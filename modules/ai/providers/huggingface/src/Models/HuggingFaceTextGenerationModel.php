<?php

declare(strict_types=1);

namespace WordPress\HuggingFaceAiProvider\Models;

use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\HuggingFaceAiProvider\Provider\HuggingFaceProvider;

/**
 * Class for a HuggingFace text generation model.
 *
 * @since 1.0.0
 */
class HuggingFaceTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
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
            HuggingFaceProvider::url($path),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
