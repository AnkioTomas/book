<?php
/*
 * Copyright (c) 2025. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

declare(strict_types=1);
$config = new PhpCsFixer\Config();


return $config
    ->setRules([
        '@PSR12' => true,
        'declare_strict_types' => true,
        'indentation_type' => true,
        'array_indentation' => true,
        'no_trailing_whitespace' => true,
        'no_extra_blank_lines' => true,
        'ordered_imports' => true,
        'method_argument_space' => ['after_heredoc' => true, 'on_multiline' => 'ensure_fully_multiline'],
        'phpdoc_align' => true,
        'no_unused_imports' => true,
        'visibility_required' => ['elements' => ['method', 'property']],  // 正确配置
    ])
    ->setFinder(PhpCsFixer\Finder::create()
        ->in(__DIR__) // 扫描当前目录
    );