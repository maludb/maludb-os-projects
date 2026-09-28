<?php
declare(strict_types=1);

/**
 * Hand-drawn SVG charts (docs/build-specs/sso-shell.md, "Charts"): one series or a few bars over at
 * most a few dozen points, rendered inside an HTMX partial with no re-init. Pure PHP, every text
 * escaped, no JavaScript. Colours come from app-overrides.css (chart-line, chart-bar, chart-guide).
 *
 *   chart_line([['label' => 'Remaining', 'points' => [3, 5, 2, ...]], ...], ['width' => 120, 'height' => 32])
 *   chart_bars([['label' => 'Sprint 1', 'value' => 21], ...], ['width' => 640, 'height' => 240, 'axes' => true])
 *   chart_stacked([['label' => 'Mon', 'values' => [3, 5, 2]], ...], ['keys' => ['To do', 'In progress', 'Done'], 'axes' => true])
 */

function chart_svg_open(int $w, int $h, string $title, string $id): string
{
    return '<svg class="chart" viewBox="0 0 ' . $w . ' ' . $h . '" width="' . $w . '" height="' . $h . '" role="img" aria-label="' . e($title) . '"'
        . ($id !== '' ? ' id="' . e($id) . '"' : '') . ' xmlns="http://www.w3.org/2000/svg">';
}

/** A line per series over the x axis (index), y from 0 (or opts.min) to the max (or opts.max). */
function chart_line(array $series, array $opts = []): string
{
    $w = (int) ($opts['width'] ?? 120);
    $h = (int) ($opts['height'] ?? 32);
    $axes = !empty($opts['axes']);
    $padL = $axes ? 36 : 2; $padR = $axes ? 8 : 2; $padT = $axes ? 8 : 2; $padB = $axes ? 22 : 2;
    $n = 0; $max = (float) ($opts['max'] ?? 0); $min = (float) ($opts['min'] ?? 0);
    foreach ($series as $s) {
        $n = max($n, count($s['points'] ?? []));
        foreach ($s['points'] ?? [] as $v) { $max = max($max, (float) $v); $min = min($min, (float) $v); }
    }
    if ($n === 0) {
        return chart_svg_open($w, $h, (string) ($opts['title'] ?? 'No data'), (string) ($opts['id'] ?? '')) . '</svg>';
    }
    $max = $max <= $min ? $min + 1 : $max;
    $iw = $w - $padL - $padR; $ih = $h - $padT - $padB;
    $x = static fn (int $i): float => $padL + ($n === 1 ? $iw / 2 : $i * $iw / ($n - 1));
    $y = static fn (float $v): float => $padT + $ih - (($v - $min) / ($max - $min)) * $ih;
    $out = chart_svg_open($w, $h, (string) ($opts['title'] ?? 'Chart'), (string) ($opts['id'] ?? ''));
    if ($axes) {
        $out .= '<line class="chart-guide" x1="' . $padL . '" y1="' . ($padT + $ih) . '" x2="' . ($w - $padR) . '" y2="' . ($padT + $ih) . '"/>';
        $out .= '<line class="chart-guide" x1="' . $padL . '" y1="' . $padT . '" x2="' . $padL . '" y2="' . ($padT + $ih) . '"/>';
        $out .= '<text class="chart-label" x="' . ($padL - 4) . '" y="' . ($padT + 4) . '" text-anchor="end">' . e(rtrim(rtrim(number_format($max, 1, '.', ''), '0'), '.')) . '</text>';
        $out .= '<text class="chart-label" x="' . ($padL - 4) . '" y="' . ($padT + $ih) . '" text-anchor="end">' . e(rtrim(rtrim(number_format($min, 1, '.', ''), '0'), '.')) . '</text>';
        foreach ((array) ($opts['labels'] ?? []) as $i => $label) {
            if ($n > 1 && ($i === 0 || $i === $n - 1 || $n <= 8 || $i % (int) ceil($n / 8) === 0)) {
                $out .= '<text class="chart-label" x="' . round($x((int) $i), 1) . '" y="' . ($h - 6) . '" text-anchor="middle">' . e((string) $label) . '</text>';
            }
        }
    }
    foreach ($series as $k => $s) {
        $pts = [];
        foreach (array_values($s['points'] ?? []) as $i => $v) { $pts[] = round($x($i), 1) . ',' . round($y((float) $v), 1); }
        $cls = 'chart-line chart-series-' . ((int) $k + 1) . (!empty($s['dashed']) ? ' chart-dashed' : '');
        $out .= '<polyline class="' . $cls . '" fill="none" points="' . implode(' ', $pts) . '"><title>' . e((string) ($s['label'] ?? '')) . '</title></polyline>';
    }
    return $out . '</svg>';
}

