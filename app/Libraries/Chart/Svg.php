<?php

namespace App\Libraries\Chart;

/**
 * Tiny dependency-free SVG chart builder for the dashboard.
 *
 * Every method returns a self-contained <svg> string sized to a fixed
 * viewBox and set to scale to its container's width. Colours are plain
 * hex that track the app palette in public/assets/app.css.
 */
class Svg
{
    // Colours resolve against the active theme's CSS variables (app.css),
    // with a hard hex fallback so charts still render outside the app shell.
    public const INK    = 'var(--c-ink,#1f2733)';
    public const MUTED  = 'var(--c-muted,#6b7686)';
    public const LINE   = 'var(--c-line,#e2e5ea)';
    public const BRAND  = 'var(--c-s1,#1f5f8b)';
    public const GREEN  = 'var(--c-pos,#1a7f45)';
    public const RED    = 'var(--c-neg,#b3261e)';
    public const AMBER  = 'var(--c-s3,#a56a00)';

    /** Extra hues for categorical charts. */
    public const PALETTE = [
        'var(--c-s1,#1f5f8b)', 'var(--c-s2,#1a7f45)', 'var(--c-s3,#a56a00)', 'var(--c-s4,#7b52c9)',
        'var(--c-s5,#0f766e)', 'var(--c-s6,#b3261e)', 'var(--c-s7,#5b6b7b)', 'var(--c-s8,#9a3412)',
    ];

    private const W = 480;
    private const H = 210;

    public static function abbr(float $n): string
    {
        $a = abs($n);
        if ($a >= 1_000_000_000_000) {
            return self::trim($n / 1_000_000_000_000) . ' T';
        }
        if ($a >= 1_000_000_000) {
            return self::trim($n / 1_000_000_000) . ' M';
        }
        if ($a >= 1_000_000) {
            return self::trim($n / 1_000_000) . ' jt';
        }
        if ($a >= 1_000) {
            return self::trim($n / 1_000) . ' rb';
        }

        return self::trim($n);
    }

    private static function trim(float $n): string
    {
        $s = number_format($n, abs($n) < 10 && $n != (int) $n ? 1 : 0, '.', ',');

        return $s;
    }

    private static function open(): string
    {
        return '<svg viewBox="0 0 ' . self::W . ' ' . self::H . '" width="100%" height="auto"'
            . ' preserveAspectRatio="xMidYMid meet" font-family="-apple-system,Segoe UI,Roboto,Arial,sans-serif"'
            . ' style="display:block">';
    }

    private static function empty(string $msg): string
    {
        return self::open()
            . '<text x="' . (self::W / 2) . '" y="' . (self::H / 2) . '" text-anchor="middle" fill="' . self::MUTED . '" font-size="12">'
            . htmlspecialchars($msg) . '</text></svg>';
    }

    /**
     * Grouped vertical bars.
     *
     * @param list<string>              $labels
     * @param array<string,list<float>> $series  name => values (aligned to $labels)
     * @param list<string>|null         $colors
     */
    public static function groupedBars(array $labels, array $series, ?array $colors = null): string
    {
        $vals = array_merge(...array_values($series)) ?: [0.0];
        $max  = max(max($vals), 0.0);
        $min  = min(min($vals), 0.0);
        if ($max === 0.0 && $min === 0.0) {
            return self::empty('No data for this range');
        }

        $padL = 44;
        $padR = 10;
        $padT = 22;
        $padB = 34;
        $plotW = self::W - $padL - $padR;
        $plotH = self::H - $padT - $padB;
        $range = $max - $min ?: 1;
        $zeroY = $padT + ($max / $range) * $plotH;
        $names = array_keys($series);
        $cols  = $colors ?? [self::GREEN, self::RED, self::BRAND];

        $svg   = self::open();
        $svg  .= self::yGrid($padL, $padR, $padT, $plotH, $min, $max);

        $n     = count($labels);
        $slot  = $plotW / max($n, 1);
        $gCount = count($names);
        $bw    = min(18, ($slot * 0.7) / max($gCount, 1));
        $gw    = $bw * $gCount;

        foreach ($labels as $i => $label) {
            $cx = $padL + $slot * $i + $slot / 2;
            foreach ($names as $s => $name) {
                $v  = $series[$name][$i] ?? 0;
                $bh = abs($v) / $range * $plotH;
                $x  = $cx - $gw / 2 + $bw * $s;
                $y  = $v >= 0 ? $zeroY - $bh : $zeroY;
                $svg .= '<rect x="' . round($x, 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1)
                    . '" height="' . round(max($bh, 0.5), 1) . '" rx="1.5" fill="' . $cols[$s % count($cols)] . '">'
                    . '<title>' . htmlspecialchars($name . ' · ' . $label . ' : ' . self::abbr($v)) . '</title></rect>';
            }
            $svg .= self::xLabel($cx, self::H - $padB + 13, $label);
        }

        $svg .= '<line x1="' . $padL . '" y1="' . round($zeroY, 1) . '" x2="' . (self::W - $padR)
            . '" y2="' . round($zeroY, 1) . '" stroke="' . self::LINE . '" stroke-width="1"/>';
        $svg .= self::legend($padL, 13, $names, $cols);

        return $svg . '</svg>';
    }

