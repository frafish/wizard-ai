<?php

declare(strict_types=1);

namespace WordPress\OpenRouterAiProvider\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider;

/**
 * Class for an OpenRouter text generation model.
 *
 * @since 1.0.0
 */
class OpenRouterTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        // OpenRouter recommends setting HTTP-Referer and X-Title headers.
        // We will add some default ones for WordPress AI Client.
        $headers = array_merge([
            'HTTP-Referer' => home_url(),
            'X-Title' => 'WordPress AI Client'
        ], $headers);

        return new Request(
            $method,
            OpenRouterProvider::url($path),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function parseResponseChoiceToCandidate(array $choiceData, int $index): \WordPress\AiClient\Results\DTO\Candidate
    {
        if (isset($choiceData['finish_reason'])) {
            $reason = $choiceData['finish_reason'];
            // Normalize non-standard finish reasons returned by OpenRouter
            if ($reason === 'error' || $reason === 'end_turn' || $reason === 'stop_sequence') {
                $choiceData['finish_reason'] = 'stop';
            } elseif ($reason === 'tool_use') {
                $choiceData['finish_reason'] = 'tool_calls';
            } elseif ($reason === 'max_tokens') {
                $choiceData['finish_reason'] = 'length';
            } elseif ($reason === null) {
                $choiceData['finish_reason'] = 'stop';
            }
        }
        
        return parent::parseResponseChoiceToCandidate($choiceData, $index);
    }
}
