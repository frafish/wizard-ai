<?php
declare(strict_types=1);
namespace WordPress\GithubAiProvider\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\GithubAiProvider\Metadata\GithubModelMetadataDirectory;
use WordPress\GithubAiProvider\Models\GithubTextGenerationModel;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;

class GithubProvider extends AbstractApiProvider
{
    public static function url(string $path = ''): string
    {
        return 'https://models.inference.ai.azure.com/' . ltrim($path, '/');
    }

    protected static function baseUrl(): string
    {
        return 'https://models.inference.ai.azure.com';
    }

    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        return new GithubTextGenerationModel($modelMetadata, $providerMetadata);
    }

    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'github',
            'GitHub',
            ProviderTypeEnum::cloud(),
            'https://github.com/settings/tokens',
            RequestAuthenticationMethod::apiKey(),
            'GitHub Models Inference API',
            dirname(__DIR__, 2) . '/assets/images/github.svg'
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
        return new GithubModelMetadataDirectory();
    }
}