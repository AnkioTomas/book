<?php

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

    // 关联数据 (如果groupBooks是字符串存储,否则应该用关联表)
    public array $groupBooks = [];    // 分组书籍JSON: []
    public string $groupName = "";     // 分组名称

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
            [
                'bookName', 'author'
            ]
        ];
    }

}