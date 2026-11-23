<?php

declare(strict_types=1);

namespace Oeuvres\Alix;

function pageUrl(string $mode, string $base, string $suffix = ''): string
{
    $prefix = $mode === 'multi' ? '/' . rawurlencode($base) : '';
    return ($prefix . $suffix) ?: '/';
}

/** @return list<string> */
function docPrefixes(array $base): array
{
    $value = $base['docprefix'] ?? '';
    if (!is_string($value) || trim($value) === '') {
        return [];
    }
    return preg_split('/\s+/u', trim($value)) ?: [];
}

function matchesDocPrefix(string $doc, array $base): bool
{
    foreach (docPrefixes($base) as $prefix) {
        if (str_starts_with($doc, $prefix) && strlen($doc) > strlen($prefix)) {
            return true;
        }
    }
    return false;
}

function documentUrl(string $mode, string $baseKey, array $base, string $doc): string
{
    $path = (matchesDocPrefix($doc, $base) ? '/' : '/doc/') . rawurlencode($doc);
    return pageUrl($mode, $baseKey, $path);
}

/** Rewrite Alix's relative document links without modifying pagination links. */
function resultLinks(string $html, string $mode, string $baseKey, array $base): string
{
    return preg_replace_callback(
        '~(<a\b[^>]*?\bhref\s*=\s*)(["\'])(.*?)\2~is',
        static function (array $match) use ($mode, $baseKey, $base): string {
            $href = html_entity_decode($match[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($href === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|/|#|\?)~i', $href)) {
                return $match[0];
            }
            $length = strcspn($href, '?#');
            $doc = substr($href, 0, $length);
            if ($doc === '' || str_contains($doc, '/') || $doc === '.' || $doc === '..') {
                return $match[0];
            }
            $url = documentUrl($mode, $baseKey, $base, rawurldecode($doc)) . substr($href, $length);
            return $match[1] . $match[2]
                . htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
                . $match[2];
        },
        $html
    ) ?? $html;
}

function render(string $template, array $data): void
{
    $e = static fn (string $value): string => htmlspecialchars(
        $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
    $link = static fn (string $mode, string $base, string $suffix = ''): string =>
        pageUrl($mode, $base, $suffix);

    extract($data, EXTR_SKIP);

    ob_start();
    require __DIR__ . '/../templates/' . $template . '.php';
    $content = ob_get_clean();

    require __DIR__ . '/../templates/layout.php';
}
