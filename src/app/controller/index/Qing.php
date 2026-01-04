<?php

namespace app\controller\index;

use app\utils\QingTasker;
use nova\framework\http\Response;
use nova\plugin\corn\schedule\TaskerManager;

class Qing extends BaseController
{
    private const TASK_NAME = "青年文摘";

    /**
     * 获取或设置定时任务
     * GET: 获取当前配置
     * POST: 设置或取消任务
     */
    function cron(): Response
    {
        // GET 请求：返回当前配置
        if ($this->request->isGet()) {
            return $this->status();
        }
        
        // POST 请求：设置任务
        $cron = $this->request->post('cron', '');

        TaskerManager::del(self::TASK_NAME);
        if (empty($cron)) {
            return Response::asJson([
                'code' => 200,
                'msg' => "已取消定时任务"
            ]);
        }

        // 添加新任务
        $tasker = new QingTasker();
        TaskerManager::add($cron, $tasker, self::TASK_NAME, -1);
        
        return Response::asJson([
            'code' => 200,
            'msg' => "定时任务已设置: {$cron}"
        ]);
    }

    /**
     * 获取当前任务状态
     */
    function status(): Response
    {
        $exists = TaskerManager::has(self::TASK_NAME);
        $cron = '';
        $nextRun = '';
        
        if ($exists) {
            $list = TaskerManager::list();
            foreach ($list as $task) {
                if ($task->name === self::TASK_NAME) {
                    $cron = $task->cron;
                    $nextRun = date('Y-m-d H:i:s', $task->next);
                    break;
                }
            }
        }
        
        return Response::asJson([
            'code' => 200,
            'data' => [
                'enabled' => $exists,
                'cron' => $cron,
                'nextRun' => $nextRun
            ]
        ]);
    }
}