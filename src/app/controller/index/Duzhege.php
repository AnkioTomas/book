<?php

namespace app\controller\index;

use app\utils\DuzhegeTasker;
use nova\framework\core\Context;
use nova\framework\http\Response;
use nova\plugin\corn\schedule\TaskerManager;

class Duzhege extends BaseController
{
    public static array $books = [
      "儿童文学选萃版" => [
          "path" =>  "/OneDrive/儿童文学/{year}/选萃版",
          "min" => 2022,
      ],
        "儿童文学经典版" =>  [
            "path" =>  "/OneDrive/儿童文学/{year}/经典版",
            "min" => 2022,
        ],
        "儿童文学故事版" =>  [
            "path" =>  "/OneDrive/儿童文学/{year}/故事版",
            "min" => 2022,
        ],
        "故事会正刊" => [
            "path" =>  "/OneDrive/故事会/{year}/正刊",
            "min" => 2021,
        ],
        "故事会校园版" => [
            "path" =>  "/OneDrive/故事会/{year}/校园版",
            "min" => 2021,
        ]
    ];



    public function cron():Response
    {
        // GET 请求：返回当前配置
        if ($this->request->isGet()) {

            $books = [];

            foreach (self::$books as $key => $book) {
                $books[] = [
                    'book' => $key,
                    'cron' => TaskerManager::get($key)?->cron?:""
                ];
            }

            return Response::asJson([
                'code' => 200,
                'data' => $books
            ]);
        }

        // POST 请求：设置任务
        $cron = $this->request->post('cron', '');
        $book = urldecode($this->request->post('book', ''));

        $item = self::$books[$book] ?? null;
        if (empty($item)) {
            return Response::asJson([
                'code' => 404,
                'msg' => '没有该任务'
            ]);
        }

        TaskerManager::del($book);
        if (empty($cron)) {
            return Response::asJson([
                'code' => 200,
                'msg' => "已取消定时任务"
            ]);
        }

        // 添加新任务
        $tasker = new DuzhegeTasker($book,$item['path'],$item['min']);
        TaskerManager::add($cron, $tasker, $book, -1);

        return Response::asJson([
            'code' => 200,
            'msg' => "定时任务已设置: {$cron}"
        ]);
    }
}