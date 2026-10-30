<?php

declare(strict_types=1);

namespace app\task;

use nova\framework\core\Context;
use nova\framework\core\Logger;
use nova\plugin\corn\schedule\TaskerAbstract;
use nova\plugin\corn\schedule\TaskerManager;
use nova\plugin\task\TaskLogger;
use Throwable;

/**
 * 上传入库后的 AI 补空：从待处理队列逐本拉取，一本任务消化整批上传。
 *
 * 避免「每本单独入队」时调度器一挂/并发挤占，看起来像只处理了一本。
 */
class UploadAiFillTask extends TaskerAbstract
{
    public const string PENDING_KEY = 'book/upload_ai_pending';
    public const string TASK_NAME = 'AI填充_upload_batch';

    public function getTimeOut(): int
    {
        return 3600;
    }

    public function onStart(): void
    {
        $cache = Context::instance()->cache;
        $processed = 0;

        while (true) {
            $pending = $cache->get(self::PENDING_KEY);
            if (!is_array($pending) || $pending === []) {
                break;
            }

            $id = (int)array_shift($pending);
            $cache->set(self::PENDING_KEY, array_values($pending), 7200);
            if ($id <= 0) {
                continue;
            }

            $processed++;
            // 单本复用识别逻辑（onlyEmpty=true）
            (new AiIdentifyTask([$id], true))->onStart();
        }

        if ($processed === 0) {
            TaskLogger::log('上传补空队列为空，跳过');
            return;
        }

        // 跑的过程中又有新书入队：再挂一轮（空 cron ≈ 10 秒后）
        $left = $cache->get(self::PENDING_KEY);
        if (is_array($left) && $left !== []) {
            TaskLogger::log('队列仍有 ' . count($left) . ' 本，继续排队');
            TaskerManager::del(self::TASK_NAME);
            TaskerManager::add('', new self(), self::TASK_NAME);
        }
    }

    public function onStop(): void
    {
    }

    public function onAbort(Throwable $e): void
    {
        Logger::error('[UploadAiFillTask] 异常中止: ' . $e->getMessage());
        TaskLogger::log('上传 AI 补空异常中止：' . $e->getMessage(), 'error');
    }

    /**
     * 上传入库后调用：把书 ID 丢进队列，并确保有一条批处理任务在跑。
     */
    public static function enqueue(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        $cache = Context::instance()->cache;
        $pending = $cache->get(self::PENDING_KEY);
        if (!is_array($pending)) {
            $pending = [];
        }
        if (!in_array($id, $pending, true)) {
            $pending[] = $id;
            $cache->set(self::PENDING_KEY, $pending, 7200);
        }

        if (!TaskerManager::has(self::TASK_NAME)) {
            TaskerManager::add('', new self(), self::TASK_NAME);
        }
    }
}
