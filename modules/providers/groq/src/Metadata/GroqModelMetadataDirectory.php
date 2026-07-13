<?php
declare(strict_types=1);
namespace WordPress\GroqAiProvider\Metadata;
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
use WordPress\GroqAiProvider\Provider\GroqProvider;

/**
 * Class for the Groq model metadata directory.
 *
 * @since 1.0.0
 */
class GroqModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
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
            throw ResponseException::fromMissingData('Groq', 'data');
        }

        $baseTextOptions = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::temperature()),
            new SupportedOption(OptionEnum::topP()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::functionDeclarations()),
        ];

        $modelsData = (array) $responseData['data'];

        $models = [];

        foreach ($modelsData as $modelData) {
            $modelId = $modelData['id'];

            // Skip whisper models as they are for audio transcription, not text generation.
            if (str_contains($modelId, 'whisper')) {
                continue;
            }

            $options = $baseTextOptions;
            
            $inputModalities = [ModalityEnum::text()];
            if (strpos($modelId, 'vision') !== false || strpos($modelId, 'llama-3.2-11b-vision') !== false || strpos($modelId, 'llama-3.2-90b-vision') !== false) {
                $inputModalities[] = ModalityEnum::image();
            }
            $options[] = new SupportedOption(OptionEnum::inputModalities(), [$inputModalities]);

            $models[] = new ModelMetadata(
                $modelId,
                $modelId, // Groq uses the ID as the name.
                [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
                $options
            );
        }

        return $models;
    }
}
