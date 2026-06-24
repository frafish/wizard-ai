<?php

declare(strict_types=1);

namespace WordPress\OpenRouterAiProvider\Metadata;

use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;
use WordPress\OpenRouterAiProvider\Provider\OpenRouterProvider;

/**
 * Class for the OpenRouter model metadata directory.
 *
 * @since 1.0.0
 */
class OpenRouterModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
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
            OpenRouterProvider::url($path),
            $headers,
            $data
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        $responseData = $response->getData();
        if (!isset($responseData['data']) || empty($responseData['data'])) {
            throw ResponseException::fromMissingData('OpenRouter', 'data');
        }

        $baseTextOptions = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
        ];

        $modelsData = (array) $responseData['data'];

        $models = [];

        foreach ($modelsData as $modelData) {
            $modelId = $modelData['id'];
            
            $options = $baseTextOptions;
            
            // Check if tools are supported
            if (!isset($modelData['supported_parameters']) || (is_array($modelData['supported_parameters']) && in_array('tools', $modelData['supported_parameters'], true))) {
                $options[] = new SupportedOption(OptionEnum::functionDeclarations());
            }

            // Skip free models or image models if necessary, but OpenRouter returns all accessible models.
            // Optionally, skip if not text generation, but assume OpenRouter list is mostly text models.
            $models[] = new ModelMetadata(
                $modelId,
                $modelData['name'] ?? $modelId,
                [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
                $options
            );
        }

        return $models;
    }
}
