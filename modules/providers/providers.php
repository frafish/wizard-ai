<?php
namespace WizardAi\Modules\Providers;

if (!defined('ABSPATH')) {
    exit;
}

class Providers {

    public function __construct() {
        add_action('init', [$this, 'register_subplugins_providers'], 5);
    }

    public function register_subplugins_providers() {
        if (!class_exists('\WordPress\AiClient\AiClient')) {
            return;
        }

        if (file_exists(WIZARD_AI_PATH . 'modules/providers/groq/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/groq/src/autoload.php';
        }
        if (file_exists(WIZARD_AI_PATH . 'modules/providers/openrouter/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/openrouter/src/autoload.php';
        }
        if (file_exists(WIZARD_AI_PATH . 'modules/providers/huggingface/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/huggingface/src/autoload.php';
        }
        if (file_exists(WIZARD_AI_PATH . 'modules/providers/github/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/github/src/autoload.php';
        }
        if (file_exists(WIZARD_AI_PATH . 'modules/providers/mistral/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/mistral/src/autoload.php';
        }
        if (file_exists(WIZARD_AI_PATH . 'modules/providers/cohere/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/cohere/src/autoload.php';
        }
        if (file_exists(WIZARD_AI_PATH . 'modules/providers/ollama/src/autoload.php')) {
            require_once WIZARD_AI_PATH . 'modules/providers/ollama/src/autoload.php';
        }

        $registry = \WordPress\AiClient\AiClient::defaultRegistry();

        if (class_exists('\WordPress\GroqAiProvider\Provider\GroqProvider') && !$registry->hasProvider('\WordPress\GroqAiProvider\Provider\GroqProvider')) {
            $registry->registerProvider('\WordPress\GroqAiProvider\Provider\GroqProvider');
        }
        if (class_exists('\WordPress\HuggingFaceAiProvider\Provider\HuggingFaceProvider') && !$registry->hasProvider('\WordPress\HuggingFaceAiProvider\Provider\HuggingFaceProvider')) {
            $registry->registerProvider('\WordPress\HuggingFaceAiProvider\Provider\HuggingFaceProvider');
        }
        if (class_exists('\WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider') && !$registry->hasProvider('\WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider')) {
            $registry->registerProvider('\WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider');
        }
        if (class_exists('\WordPress\GithubAiProvider\Provider\GithubProvider') && !$registry->hasProvider('\WordPress\GithubAiProvider\Provider\GithubProvider')) {
            $registry->registerProvider('\WordPress\GithubAiProvider\Provider\GithubProvider');
        }
        if (class_exists('\WordPress\MistralAiProvider\Provider\MistralProvider') && !$registry->hasProvider('\WordPress\MistralAiProvider\Provider\MistralProvider')) {
            $registry->registerProvider('\WordPress\MistralAiProvider\Provider\MistralProvider');
        }
        if (class_exists('\WordPress\CohereAiProvider\Provider\CohereProvider') && !$registry->hasProvider('\WordPress\CohereAiProvider\Provider\CohereProvider')) {
            $registry->registerProvider('\WordPress\CohereAiProvider\Provider\CohereProvider');
        }
        // Ollama Provider integration has been delegated to the official ai-provider-for-ollama plugin
        // to avoid conflicts and simplify configuration for the user.
    }
}
