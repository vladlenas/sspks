<?php

declare(strict_types=1);

// Minimal PSR-4 autoloader for the SSpkS namespace. The server has no
// runtime dependencies, so Composer is not needed to run it.
spl_autoload_register(static function (string $class): void {
    $prefix = 'SSpkS\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});
