<?php

declare(strict_types=1);

namespace Oeuvres\Alix;

function pageUrl(string $mode, string $base, string $suffix = ''): string
{
    $prefix = $mode === 'multi' ? '/' . rawurlencode($base) : '';
    return ($prefix . $suffix) ?: '/';
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
