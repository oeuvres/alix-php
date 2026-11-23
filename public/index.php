<?php

declare(strict_types=1);

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Oeuvres\Alix\Alix;
use function FastRoute\simpleDispatcher;
use function Oeuvres\Alix\matchesDocPrefix;
use function Oeuvres\Alix\render;
use function Oeuvres\Alix\resultLinks;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/functions.php';

$config = require dirname(__DIR__) . '/config.php';
$mode = $config['mode'];
if (!in_array($mode, ['single', 'multi'], true)) {
    throw new LogicException('Invalid Alix mode');
}

$alix = new Alix($config['api']);
try {
    $bases = $alix->bases($config['cacheFile'], $config['cacheTtl']);
} catch (RuntimeException $exception) {
    error_log($exception->getMessage());
    http_response_code(502);
    render('error', ['title' => 'Unavailable', 'message' => 'Alix metadata is unavailable.']);
    return;
}

$router = simpleDispatcher(static function (RouteCollector $routes) use ($mode): void {
    if ($mode === 'multi') {
        $routes->get('/', 'bases');
        $routes->get('/{base}/q', 'q');
        $routes->get('/{base}/doc/{doc}', 'doc');
        $routes->get('/{base}', 'base');
        $routes->get('/{base}/{doc}', 'shortdoc');
    } else {
        $routes->get('/', 'base');
        $routes->get('/q', 'q');
        $routes->get('/doc/{doc}', 'doc');
        $routes->get('/{doc}', 'shortdoc');
    }
});

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$route = $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', rawurldecode($uri));
if ($route[0] !== Dispatcher::FOUND) {
    http_response_code($route[0] === Dispatcher::METHOD_NOT_ALLOWED ? 405 : 404);
    if ($route[0] === Dispatcher::METHOD_NOT_ALLOWED) {
        header('Allow: ' . implode(', ', $route[1]));
    }
    render('error', ['title' => 'Not found', 'message' => 'Page unavailable.']);
    return;
}

$page = $route[1];
$vars = $route[2];
$baseKey = $vars['base'] ?? $config['default'];

if ($page === 'bases') {
    render('bases', ['title' => 'Corpora', 'mode' => $mode, 'bases' => $bases]);
    return;
}

if (!isset($bases[$baseKey])) {
    http_response_code(404);
    render('error', ['title' => 'Not found', 'message' => 'Unknown corpus.']);
    return;
}

$base = $bases[$baseKey];
$data = [
    'title' => (string) ($base['label'] ?? $baseKey),
    'mode' => $mode,
    'baseKey' => $baseKey,
    'base' => $base,
];

if ($page === 'base') {
    render('base', $data);
    return;
}

if ($page === 'shortdoc' && !matchesDocPrefix($vars['doc'], $base)) {
    http_response_code(404);
    render('error', ['title' => 'Not found', 'message' => 'Unknown document.']);
    return;
}

// Only forward parameters used by the search and highlighting endpoints.
$params = [];
foreach (['q', 'from', 'docs', 'rows', 'sort', 'slop', 'ctx', 'start', 'end'] as $name) {
    if (isset($_GET[$name]) && is_string($_GET[$name])) {
        $params[$name] = $_GET[$name];
    }
}

if ($page === 'q' && trim($params['q'] ?? '') === '') {
    render('q', $data + ['q' => '', 'results' => null]);
    return;
}

try {
    $operation = $page === 'doc' || $page === 'shortdoc' ? $vars['doc'] : 'results';
    $response = $alix->html($baseKey, $operation, $params);
    if ($response['status'] !== 200) {
        http_response_code($response['status'] === 404 ? 404 : 502);
        render('error', [
            'title' => 'Unavailable',
            'message' => $response['status'] === 404 ? 'Document not found.' : 'Alix servlet error.',
        ]);
        return;
    }

    if ($page === 'doc' || $page === 'shortdoc') {
        render('doc', $data + ['doc' => $vars['doc'], 'fragment' => $response['body']]);
    } else {
        render('q', $data + [
            'q' => $params['q'],
            'results' => resultLinks($response['body'], $mode, $baseKey, $base),
        ]);
    }
} catch (RuntimeException $exception) {
    error_log($exception->getMessage());
    http_response_code(502);
    render('error', ['title' => 'Unavailable', 'message' => 'Alix servlet is unreachable.']);
}
