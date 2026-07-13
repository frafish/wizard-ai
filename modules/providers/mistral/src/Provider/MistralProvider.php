<?php
declare(strict_types=1);
namespace WordPress\MistralAiProvider\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\MistralAiProvider\Metadata\MistralModelMetadataDirectory;
use WordPress\MistralAiProvider\Models\MistralTextGenerationModel;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;

class MistralProvider extends AbstractApiProvider
{
    public static function url(string $path = ''): string
    {
        return 'https://api.mistral.ai/v1/' . ltrim($path, '/');
    }

    protected static function baseUrl(): string
    {
        return 'https://api.mistral.ai/v1';
    }

    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        return new MistralTextGenerationModel($modelMetadata, $providerMetadata);
    }

    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'mistral',
            'Mistral',
            ProviderTypeEnum::cloud(),
            'https://console.mistral.ai/api-keys/',
            RequestAuthenticationMethod::apiKey(),
            'Frontier AI models by Mistral AI',
            dirname(__DIR__, 2) . '/assets/images/mistral.svg'
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
        return new MistralModelMetadataDirectory();
    }
}