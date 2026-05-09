<?php
namespace App\Modules\AI;

use App\Contracts\AIDriverContract;
use Illuminate\Contracts\Container\Container;
use RuntimeException;

class AiDriverManager
{
    public function __construct(private Container $container) {}

    public function driver(?string $name = null): AIDriverContract
    {
        $name = $name ?? config('ai.default_driver', 'gemini');

        $driver = match ($name) {
            'gemini'    => $this->container->make(GeminiDriver::class),
            'qwen'      => $this->container->make(QwenDriver::class),
            'openai'    => $this->container->make(OpenAiDriver::class),
            'anthropic' => $this->container->make(AnthropicDriver::class),
            default     => throw new RuntimeException("Unknown AI driver [{$name}]."),
        };

        $apiKey = config("ai.drivers.{$name}.api_key", '');
        if (empty($apiKey)) {
            throw new RuntimeException("AI driver [{$name}] has no API key configured.");
        }

        return $driver;
    }
}
