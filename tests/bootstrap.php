<?php

declare(strict_types=1);

require dirname(__DIR__, 4) . '/vendor/autoload.php';
require dirname(__DIR__) . '/vendor/autoload.php';

$_SERVER['PROJECT_ROOT'] = $_SERVER['PROJECT_ROOT'] ?? dirname(__DIR__, 4);
$_ENV['PROJECT_ROOT'] = $_ENV['PROJECT_ROOT'] ?? dirname(__DIR__, 4);
$_SERVER['APP_ENV'] = $_SERVER['APP_ENV'] ?? 'test';
$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'test';
$_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? '1';
$_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? '1';
$_SERVER['APP_SECRET'] = $_SERVER['APP_SECRET'] ?? 'testsecret';
$_ENV['APP_SECRET'] = $_ENV['APP_SECRET'] ?? 'testsecret';
$_SERVER['LOCK_DSN'] = $_SERVER['LOCK_DSN'] ?? 'flock';
$_ENV['LOCK_DSN'] = $_ENV['LOCK_DSN'] ?? 'flock';

require dirname(__DIR__, 4) . '/vendor/shopware/core/TestBootstrap.php';
