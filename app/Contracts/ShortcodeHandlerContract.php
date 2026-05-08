<?php

namespace App\Contracts;

interface ShortcodeHandlerContract
{
    public function name(): string;

    /**
     * @param  array<string, string>  $attributes
     */
    public function render(array $attributes, ?string $content): string;
}
