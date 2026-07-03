<?php

declare(strict_types=1);

namespace WordPress\GroqAiProvider\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\GroqAiProvider\Provider\GroqProvider;

/**
 * Class for a Groq text generation model.
 *
 * @since 1.0.0
 */
class GroqTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
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
        return new Request(
            $method,
            GroqProvider::url($path),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
