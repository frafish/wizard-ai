<?php
declare(strict_types=1);
namespace WordPress\HuggingFaceAiProvider\Provider;

use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\HuggingFaceAiProvider\Metadata\HuggingFaceModelMetadataDirectory;
use WordPress\HuggingFaceAiProvider\Models\HuggingFaceTextGenerationModel;
use WordPress\HuggingFaceAiProvider\Provider\HuggingFaceProviderAvailability;

require_once __DIR__ . '/HuggingFaceProviderAvailability.php';

class HuggingFaceProvider extends AbstractApiProvider
{
    public static function url(string $path = ''): string
    {
        return 'https://router.huggingface.co/v1/' . ltrim($path, '/');
    }

    protected static function baseUrl(): string
    {
        return 'https://router.huggingface.co/v1';
    }

    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        return new HuggingFaceTextGenerationModel($modelMetadata, $providerMetadata);
    }

    protected static function createProviderMetadata(): ProviderMetadata
    {
        return new ProviderMetadata(
            'huggingface',
            'HuggingFace',
            ProviderTypeEnum::cloud(),
            'https://huggingface.co/settings/tokens',
            RequestAuthenticationMethod::apiKey(),
            'HuggingFace Inference API',
            dirname(__DIR__, 2) . '/assets/images/huggingface.svg'
        );
    }

    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        return new HuggingFaceProviderAvailability(
            static::modelMetadataDirectory()
        );
    }

    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new HuggingFaceModelMetadataDirectory();
    }
}