    /**
     * Single series of vertical bars; bars are coloured by sign.
     *
     * @param list<string> $labels
     * @param list<float>  $values
     */
    public static function signedBars(array $labels, array $values): string
    {
        if (! array_filter($values, static fn ($v) => abs($v) > 0.005)) {
            return self::empty('No data for this range');
        }
        $max = max(max($values), 0.0);
        $min = min(min($values), 0.0);

        $padL = 44;
        $padR = 10;
        $padT = 14;
        $padB = 34;
        $plotW = self::W - $padL - $padR;
        $plotH = self::H - $padT - $padB;
        $range = ($max - $min) ?: 1;
        $zeroY = $padT + ($max / $range) * $plotH;

        $svg  = self::open();
        $svg .= self::yGrid($padL, $padR, $padT, $plotH, $min, $max);

        $n    = count($labels);
        $slot = $plotW / max($n, 1);
        $bw   = min(26, $slot * 0.6);

        foreach ($labels as $i => $label) {
            $v   = $values[$i] ?? 0;
            $cx  = $padL + $slot * $i + $slot / 2;
            $bh  = abs($v) / $range * $plotH;
            $y   = $v >= 0 ? $zeroY - $bh : $zeroY;
            $svg .= '<rect x="' . round($cx - $bw / 2, 1) . '" y="' . round($y, 1) . '" width="' . round($bw, 1)
                . '" height="' . round(max($bh, 0.5), 1) . '" rx="1.5" fill="' . ($v >= 0 ? self::GREEN : self::RED) . '">'
                . '<title>' . htmlspecialchars($label . ' : ' . self::abbr($v)) . '</title></rect>';
            $svg .= self::xLabel($cx, self::H - $padB + 13, $label);
        }

        $svg .= '<line x1="' . $padL . '" y1="' . round($zeroY, 1) . '" x2="' . (self::W - $padR)
            . '" y2="' . round($zeroY, 1) . '" stroke="' . self::MUTED . '" stroke-width="1"/>';

        return $svg . '</svg>';
    }

