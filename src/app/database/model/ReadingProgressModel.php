<?php

namespace app\database\model;

use nova\framework\core\Logger;
use nova\plugin\orm\object\Model;

class ReadingProgressModel extends Model
{
    public string $filename = '';     // 文件名
    public string $raw = '';          // 原始进度字符串
    public int $timestamp = 0;      // 毫秒时间戳
    public int $spineIndex = 0;     // 章节/脊柱索引
    public int $pageIndex = 0;      // 章节内页/段索引
    public int $offset = 0;         // 章节内偏移
    public float $percent = 0.0;    // 阅读百分比(去掉%)
    public string $percentText = '0'; // 原始百分比文本(不含%)

    /**
     * 第三方进度串（可多形态并存）。始终以原始串写入 {@see $raw}，结构化字段仅为内部使用；
     * 规范输出见 {@see toString()}（完整形态）。
     *
     * 支持形态：
     * - 完整：{ts}*{spine}@{page}#{offset}:{pct}%
     * - 省略 offset：{ts}*{spine}@{page}:{pct}%（offset 视为 0）
     * - 短串：{ts}*{page}:{pct}%（此处为全书/阅读器页码语义，spine、offset 视为 0）
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('Empty progress string.');
        }

        $model = new self();
        $model->raw = $value;

        $full = '/^(?P<ts>\d+)\*(?P<spine>\d+)@(?P<page>\d+)#(?P<offset>\d+):(?P<pct>\d+(?:\.\d+)?)%+$/';
        $noHash = '/^(?P<ts>\d+)\*(?P<spine>\d+)@(?P<page>\d+):(?P<pct>\d+(?:\.\d+)?)%+$/';
        $short = '/^(?P<ts>\d+)\*(?P<page>\d+):(?P<pct>\d+(?:\.\d+)?)%+$/';

        $m = [];
        if (preg_match($full, $value, $m)) {
            $model->timestamp = (int)$m['ts'];
            $model->spineIndex = (int)$m['spine'];
            $model->pageIndex = (int)$m['page'];
            $model->offset = (int)$m['offset'];
        } elseif (preg_match($noHash, $value, $m)) {
            $model->timestamp = (int)$m['ts'];
            $model->spineIndex = (int)$m['spine'];
            $model->pageIndex = (int)$m['page'];
            $model->offset = 0;
        } elseif (preg_match($short, $value, $m)) {
            $model->timestamp = (int)$m['ts'];
            $model->spineIndex = 0;
            $model->pageIndex = (int)$m['page'];
            $model->offset = 0;
        } else {
            Logger::error('Invalid progress string format.'.$value);
        }

        $pct = $m['pct'] ?? '0';
        $model->percentText = self::sanitizePercentText($pct);
        $model->percent = (float)$model->percentText;

        return $model;
    }

    public function getUnique(): array
    {
        return ['filename'];
    }

    public function toString(): string
    {
        $percentText = self::sanitizePercentText($this->percentText);
        if ($percentText === '') {
            $percentText = self::normalizePercentText($this->percent);
        }

        return sprintf(
            '%d*%d@%d#%d:%s%%',
            $this->timestamp,
            $this->spineIndex,
            $this->pageIndex,
            $this->offset,
            $percentText
        );
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function normalizePercentText(float $percent): string
    {
        $formatted = rtrim(rtrim(sprintf('%.6f', $percent), '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    private static function sanitizePercentText(string $value): string
    {
        $clean = trim($value);
        $clean = rtrim($clean, '%');
        return $clean === '' ? '0' : $clean;
    }
}
