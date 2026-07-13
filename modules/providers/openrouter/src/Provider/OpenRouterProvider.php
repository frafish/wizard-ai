<?php
declare(strict_types=1);
namespace WordPress\OpenRouterAiProvider\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\OpenRouterAiProvider\Metadata\OpenRouterModelMetadataDirectory;
use WordPress\OpenRouterAiProvider\Models\OpenRouterTextGenerationModel;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;

class OpenRouterProvider extends AbstractApiProvider
{
    public static function url(string $path = ''): string
    {
        return 'https://openrouter.ai/api/v1/' . ltrim($path, '/');
    }

    protected static function baseUrl(): string
    {
        return 'https://openrouter.ai/api/v1';
    }

    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        return new OpenRouterTextGenerationModel($modelMetadata, $providerMetadata);
    }

    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'openrouter',
            'OpenRouter',
            ProviderTypeEnum::cloud(),
            'https://openrouter.ai/keys',
            RequestAuthenticationMethod::apiKey(),
            'A unified API for top LLMs',
            dirname(__DIR__, 2) . '/assets/images/openrouter.svg'
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
        return new OpenRouterModelMetadataDirectory();
    }
}