    /**
     * Grouped bars (left money axis) with a percentage line overlaid on a
     * right-hand 0-100% axis. Built for "Sales · Cost of Sales · GOP %".
     *
     * @param list<string>              $labels
     * @param array<string,list<float>> $barSeries   name => values
     * @param list<float>               $pct         fractions 0..1, aligned to $labels
     * @param string                    $pctLabel
     * @param list<string>|null          $barColors
     */
    public static function barsAndLine(array $labels, array $barSeries, array $pct, string $pctLabel, ?array $barColors = null): string
    {
        $vals = array_merge(...array_values($barSeries)) ?: [0.0];
        $max  = max(max($vals), 0.0);
        if ($max <= 0.0) {
            return self::empty('No data for this range');
        }

        $padL = 44;
        $padR = 40;
        $padT = 24;
        $padB = 34;
        $plotW = self::W - $padL - $padR;
        $plotH = self::H - $padT - $padB;
        $names = array_keys($barSeries);
        $cols  = $barColors ?? [self::BRAND, self::AMBER];

        // right axis scale: round the max % up to a sensible ceiling
        $pctMax = max(0.01, max(array_map('abs', $pct ?: [0.0])));
        $pctTop = $pctMax <= 0.5 ? ceil($pctMax * 20) / 20 : ceil($pctMax * 4) / 4; // 5% or 25% steps

        $svg  = self::open();
        $svg .= self::yGrid($padL, $padR, $padT, $plotH, 0.0, $max);

        $n      = count($labels);
        $slot   = $plotW / max($n, 1);
        $gCount = count($names);
        $bw     = min(16, ($slot * 0.62) / max($gCount, 1));
        $gw     = $bw * $gCount;

        foreach ($labels as $i => $label) {
            $cx = $padL + $slot * $i + $slot / 2;
            foreach ($names as $s => $name) {
                $v  = $barSeries[$name][$i] ?? 0;
                $bh = max(abs($v) / $max * $plotH, 0.5);
                $x  = $cx - $gw / 2 + $bw * $s;
                $svg .= '<rect x="' . round($x, 1) . '" y="' . round($padT + $plotH - $bh, 1) . '" width="' . round($bw, 1)
                    . '" height="' . round($bh, 1) . '" rx="1.5" fill="' . $cols[$s % count($cols)] . '">'
                    . '<title>' . htmlspecialchars($name . ' · ' . $label . ' : ' . self::abbr($v)) . '</title></rect>';
            }
            $svg .= self::xLabel($cx, self::H - $padB + 13, $label);
        }

        // right % axis ticks
        for ($t = 0; $t <= 3; $t++) {
            $frac = $pctTop * $t / 3;
            $y    = $padT + $plotH - ($frac / $pctTop) * $plotH;
            $svg .= '<text x="' . (self::W - $padR + 6) . '" y="' . round($y + 3, 1) . '" font-size="9" fill="' . self::MUTED . '">'
                . round($frac * 100) . '%</text>';
        }

        // the % line
        $pts = [];
        foreach ($labels as $i => $label) {
            $frac = $pct[$i] ?? 0;
            $cx   = $padL + $slot * $i + $slot / 2;
            $cy   = $padT + $plotH - (max(min($frac, $pctTop), 0) / $pctTop) * $plotH;
            $pts[] = round($cx, 1) . ',' . round($cy, 1);
        }
        $svg .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . self::GREEN
            . '" stroke-width="2" stroke-linejoin="round"/>';
        foreach ($labels as $i => $label) {
            [$cx, $cy] = explode(',', $pts[$i]);
            $svg .= '<circle cx="' . $cx . '" cy="' . $cy . '" r="2.6" fill="' . self::GREEN . '">'
                . '<title>' . htmlspecialchars($pctLabel . ' · ' . $label . ' : ' . round(($pct[$i] ?? 0) * 100, 1) . '%') . '</title></circle>';
        }

        $svg .= self::legend($padL, 13, array_merge($names, [$pctLabel]), array_merge($cols, [self::GREEN]));

        return $svg . '</svg>';
    }

    /**
     * Filled line (area) chart, non-negative series.
     *
     * @param list<string> $labels
     * @param list<float>  $values
     */
    public static function area(array $labels, array $values, string $color = self::BRAND): string
    {
        if (! $values || ! array_filter($values, static fn ($v) => abs($v) > 0.005)) {
            return self::empty('No data for this range');
        }
        $max = max($values);
        $min = min(min($values), 0.0);

        $padL = 44;
        $padR = 10;
        $padT = 16;
        $padB = 34;
        $plotW = self::W - $padL - $padR;
        $plotH = self::H - $padT - $padB;
        $range = ($max - $min) ?: 1;
        $n     = count($values);
        $dx    = $n > 1 ? $plotW / ($n - 1) : 0;

        $pt = static function (int $i, float $v) use ($padL, $padT, $plotH, $dx, $max, $range) {
            $x = $padL + $dx * $i;
            $y = $padT + ($max - $v) / $range * $plotH;

            return round($x, 1) . ',' . round($y, 1);
        };

        $line = [];
        foreach ($values as $i => $v) {
            $line[] = $pt($i, $v);
        }
        $baseY = $padT + ($max - max($min, 0)) / $range * $plotH;
        $areaPath = 'M' . $pt(0, $values[0]) . ' L' . implode(' L', $line)
            . ' L' . round($padL + $dx * ($n - 1), 1) . ',' . round($baseY, 1)
            . ' L' . round($padL, 1) . ',' . round($baseY, 1) . ' Z';

        $svg  = self::open();
        $svg .= self::yGrid($padL, $padR, $padT, $plotH, $min, $max);
        $svg .= '<path d="' . $areaPath . '" fill="' . $color . '" fill-opacity="0.14"/>';
        $svg .= '<polyline points="' . implode(' ', $line) . '" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linejoin="round"/>';

        foreach ($values as $i => $v) {
            [$x, $y] = explode(',', $pt($i, $v));
            $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="2.5" fill="' . $color . '">'
                . '<title>' . htmlspecialchars(($labels[$i] ?? '') . ' : ' . self::abbr($v)) . '</title></circle>';
            if ($i % max(1, (int) ceil($n / 8)) === 0 || $i === $n - 1) {
                $svg .= self::xLabel((float) $x, self::H - $padB + 13, $labels[$i] ?? '');
            }
        }

        return $svg . '</svg>';
    }

