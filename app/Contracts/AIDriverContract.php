<?php

namespace App\Contracts;

interface AIDriverContract
{
    public function summarize(string $markdown): string;

    public function excerpt(string $markdown, int $words = 50): string;

    public function translate(string $markdown, string $targetLocale, string $sourceLocale = 'en'): string;
}
