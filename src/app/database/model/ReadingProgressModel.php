<?php

namespace app\database\model;

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
     * 解析进度字符串格式:
     * 1745487877136*1@0#7834:1.6%
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);
        $pattern = '/^(?P<ts>\d+)\*(?P<spine>\d+)@(?P<page>\d+)#(?P<offset>\d+):(?P<pct>\d+(?:\.\d+)?)%$/';

        if (!preg_match($pattern, $value, $matches)) {
            throw new \InvalidArgumentException('Invalid progress string format.');
        }

        $model = new self();
        $model->raw = $value;
        $model->timestamp = (int)$matches['ts'];
        $model->spineIndex = (int)$matches['spine'];
        $model->pageIndex = (int)$matches['page'];
        $model->offset = (int)$matches['offset'];
        $model->percentText = $matches['pct'];
        $model->percent = (float)$matches['pct'];

        return $model;
    }

    public function getUnique(): array
    {
        return ['filename'];
    }

    public function toString(): string
    {
        $percentText = $this->percentText;
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
}
