<?php
declare(strict_types=1);
/**
 * PSR-4 autoloader for the AI Provider for Cohere package.
 *
 * @since 1.0.0
 *
 * @package WordPress\CohereAiProvider
 */


if (!defined('ABSPATH')) { exit; }


spl_autoload_register(static function (string $class): void {
    $prefix = 'WordPress\\CohereAiProvider\\';
    $baseDir = __DIR__ . '/';

    $len = strlen($prefix);

    if (strncmp($class, $prefix, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
