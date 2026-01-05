<?php

namespace tests;

use app\utils\DuzhegeTasker;
use nova\commands\test\TestCase;

class DuzheTaskTest extends TestCase
{

    function test()
    {
        $test = new DuzhegeTasker("儿童文学选萃版","/OneDrive/儿童文学/{year}/选萃版",2022);
        $test->onStart();

    }
}