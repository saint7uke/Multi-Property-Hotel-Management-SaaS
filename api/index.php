<?php

declare(strict_types=1);

if (getenv('VERCEL')) {
    $runtimePath = '/tmp/laravel';

    foreach ([$runtimePath, "$runtimePath/views", "$runtimePath/cache", "$runtimePath/sessions"] as $directory) {
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
    }

    $runtimeEnvironment = [
        'APP_CONFIG_CACHE' => "$runtimePath/config.php",
        'APP_EVENTS_CACHE' => "$runtimePath/events.php",
        'APP_PACKAGES_CACHE' => "$runtimePath/packages.php",
        'APP_ROUTES_CACHE' => "$runtimePath/routes.php",
        'APP_SERVICES_CACHE' => "$runtimePath/services.php",
        'VIEW_COMPILED_PATH' => "$runtimePath/views",
        'HOME' => '/tmp',
    ];

    foreach ($runtimeEnvironment as $key => $value) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

require __DIR__ . '/../public/index.php';
