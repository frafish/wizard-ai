<?php
declare(strict_types=1);
namespace WordPress\HuggingFaceAiProvider\Provider;

use Exception;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\Http\Contracts\RequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithRequestAuthenticationInterface;
use WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Contracts\WithHttpTransporterInterface;
use WordPress\AiClient\Providers\Http\Traits\WithRequestAuthenticationTrait;
use WordPress\AiClient\Providers\Http\Traits\WithHttpTransporterTrait;

class HuggingFaceProviderAvailability implements ProviderAvailabilityInterface, WithRequestAuthenticationInterface, WithHttpTransporterInterface
{
    use WithRequestAuthenticationTrait;
    use WithHttpTransporterTrait;
    private ModelMetadataDirectoryInterface $directory;

    public function __construct(ModelMetadataDirectoryInterface $directory) {
        $this->directory = $directory;
    }

    public function setRequestAuthentication(RequestAuthenticationInterface $requestAuthentication): void {
        $this->requestAuthentication = $requestAuthentication;
        if ($this->directory instanceof WithRequestAuthenticationInterface) {
            $this->directory->setRequestAuthentication($requestAuthentication);
        }
    }

    public function setHttpTransporter(HttpTransporterInterface $httpTransporter): void {
        $this->httpTransporter = $httpTransporter;
        if ($this->directory instanceof WithHttpTransporterInterface) {
            $this->directory->setHttpTransporter($httpTransporter);
        }
    }

    public function isAvailable(): bool { return true; }
    public function getError(): ?\Throwable { return null; }

    public function isConfigured(): bool {
        try {
            $this->directory->listModelMetadata();
            return true;
        } catch (Exception $e) {
            error_log("HuggingFace isConfigured failed: " . $e->getMessage());
            return false;
        }
    }
}
