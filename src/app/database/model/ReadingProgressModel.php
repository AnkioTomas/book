<?php

declare(strict_types=1);

namespace app\database\model;

use nova\framework\core\Logger;
use nova\plugin\orm\object\Model;

class ReadingProgressModel extends Model
{
    public string $filename = '';     // 文件名
    public string $raw = '';          // 原始进度字符串
    public int $timestamp = 0;      // 版本时刻（毫秒）。本地阅读=保存时刻，远端下载=文件 mtime。不是 .po 串里的第一个数字。
    public int $spineIndex = 0;     // 章节/脊柱索引
    public int $pageIndex = 0;      // 章节分片（静读天下 lastSplitIndex）；短格式里是 PDF 页码
    public int $offset = 0;         // 分片内字符偏移（静读天下 lastPosition）
    public float $percent = 0.0;    // 阅读百分比(去掉%)
    public string $percentText = '0'; // 原始百分比文本(不含%)

    /**
     * .po 星号前的设备号（静读天下 deviceRandomID，本站写自己的 webdav.deviceId）。
     * 装机时定死，阅读时不改。静读天下发现不是自己的设备号才会采用这段进度。
     */
    public string $deviceId = '';

    /** 静读天下 WebView 滚屏尾缀 |lastWebY。-1 表示原串没有这段。 */
    public int $webY = -1;

    /** full / nohash / short，对应静读天下三种进度串。回写必须用原形态，PDF 不能写成带 # 的长串。 */
    public string $shape = 'full';

    /**
     * 精确定位坐标（XPointer / EPUB CFI），由 KOReader 之类的客户端上报。
     *
     * 刻意不进 {@see $raw}：raw 是静读天下的 .po 进度串，格式固定为
     * {设备号}*{章}@{分片}#{字符偏移}:{百分比}%，塞不下也不该塞别的东西。
     * 这一列只在本站接口内往返，对静读天下完全透明。
     */
    public string $locator = '';

    public function getSchemaVersion(): int
    {
        return 3;
    }

    public function getUpgradeSql(): array
    {
        return [
            "1_2" => [
                // TEXT 不带 DEFAULT：MySQL 的 TEXT 不支持默认值，PG 不认 LONGTEXT，
                // 这样写 mysql / sqlite / pgsql 三个驱动都能直接执行。
                "ALTER TABLE {table} ADD COLUMN `locator` TEXT",
            ],
            "2_3" => [
                "ALTER TABLE {table} ADD COLUMN `deviceId` VARCHAR(40) NOT NULL DEFAULT ''",
                "ALTER TABLE {table} ADD COLUMN `webY` INT NOT NULL DEFAULT -1",
                "ALTER TABLE {table} ADD COLUMN `shape` VARCHAR(8) NOT NULL DEFAULT 'full'",
            ],
        ];
    }

    /**
     * 静读天下 .po（可多形态并存）。始终以原始串写入 {@see $raw}。
     * 星号前是设备号，不是版本时间；版本看文件 mtime，由调用方写入 {@see $timestamp}。
     *
     * 支持形态：
     * - 完整：{设备号}*{章}@{分片}#{字符偏移}:{百分比}%
     * - 省略 offset：{设备号}*{章}@{分片}:{百分比}%（offset 视为 0）
     * - 短串：{设备号}*{页码}:{百分比}%（PDF。页码进 pageIndex，spine、offset 为 0）
     * 尾缀 |整数 是 WebView 滚屏，原样保留。
     */
    public static function fromString(string $value): self
    {
        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('Empty progress string.');
        }

        $model = new self();
        $model->raw = $value;

        $full = '/^(?P<device>\d+)\*(?P<spine>\d+)@(?P<page>\d+)#(?P<offset>\d+):(?P<pct>\d+(?:\.\d+)?)%+(?:\|(?P<web>-?\d+))?$/';
        $noHash = '/^(?P<device>\d+)\*(?P<spine>\d+)@(?P<page>\d+):(?P<pct>\d+(?:\.\d+)?)%+(?:\|(?P<web>-?\d+))?$/';
        $short = '/^(?P<device>\d+)\*(?P<page>\d+):(?P<pct>\d+(?:\.\d+)?)%+(?:\|(?P<web>-?\d+))?$/';

        $m = [];
        if (preg_match($full, $value, $m)) {
            $model->shape = 'full';
            $model->spineIndex = (int)$m['spine'];
            $model->pageIndex = (int)$m['page'];
            $model->offset = (int)$m['offset'];
        } elseif (preg_match($noHash, $value, $m)) {
            $model->shape = 'nohash';
            $model->spineIndex = (int)$m['spine'];
            $model->pageIndex = (int)$m['page'];
            $model->offset = 0;
        } elseif (preg_match($short, $value, $m)) {
            $model->shape = 'short';
            $model->spineIndex = 0;
            $model->pageIndex = (int)$m['page'];
            $model->offset = 0;
        } else {
            Logger::error('Invalid progress string format.'.$value);
        }

        if (($m['device'] ?? '') !== '') {
            $model->deviceId = $m['device'];
        }
        if (isset($m['web']) && $m['web'] !== '') {
            $model->webY = (int)$m['web'];
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

        $device = $this->deviceId !== '' ? $this->deviceId : '0';
        if ($this->shape === 'short') {
            $body = sprintf('%s*%d:%s%%', $device, $this->pageIndex, $percentText);
        } elseif ($this->shape === 'nohash') {
            $body = sprintf('%s*%d@%d:%s%%', $device, $this->spineIndex, $this->pageIndex, $percentText);
        } else {
            $body = sprintf(
                '%s*%d@%d#%d:%s%%',
                $device,
                $this->spineIndex,
                $this->pageIndex,
                $this->offset,
                $percentText
            );
        }
        if ($this->webY >= 0) {
            $body .= '|' . $this->webY;
        }
        return $body;
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
