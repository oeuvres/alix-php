<?php

declare(strict_types=1);

return [
    'api' => 'http://127.0.0.1:8080/alix',
    'mode' => 'multi', // 'single' or 'multi'
    'default' => 'rougemont',
    'bases' => [
        'rougemont' => [
            'index' => 'ddr',
            'title' => 'Denis de Rougemont',
            'docPattern' => 'ddr[^/]+',
        ],
        'piaget' => [
            'index' => 'piaget',
            'title' => 'Jean Piaget',
            'docPattern' => '[^/]+', // Replace with the actual document ID pattern.
        ],
    ],
];
