<?php
declare(strict_types=1);
namespace WordPress\HuggingFaceAiProvider\Metadata;
if ( ! defined( 'ABSPATH' ) ) exit;
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
use WordPress\HuggingFaceAiProvider\Provider\HuggingFaceProvider;

/**
 * Class for the HuggingFace model metadata directory.
 *
 * @since 1.0.0
 */
class HuggingFaceModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
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
            throw ResponseException::fromMissingData('HuggingFace', 'data');
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
            new SupportedOption(OptionEnum::functionDeclarations()),
        ];

        $modelsData = (array) $responseData['data'];
        $models = [];
        $hasEmbeddings = false;

        foreach ($modelsData as $modelData) {
            $modelId = $modelData['id'];

            // Some models might be embeddings
            $capabilities = [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()];
            if (strpos(strtolower($modelId), 'embed') !== false || strpos(strtolower($modelId), 'minilm') !== false) {
                $capabilities = [CapabilityEnum::embeddingGeneration()];
                $hasEmbeddings = true;
            }

            $models[] = new ModelMetadata(
                $modelId,
                $modelData['name'] ?? $modelId,
                $capabilities,
                $capabilities[0]->isTextGeneration() ? $baseTextOptions : []
            );
        }

        if (!$hasEmbeddings) {
            $models[] = new ModelMetadata(
                'all-MiniLM-L6-v2',
                'all-MiniLM-L6-v2',
                [CapabilityEnum::embeddingGeneration()],
                []
            );
        }

        return $models;
    }
}
