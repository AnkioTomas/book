<?php

namespace app\utils\BookManager;

use nova\framework\core\Instance;

class MoonManager extends Instance
{
    function delete(string $filename)
    {
        BookManager::getInstance()->deleteBook($filename);
        CoverManager::getInstance()->deleteCover($filename);
        ProgressManager::getInstance()->deleteProgress($filename);
    }
}