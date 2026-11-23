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
     * Fetches a trusted HTML fragment from the Alix servlet.
     *
     * @return array{status: int, body: string}
     */
    public function html(string $index, string $operation, array $params = []): array
    {
        $url = rtrim($this->api, '/')
            . '/' . rawurlencode($index)
            . '/' . rawurlencode($operation) . '.html';

        if ($params !== []) {
            $url .= '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "Accept: text/html\r\n",
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
}
