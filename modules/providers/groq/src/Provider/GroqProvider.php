<?php
declare(strict_types=1);
namespace WordPress\GroqAiProvider\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\GroqAiProvider\Metadata\GroqModelMetadataDirectory;
use WordPress\GroqAiProvider\Models\GroqTextGenerationModel;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;

class GroqProvider extends AbstractApiProvider
{
    public static function url(string $path = ''): string
    {
        return 'https://api.groq.com/openai/v1/' . ltrim($path, '/');
    }

    protected static function baseUrl(): string
    {
        return 'https://api.groq.com/openai/v1';
    }

    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        return new GroqTextGenerationModel($modelMetadata, $providerMetadata);
    }

    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'groq',
            'Groq',
            ProviderTypeEnum::cloud(),
            'https://console.groq.com/keys',
            RequestAuthenticationMethod::apiKey(),
            'Fast AI Inference with Groq LPU',
            dirname(__DIR__, 2) . '/assets/images/groq.svg'
        );
    }

    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new ListModelsApiBasedProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new GroqModelMetadataDirectory();
    }
}