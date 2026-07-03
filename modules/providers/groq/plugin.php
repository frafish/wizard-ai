<?php

/**
 * Plugin Name: AI Provider for Groq
 * Plugin URI: https://github.com/WordPress/ai-provider-for-groq
 * Description: AI Provider for Groq for the WordPress AI Client.
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Version: 1.0.0
 * Author: WordPress AI Team
 * Author URI: https://make.wordpress.org/ai/
 * License: GPL-2.0-or-later
 * License URI: https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain: ai-provider-for-groq
 *
 * @package WordPress\GroqAiProvider
 */

declare(strict_types=1);

namespace WordPress\GroqAiProvider;

use WordPress\AiClient\AiClient;
use WordPress\GroqAiProvider\Provider\GroqProvider;

if (!defined('ABSPATH')) {
    return;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Registers the AI Provider for Groq with the AI Client.
 *
 * @since 1.0.0
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(GroqProvider::class)) {
        return;
    }

    $registry->registerProvider(GroqProvider::class);
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);