/** One bar per row; value from 0 to the max. Optional rows[]['value2'] draws a second, lighter bar beside it. */
function chart_bars(array $rows, array $opts = []): string
{
    $w = (int) ($opts['width'] ?? 640); $h = (int) ($opts['height'] ?? 240);
    $axes = !empty($opts['axes']);
    $padL = $axes ? 36 : 2; $padR = 8; $padT = 8; $padB = $axes ? 28 : 2;
    $n = count($rows);
    if ($n === 0) {
        return chart_svg_open($w, $h, (string) ($opts['title'] ?? 'No data'), (string) ($opts['id'] ?? '')) . '</svg>';
    }
    $max = (float) ($opts['max'] ?? 0);
    foreach ($rows as $r) { $max = max($max, (float) ($r['value'] ?? 0), (float) ($r['value2'] ?? 0)); }
    $max = $max <= 0 ? 1 : $max;
    $iw = $w - $padL - $padR; $ih = $h - $padT - $padB;
    $slot = $iw / $n; $two = array_filter($rows, static fn ($r): bool => isset($r['value2'])) !== [];
    $bw = $slot * ($two ? 0.36 : 0.6);
    $out = chart_svg_open($w, $h, (string) ($opts['title'] ?? 'Chart'), (string) ($opts['id'] ?? ''));
    if ($axes) {
        $out .= '<line class="chart-guide" x1="' . $padL . '" y1="' . ($padT + $ih) . '" x2="' . ($w - $padR) . '" y2="' . ($padT + $ih) . '"/>';
        $out .= '<text class="chart-label" x="' . ($padL - 4) . '" y="' . ($padT + 4) . '" text-anchor="end">' . e(rtrim(rtrim(number_format($max, 1, '.', ''), '0'), '.')) . '</text>';
    }
    foreach (array_values($rows) as $i => $r) {
        $x0 = $padL + $i * $slot + ($slot - ($two ? $bw * 2 + 4 : $bw)) / 2;
        $v = (float) ($r['value'] ?? 0); $bh = $v / $max * $ih;
        $out .= '<rect class="chart-bar chart-series-1" x="' . round($x0, 1) . '" y="' . round($padT + $ih - $bh, 1) . '" width="' . round($bw, 1) . '" height="' . round($bh, 1) . '"><title>' . e((string) ($r['label'] ?? '') . ': ' . $v) . '</title></rect>';
        if ($two) {
            $v2 = (float) ($r['value2'] ?? 0); $bh2 = $v2 / $max * $ih;
            $out .= '<rect class="chart-bar chart-series-2" x="' . round($x0 + $bw + 4, 1) . '" y="' . round($padT + $ih - $bh2, 1) . '" width="' . round($bw, 1) . '" height="' . round($bh2, 1) . '"><title>' . e((string) ($r['label2'] ?? $r['label'] ?? '') . ': ' . $v2) . '</title></rect>';
        }
        if ($axes) {
            $out .= '<text class="chart-label" x="' . round($padL + $i * $slot + $slot / 2, 1) . '" y="' . ($h - 8) . '" text-anchor="middle">' . e(mb_substr((string) ($r['label'] ?? ''), 0, 12)) . '</text>';
        }
    }
    return $out . '</svg>';
}

/** Stacked areas per row (cumulative flow): rows[]['values'] in the order of opts.keys, bottom first. */
function chart_stacked(array $rows, array $opts = []): string
{
    $w = (int) ($opts['width'] ?? 640); $h = (int) ($opts['height'] ?? 240);
    $axes = !empty($opts['axes']);
    $padL = $axes ? 36 : 2; $padR = 8; $padT = 8; $padB = $axes ? 28 : 2;
    $n = count($rows);
    if ($n === 0) {
        return chart_svg_open($w, $h, (string) ($opts['title'] ?? 'No data'), (string) ($opts['id'] ?? '')) . '</svg>';
    }
    $keys = (array) ($opts['keys'] ?? []);
    $layers = $keys !== [] ? count($keys) : max(array_map(static fn ($r): int => count($r['values'] ?? []), $rows));
    $max = 0.0;
    foreach ($rows as $r) { $max = max($max, array_sum(array_map('floatval', $r['values'] ?? []))); }
    $max = $max <= 0 ? 1 : $max;
    $iw = $w - $padL - $padR; $ih = $h - $padT - $padB;
    $x = static fn (int $i): float => $padL + ($n === 1 ? $iw / 2 : $i * $iw / ($n - 1));
    $out = chart_svg_open($w, $h, (string) ($opts['title'] ?? 'Chart'), (string) ($opts['id'] ?? ''));
    $prev = array_fill(0, $n, 0.0);
    for ($l = 0; $l < $layers; $l++) {
        $top = []; $cum = [];
        foreach (array_values($rows) as $i => $r) {
            $cum[$i] = $prev[$i] + (float) (($r['values'] ?? [])[$l] ?? 0);
            $top[] = round($x($i), 1) . ',' . round($padT + $ih - $cum[$i] / $max * $ih, 1);
        }
        $bottom = [];
        for ($i = $n - 1; $i >= 0; $i--) { $bottom[] = round($x($i), 1) . ',' . round($padT + $ih - $prev[$i] / $max * $ih, 1); }
        $out .= '<polygon class="chart-area chart-series-' . ($l + 1) . '" points="' . implode(' ', array_merge($top, $bottom)) . '"><title>' . e((string) ($keys[$l] ?? ('Series ' . ($l + 1)))) . '</title></polygon>';
        $prev = $cum;
    }
    if ($axes) {
        $out .= '<line class="chart-guide" x1="' . $padL . '" y1="' . ($padT + $ih) . '" x2="' . ($w - $padR) . '" y2="' . ($padT + $ih) . '"/>';
        $out .= '<text class="chart-label" x="' . ($padL - 4) . '" y="' . ($padT + 4) . '" text-anchor="end">' . e(rtrim(rtrim(number_format($max, 1, '.', ''), '0'), '.')) . '</text>';
        foreach (array_values($rows) as $i => $r) {
            if ($i === 0 || $i === $n - 1 || $n <= 8 || $i % (int) ceil($n / 8) === 0) {
                $out .= '<text class="chart-label" x="' . round($x($i), 1) . '" y="' . ($h - 8) . '" text-anchor="middle">' . e(mb_substr((string) ($r['label'] ?? ''), 0, 12)) . '</text>';
            }
        }
    }
    return $out . '</svg>';
}
