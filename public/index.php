<?php

declare(strict_types=1);

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Oeuvres\Alix\Alix;
use function FastRoute\simpleDispatcher;
use function Oeuvres\Alix\render;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/src/functions.php';

$config = require dirname(__DIR__) . '/config.php';
$mode = $config['mode'];
$bases = $config['bases'];

if (!in_array($mode, ['single', 'multi'], true) || !isset($bases[$config['default']])) {
    throw new LogicException('Invalid Alix configuration');
}

$defaultPattern = $bases[$config['default']]['docPattern'];
$router = simpleDispatcher(static function (RouteCollector $routes) use ($mode, $defaultPattern): void {
    if ($mode === 'multi') {
        $routes->get('/', 'bases');
        $routes->get('/{base}/q', 'q');
        $routes->get('/{base}', 'base');
        $routes->get('/{base}/{doc:[^/]+}', 'doc');
    } else {
        $routes->get('/', 'base');
        $routes->get('/q', 'q');
        $routes->get('/{doc:' . $defaultPattern . '}', 'doc');
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
    'title' => $base['title'],
    'mode' => $mode,
    'baseKey' => $baseKey,
    'base' => $base,
];

if ($page === 'base') {
    render('base', $data);
    return;
}

if ($page === 'doc') {
    $doc = $vars['doc'];
    $pattern = '~\A(?:' . $base['docPattern'] . ')\z~D';
    if (!preg_match($pattern, $doc)) {
        http_response_code(404);
        render('error', ['title' => 'Not found', 'message' => 'Unknown document.']);
        return;
    }
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
    $alix = new Alix($config['api']);
    $operation = $page === 'doc' ? $doc : 'results';
    $response = $alix->html($base['index'], $operation, $params);

    if ($response['status'] !== 200) {
        http_response_code($response['status'] === 404 ? 404 : 502);
        render('error', [
            'title' => 'Unavailable',
            'message' => $response['status'] === 404 ? 'Document not found.' : 'Alix servlet error.',
        ]);
        return;
    }

    if ($page === 'doc') {
        render('doc', $data + ['doc' => $doc, 'fragment' => $response['body']]);
    } else {
        render('q', $data + ['q' => $params['q'], 'results' => $response['body']]);
    }
} catch (RuntimeException $exception) {
    error_log($exception->getMessage());
    http_response_code(502);
    render('error', ['title' => 'Unavailable', 'message' => 'Alix servlet is unreachable.']);
}
