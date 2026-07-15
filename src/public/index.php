<?php

declare(strict_types=1);

namespace app;

$conf = include_once "../example.config.php";
$exts = $conf['ext'] ?? [];
$extensions = get_loaded_extensions();

$missingExtensions = array_diff($exts, $extensions);

if (!empty($missingExtensions)) {
    echo "以下 PHP 扩展尚未安装：\n";
    foreach ($missingExtensions as $extension) {
        echo " - {$extension}\n";
    }

    exit(1);
}

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
}
include __DIR__ . '/../nova/framework/bootstrap.php';
