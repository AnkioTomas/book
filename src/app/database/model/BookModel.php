<?php

namespace app\database\model;

use app\database\dao\BookDao;
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
        return 2;
    }

    public function getUpgradeSql(): array
    {
        return [
            "1_2" => [
                "ALTER TABLE `book` ADD COLUMN `series` VARCHAR(50) NOT NULL DEFAULT ''",
                "ALTER TABLE `book` ADD COLUMN `seriesNum` INT NOT NULL DEFAULT 0",
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

    /**
     * 从书名中提取系列编号
     * 简单粗暴：移除所有非数字字符，剩余数字转为 int
     * 例如: "哈利波特7" -> 7, "第12卷" -> 12
     */
    public function extractSeriesNumber(): self
    {
        if ($this->seriesNum > 0) return $this;
        
        $digits = preg_replace('/\D+/', '', $this->bookName);
        $this->seriesNum = $digits !== '' ? (int)$digits : 0;

        BookDao::getInstance()->updateModel($this);

        return $this;
    }

}