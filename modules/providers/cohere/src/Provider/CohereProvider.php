<?php
declare(strict_types=1);
namespace WordPress\CohereAiProvider\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\CohereAiProvider\Metadata\CohereModelMetadataDirectory;
use WordPress\CohereAiProvider\Models\CohereTextGenerationModel;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;

class CohereProvider extends AbstractApiProvider
{
    public static function url(string $path = ''): string
    {
        return 'https://api.cohere.ai/v1/' . ltrim($path, '/');
    }

    protected static function baseUrl(): string
    {
        return 'https://api.cohere.ai/v1';
    }

    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        return new CohereTextGenerationModel($modelMetadata, $providerMetadata);
    }

    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'cohere',
            'Cohere',
            ProviderTypeEnum::cloud(),
            'https://dashboard.cohere.com/api-keys',
            RequestAuthenticationMethod::apiKey(),
            'Cohere Command models',
            dirname(__DIR__, 2) . '/assets/images/cohere.svg'
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
        return new CohereModelMetadataDirectory();
    }
}