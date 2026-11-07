<?php

declare(strict_types=1);

// Espo's autoloader resolves Espo\, Espo\Custom\ and Espo\Modules\ (our code in custom/).
require __DIR__ . '/../vendor/autoload.php';

// Espo always runs with UTC as PHP's default timezone; code that parses dates without an offset relies on it.
date_default_timezone_set('UTC');

spl_autoload_register(static function (string $class): void {
    $prefix = 'Tests\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (is_file($file)) {
        require $file;
    }
});
