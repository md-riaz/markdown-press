<?php

namespace App\Modules\Shortcode;

use App\Contracts\ShortcodeHandlerContract;
use InvalidArgumentException;

class ShortcodeRegistry
{
    /** @var array<string, ShortcodeHandlerContract> */
    private array $handlers = [];

    public function register(ShortcodeHandlerContract $handler): void
    {
        $this->handlers[$handler->name()] = $handler;
    }

    public function has(string $name): bool
    {
        return isset($this->handlers[$name]);
    }

    public function get(string $name): ShortcodeHandlerContract
    {
        if (!$this->has($name)) {
            throw new InvalidArgumentException("No handler registered for shortcode [{$name}].");
        }

        return $this->handlers[$name];
    }

    /** @return string[] */
    public function registeredNames(): array
    {
        return array_keys($this->handlers);
    }
}
