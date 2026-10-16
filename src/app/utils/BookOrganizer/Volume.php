<?php

declare(strict_types=1);

namespace app\utils\BookOrganizer;

/**
 * 分卷标记识别：（上）/（中）/（下）、（一）/（二）、（1）、（第三卷）、（全3册）等。
 * 用于：导入解析时保留分卷、AI 识别时校验候选分卷、豆瓣候选打分时剔除错卷。
 */
class Volume
{
    private const CN_NUM = '一二三四五六七八九十两〇零百';

    /** 括号内容是否为分卷标记的正则（不含括号） */
    public static function innerPattern(): string
    {
        $n = '(?:\d{1,3}|[' . self::CN_NUM . ']{1,4})';
        return '(?:[上中下][册卷部集篇]?|' . $n . '|第' . $n . '[卷册部集篇辑]|全' . $n . '[册卷部])';
    }

    public static function isVolumeInner(string $inner): bool
    {
        return (bool)preg_match('/^\s*' . self::innerPattern() . '\s*$/u', $inner);
    }

    /**
     * 提取分卷并归一化为可比较的键：上/中/下 原样，数字一律阿拉伯数字，全N册 → "全N"。
     * 无分卷返回 null。
     */
    public static function extract(string $title): ?string
    {
        $p = self::innerPattern();
        // 1) 括号形式（全角/半角/方括号）
        if (preg_match_all('/[（(\[【]\s*(' . $p . ')\s*[）)\]】]/u', $title, $m) && $m[1] !== []) {
            return self::key(end($m[1]));
        }
        // 2) 无括号：第N卷/册/部、末尾 上册/中册/下册、末尾 卷N
        $n = '(?:\d{1,3}|[' . self::CN_NUM . ']{1,4})';
        if (preg_match('/第(' . $n . ')[卷册部集辑]/u', $title, $m)) {
            return self::key($m[1]);
        }
        if (preg_match('/([上中下])[册卷部]\s*$/u', $title, $m)) {
            return $m[1];
        }
        return null;
    }

    private static function key(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^([上中下])/u', $raw, $m)) {
            return $m[1];
        }
        $all = str_starts_with($raw, '全');
        if (preg_match('/(\d+|[' . self::CN_NUM . ']+)/u', $raw, $m)) {
            $num = (string)self::toInt($m[1]);
            return $all ? '全' . $num : $num;
        }
        return $raw;
    }

    private static function toInt(string $s): int
    {
        if (ctype_digit($s)) {
            return (int)$s;
        }
        $map = ['〇' => 0, '零' => 0, '一' => 1, '二' => 2, '两' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9];
        $total = 0;
        $cur = 0;
        foreach (preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) as $ch) {
            if (isset($map[$ch])) {
                $cur = $map[$ch];
            } elseif ($ch === '十') {
                $total += ($cur ?: 1) * 10;
                $cur = 0;
            } elseif ($ch === '百') {
                $total += ($cur ?: 1) * 100;
                $cur = 0;
            }
        }
        return $total + $cur;
    }

    /** 两个标题的分卷是否冲突（双方都有分卷且不同）。任一方无分卷视为不冲突。 */
    public static function conflicts(string $a, string $b): bool
    {
        $va = self::extract($a);
        $vb = self::extract($b);
        return $va !== null && $vb !== null && $va !== $vb;
    }

    /** 标题比较前归一化：统一 ·・•：:— 等分隔符与全半角括号，去空白，小写 */
    public static function normalizeTitle(string $title): string
    {
        $t = mb_strtolower($title);
        $t = str_replace(['（', '）', '【', '】', '［', '］'], ['(', ')', '[', ']', '[', ']'], $t);
        $t = preg_replace('/[·・•．\.：:—–\-_\s　]+/u', '', $t);
        return (string)$t;
    }
}
