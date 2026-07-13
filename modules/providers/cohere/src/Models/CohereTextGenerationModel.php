<?php
declare(strict_types=1);
namespace WordPress\CohereAiProvider\Models;
if ( ! defined( 'ABSPATH' ) ) exit;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;
use WordPress\CohereAiProvider\Provider\CohereProvider;

/**
 * Class for a Cohere text generation model.
 *
 * @since 1.0.0
 */
class CohereTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
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
            CohereProvider::url($path),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
