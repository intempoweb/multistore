<?php

namespace App\Console\Commands;

use App\Models\Newsletter;
use App\Services\Newsletters\NewsletterRenderer;
use Illuminate\Console\Command;

class NewsletterPreviewCommand extends Command
{
    protected $signature = 'newsletter:preview {newsletter : ID newsletter}';

    protected $description = 'Renderizza HTML newsletter su stdout';

    public function handle(NewsletterRenderer $renderer): int
    {
        $newsletter = Newsletter::query()->with(['store', 'products'])->findOrFail((int) $this->argument('newsletter'));

        $this->line($renderer->render($newsletter));

        return self::SUCCESS;
    }
}
