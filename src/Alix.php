<?php

declare(strict_types=1);

namespace Oeuvres\Alix;

use RuntimeException;

final class Alix
{
    public function __construct(private readonly string $api)
    {
    }

    /**
     * Returns the servlet's public corpus registry, cached on disk.
     * Stale metadata is used if a refresh fails.
     *
     * @return array<string, array<string, mixed>>
     */
    public function bases(string $cacheFile, int $ttl = 300): array
    {
        $cached = self::readCache($cacheFile);
        if ($cached !== null && self::fresh($cacheFile, $ttl)) {
            return $cached;
        }

        $directory = dirname($cacheFile);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Cannot create Alix metadata cache directory');
        }

        $lock = fopen($cacheFile . '.lock', 'c');
        if ($lock === false) {
            throw new RuntimeException('Cannot lock Alix metadata cache');
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new RuntimeException('Cannot lock Alix metadata cache');
            }

            // Another PHP request may already have refreshed the file.
            $cached = self::readCache($cacheFile);
            if ($cached !== null && self::fresh($cacheFile, $ttl)) {
                return $cached;
            }

            try {
                $response = $this->get('', 'application/json');
                if ($response['status'] !== 200) {
                    throw new RuntimeException('Alix metadata HTTP ' . $response['status']);
                }

                $bases = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
                if (!is_array($bases) || ($bases !== [] && array_is_list($bases))) {
                    throw new RuntimeException('Invalid Alix metadata registry');
                }
                foreach ($bases as $key => $base) {
                    if (!is_string($key) || !is_array($base)) {
                        throw new RuntimeException('Invalid Alix corpus metadata');
                    }
                }

                $temporary = tempnam($directory, '.bases-');
                if ($temporary === false) {
                    throw new RuntimeException('Cannot write Alix metadata cache');
                }
                try {
                    if (file_put_contents($temporary, $response['body']) === false ||
                        !rename($temporary, $cacheFile)) {
                        throw new RuntimeException('Cannot update Alix metadata cache');
                    }
                } finally {
                    if (is_file($temporary)) {
                        unlink($temporary);
                    }
                }
                return $bases;
            } catch (\Throwable $error) {
                if ($cached !== null) {
                    return $cached;
                }
                throw new RuntimeException('Cannot load Alix metadata', 0, $error);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /**
     * Fetches a trusted HTML fragment from the Alix servlet.
     *
     * @return array{status: int, body: string}
     */
    public function html(string $index, string $operation, array $params = []): array
    {
        $path = rawurlencode($index) . '/' . rawurlencode($operation) . '.html';
        if ($params !== []) {
            $path .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }
        return $this->get($path, 'text/html');
    }

    /**
     * @return array{status: int, body: string}
     */
    private function get(string $path, string $accept): array
    {
        $url = rtrim($this->api, '/') . '/' . $path;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: $accept\r\n",
                'timeout' => 10,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Alix servlet is unreachable');
        }
        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('~^HTTP/\S+\s+(\d{3})~', $statusLine, $matches)) {
            throw new RuntimeException('Invalid Alix servlet response');
        }
        return ['status' => (int) $matches[1], 'body' => $body];
    }

    /** @return array<string, array<string, mixed>>|null */
    private static function readCache(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }
        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }
        $data = json_decode($content, true);
        return is_array($data) && !array_is_list($data) ? $data : null;
    }

    private static function fresh(string $file, int $ttl): bool
    {
        clearstatcache(true, $file);
        $modified = @filemtime($file);
        return $modified !== false && $modified + $ttl > time();
    }
}