    /**
     * Horizontal bars for a ranked breakdown.
     *
     * @param list<array{label:string, value:float}> $rows
     */
    public static function hBars(array $rows): string
    {
        $rows = array_values(array_filter($rows, static fn ($r) => abs($r['value']) > 0.005));
        if (! $rows) {
            return self::empty('No expenses in this range');
        }
        $max  = max(array_map(static fn ($r) => abs($r['value']), $rows)) ?: 1;
        $padL = 150;
        $padR = 54;
        $padT = 8;
        $rowH = min(24, (self::H - $padT * 2) / count($rows));
        $barW = self::W - $padL - $padR;

        $svg = self::open();
        foreach ($rows as $i => $r) {
            $y  = $padT + $rowH * $i + $rowH / 2;
            $w  = abs($r['value']) / $max * $barW;
            $c  = self::PALETTE[$i % count(self::PALETTE)];
            $svg .= '<text x="' . ($padL - 8) . '" y="' . round($y + 3.5, 1) . '" text-anchor="end" font-size="10.5" fill="' . self::INK . '">'
                . htmlspecialchars(mb_strimwidth($r['label'], 0, 24, '…')) . '</text>';
            $svg .= '<rect x="' . $padL . '" y="' . round($y - $rowH * 0.34, 1) . '" width="' . round(max($w, 1), 1)
                . '" height="' . round($rowH * 0.68, 1) . '" rx="2" fill="' . $c . '"/>';
            $svg .= '<text x="' . round($padL + $w + 6, 1) . '" y="' . round($y + 3.5, 1) . '" font-size="10" fill="' . self::MUTED . '">'
                . self::abbr($r['value']) . '</text>';
        }

        return $svg . '</svg>';
    }

    // ----------------------------------------------------------------- helpers

    private static function yGrid(int $padL, int $padR, int $padT, float $plotH, float $min, float $max): string
    {
        $range = ($max - $min) ?: 1;
        $svg   = '';
        for ($t = 0; $t <= 3; $t++) {
            $val = $max - $range * $t / 3;
            $y   = $padT + $plotH * $t / 3;
            $svg .= '<line x1="' . $padL . '" y1="' . round($y, 1) . '" x2="' . (self::W - $padR) . '" y2="' . round($y, 1)
                . '" stroke="' . self::LINE . '" stroke-width="1"/>';
            $svg .= '<text x="' . ($padL - 6) . '" y="' . round($y + 3, 1) . '" text-anchor="end" font-size="9" fill="' . self::MUTED . '">'
                . self::abbr($val) . '</text>';
        }

        return $svg;
    }

    private static function xLabel(float $x, float $y, string $text): string
    {
        return '<text x="' . round($x, 1) . '" y="' . round($y, 1) . '" text-anchor="middle" font-size="9.5" fill="' . self::MUTED . '">'
            . htmlspecialchars($text) . '</text>';
    }

    private static function legend(int $x, int $y, array $names, array $cols): string
    {
        $svg = '';
        $off = 0;
        foreach ($names as $i => $name) {
            $svg .= '<rect x="' . ($x + $off) . '" y="' . ($y - 8) . '" width="9" height="9" rx="2" fill="' . $cols[$i % count($cols)] . '"/>';
            $svg .= '<text x="' . ($x + $off + 13) . '" y="' . $y . '" font-size="10" fill="' . self::MUTED . '">' . htmlspecialchars($name) . '</text>';
            $off += 20 + mb_strlen($name) * 6.2;
        }

        return $svg;
    }
}
