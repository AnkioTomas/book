<?php

declare(strict_types=1);

namespace app\database\model;

use nova\plugin\orm\object\Model;

class BookModel extends Model
{
    // 书籍基本信息
    public string $bookName = "";      // 书名
    public string $author = "";        // 作者
    public string $description = "";   // 描述
    public string $category = "";      // 分类

    // 文件信息
    public string $filename = "";      // 文件名: 巷子里的野猫.epub
    public string $downloadUrl = "";   // 下载路径: [WebDav]/Apps/Books/巷子里的野猫.epub
    public string $coverUrl = "";      // 封面图片URL

    // 用户数据
    public string $rate = "0";           // 评分: 0-5
    public string $favorite = "";      // 收藏夹标签: 动物
    public string $deviceId = "";         // 设备ID
    public int $isFinished = 0;        // 是否已读完: 0/1

    // 关联数据 (如果groupBooks是字符串存储,否则应该用关联表)
    public array $groupBooks = [];    // 分组书籍JSON: []
    public string $groupName = "";     // 分组名称

    public string $series  = "";

    public int $seriesNum = 0;

    // 时间戳
    public int $addTime = 0;          // 添加时间戳(毫秒)

    /**
     * {
     * "addTime": "1761286619609",
     * "author": "阿尔贝•加缪",
     * "bookName": "局外人",
     * "category": "\u003c荒诞小说系列\u003e\n#1.0#\n哲学·社会\n时代\n",
     * "description": "加缪",
     * "deviceId": "1745487877136",
     * "downloadUrl": "",
     * "favorite": "荒谬",
     * "filename": "局外人.epub",
     * "groupBooks": [
     *
     * ],
     * "groupName": "",
     * "rate": "5"
     * },
     */

    public function getUnique(): array
    {
        return [
            'filename'
        ];
    }

    public function getSchemaVersion(): int
    {
        return 3;
    }

    public function getUpgradeSql(): array
    {
        return [
            "1_2" => [
                "ALTER TABLE `book` ADD COLUMN `series` VARCHAR(50) NOT NULL DEFAULT ''",
                "ALTER TABLE `book` ADD COLUMN `seriesNum` INT NOT NULL DEFAULT 0",
            ],
            "2_3" => [
                "ALTER TABLE `book` ADD COLUMN `isFinished` TINYINT(1) NOT NULL DEFAULT 0",
            ],
        ];
    }

    /**
     * 从 category 中提取系列信息到 series 和 seriesNum
     * category 格式: <系列名>\n#编号#\n实际分类内容
     */
    public function splitCategory2Series(): void
    {
        // 先清空 series 和 seriesNum，避免脏数据
        $this->series = '';
        $this->seriesNum = 0;

        // 匹配格式: <系列名>\n#编号#
        if (preg_match('/^<(.+?)>\s*\n\s*#(.+?)#\s*\n/s', $this->category, $matches)) {
            $this->series = trim($matches[1]);
            $this->seriesNum = (int)(float)trim($matches[2]); // 25.0 -> 25
        }

        // 无论是否匹配成功，都清理 category 中的系列前缀
        $this->category = preg_replace('/^<.+?>\s*\n\s*#.+?#\s*\n/s', '', $this->category);
        $this->category = trim($this->category);
    }

    /**
     * 将 series 和 seriesNum 合并回 category 开头
     * 生成格式: <系列名>\n#编号#\n原分类内容
     */
    public function pushSeries2Category(): self
    {
        // 先清理 category 中已存在的系列信息，避免重复
        $this->category = preg_replace('/^<.+?>\s*\n\s*#.+?#\s*\n/s', '', $this->category);
        $this->category = trim($this->category);

        // 只有 series 不为空时才添加到 category
        if (!empty($this->series)) {
            $seriesLine = "<{$this->series}>\n#{$this->seriesNum}#\n";
            $this->category = $seriesLine . $this->category;
        }
        return $this;
    }

    // ===== 语义修正层 =====
    // 历史包袱：DB 字段命名与 App 实际语义相反，不动 schema 仅在此暴露正确语义。
    //   favorite (单值)  → 实际是「分类」(App 称收藏夹)
    //   category (多行)  → 实际是「标签集」(\n 分隔，首部可能含 <系列>#编号# 前缀)
    // 新代码请用本节方法，不要再直接读写 $favorite / $category。

    /** 「已读」标签的固定文案，集中一处避免散落字面量。 */
    public const TAG_FINISHED = '已读';

    /** 真·分类：单值，对应底层 favorite 字段。 */
    public function getCategoryName(): string
    {
        return $this->favorite;
    }

    public function setCategoryName(string $name): self
    {
        $this->favorite = trim($name);
        return $this;
    }

    /**
     * 真·标签集：数组形式访问 category 中的标签部分（自动剥离系列前缀）。
     */
    public function getTags(): array
    {
        $tags = [];
        foreach (explode("\n", $this->category) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $tags[] = $line;
            }
        }
        return $tags;
    }

    /**
     * 写入标签集；自动去重去空，并保留原有的系列前缀（不破坏 series/seriesNum 的存储位置）。
     */
    public function setTags(array $tags): self
    {
        $this->category = implode("\n", $tags);
        return $this;
    }

    /**
     * 设置「已读 / 未读」状态，同步 isFinished 与「已读」标签，保证两者永远一致。
     * 已读 → 自动加入「已读」标签（去重）
     * 未读 → 自动移除「已读」标签
     */
    public function markFinished(bool $finished = true): self
    {
        $this->isFinished = $finished ? 1 : 0;

        $tags = $this->getTags();
        $has = in_array(self::TAG_FINISHED, $tags, true);
        if ($finished && !$has) {
            $tags[] = self::TAG_FINISHED;
            $this->setTags($tags);
        } elseif (!$finished && $has) {
            $this->setTags(array_values(array_filter(
                $tags,
                fn ($t) => $t !== self::TAG_FINISHED
            )));
        }
        return $this;
    }
}
