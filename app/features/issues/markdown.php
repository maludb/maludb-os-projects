<?php
declare(strict_types=1);
/**
 * A small, safe markdown renderer for descriptions and comments: paragraphs, headings (## and ###),
 * emphasis, inline code, fenced code, bullet and numbered lists, links (http/https only), and issue
 * keys (PROJ-12) as links. Everything is escaped first; no raw HTML ever passes.
 */
function markdown_to_html(string $text): string
{
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $out = [];
    $lines = explode("\n", $text);
    $i = 0;
    $n = count($lines);
    while ($i < $n) {
        $line = $lines[$i];
        if (preg_match('/^```/', $line)) {                       // fenced code
            $code = [];
            $i++;
            while ($i < $n && !preg_match('/^```/', $lines[$i])) { $code[] = $lines[$i]; $i++; }
            $i++;
            $out[] = '<pre class="bg-light p-2 rounded fs-12"><code>' . e(implode("\n", $code)) . '</code></pre>';
            continue;
        }
        if (preg_match('/^(#{2,3})\s+(.*)$/', $line, $m)) {
            $out[] = ($m[1] === '##' ? '<h6 class="fw-bold mt-3">' : '<div class="fw-semibold mt-2">') . markdown_inline($m[2]) . ($m[1] === '##' ? '</h6>' : '</div>');
            $i++;
            continue;
        }
        if (preg_match('/^\s*[-*]\s+/', $line)) {                // bullet list
            $items = [];
            while ($i < $n && preg_match('/^\s*[-*]\s+(.*)$/', $lines[$i], $m)) { $items[] = '<li>' . markdown_inline($m[1]) . '</li>'; $i++; }
            $out[] = '<ul class="mb-2">' . implode('', $items) . '</ul>';
            continue;
        }
        if (preg_match('/^\s*\d+[.)]\s+/', $line)) {             // numbered list
            $items = [];
            while ($i < $n && preg_match('/^\s*\d+[.)]\s+(.*)$/', $lines[$i], $m)) { $items[] = '<li>' . markdown_inline($m[1]) . '</li>'; $i++; }
            $out[] = '<ol class="mb-2">' . implode('', $items) . '</ol>';
            continue;
        }
        if (trim($line) === '') { $i++; continue; }
        $para = [];
        while ($i < $n && trim($lines[$i]) !== '' && !preg_match('/^(```|#{2,3}\s|\s*[-*]\s|\s*\d+[.)]\s)/', $lines[$i])) { $para[] = $lines[$i]; $i++; }
        $out[] = '<p>' . implode('<br>', array_map('markdown_inline', $para)) . '</p>';
    }
    return implode("\n", $out);
}

function markdown_inline(string $s): string
{
    $s = e($s);
    $s = preg_replace('/`([^`]+)`/', '<code>$1</code>', $s) ?? $s;
    $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s) ?? $s;
    $s = preg_replace('/(?<![\w*])\*([^*]+)\*(?![\w*])/', '<em>$1</em>', $s) ?? $s;
    $s = preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', static fn (array $m): string => '<a href="' . $m[2] . '" rel="noopener noreferrer" target="_blank">' . $m[1] . '</a>', $s) ?? $s;
    $s = preg_replace('/\b([A-Z][A-Z0-9]{1,9}-\d+)\b/', '<a href="/issues/$1" hx-get="/issues/$1" hx-target="#page-content" hx-push-url="/issues/$1">$1</a>', $s) ?? $s;
    return $s;
}
