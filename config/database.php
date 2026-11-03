<?php
declare(strict_types=1);
return [
    'driver' => 'mysql',
    'host' => \Prospak\Core\Config::get('DB_HOST', '127.0.0.1'),
    'database' => \Prospak\Core\Config::get('DB_DATABASE', 'prospak_academy'),
    'charset' => 'utf8mb4',
];
