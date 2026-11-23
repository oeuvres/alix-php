<?php

declare(strict_types=1);

return [
    'api' => 'http://localhost:8888/alix',
    'mode' => 'multi', // 'single' or 'multi'
    'default' => 'rougemont',
    'cacheFile' => __DIR__ . '/var/cache/bases.json',
    'cacheTtl' => 300,
];
