<?php
declare(strict_types=1);
namespace WordPress\GithubAiProvider\Metadata;
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
use WordPress\GithubAiProvider\Provider\GithubProvider;

/**
 * Class for the Github model metadata directory.
 *
 * @since 1.0.0
 */
class GithubModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
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
            GithubProvider::url($path),
            $headers,
            $data
        );
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function sendListModelsRequest(): array
    {
        // GitHub Models does not support standard /models endpoint at inference URL.
        // We bypass the API call and provide hardcoded models to prevent connection errors.
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

        $hardcodedModels = [
            'gpt-4o',
            'gpt-4o-mini',
            'meta-llama-3.1-405b-instruct',
            'meta-llama-3.1-70b-instruct',
            'meta-llama-3.1-8b-instruct',
            'AI21-Jamba-1.5-Large',
            'AI21-Jamba-1.5-Mini',
            'Cohere-command-r',
            'Cohere-command-r-plus',
            'Mistral-large',
            'Mistral-large-2407',
            'Mistral-Nemo',
            'Mistral-small'
        ];

        $modelMetadataMap = [];
        foreach ($hardcodedModels as $modelId) {
            $modelMetadataMap[$modelId] = new ModelMetadata(
                $modelId,
                $modelId,
                [CapabilityEnum::textGeneration(), CapabilityEnum::chatHistory()],
                $baseTextOptions
            );
        }

        return $modelMetadataMap;
    }

    /**
     * {@inheritDoc}
     *
     * @since 1.0.0
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        return [];
    }
}
