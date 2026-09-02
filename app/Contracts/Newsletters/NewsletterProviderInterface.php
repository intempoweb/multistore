<?php

namespace App\Contracts\Newsletters;

use App\Models\Newsletter;

interface NewsletterProviderInterface
{
    public function key(): string;

    public function test(array $settings): array;

    public function createDraft(Newsletter $newsletter, string $html, array $settings): array;

    public function updateDraft(Newsletter $newsletter, string $html, array $settings): array;
}
