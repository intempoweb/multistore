<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupOrderProductImages extends Command
{
    protected $signature = 'order-product-images:cleanup
        {--dry : Mostra cosa verrebbe cancellato senza modificare S3 o DB}';

    protected $description = 'Cancella da S3 gli zip foto prodotto ordine scaduti e aggiorna il meta ordine';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $checked = 0;
        $deleted = 0;
        $missing = 0;
        $skipped = 0;

        Order::query()
            ->where('channel', 'b2b')
            ->whereNotNull('meta')
            ->orderBy('id')
            ->chunkById(200, function ($orders) use ($dry, &$checked, &$deleted, &$missing, &$skipped): void {
                foreach ($orders as $order) {
                    $archive = data_get($order->meta ?? [], 'mail.product_images_zip');

                    if (!is_array($archive) || filled(data_get($archive, 'deleted_at'))) {
                        $skipped++;
                        continue;
                    }

                    $checked++;

                    $disk = trim((string) data_get($archive, 'disk', 's3')) ?: 's3';
                    $path = ltrim((string) data_get($archive, 'path', ''), '/');
                    $expiresAt = $this->archiveExpiresAt($archive);

                    if ($path === '' || $expiresAt === null || $expiresAt->isFuture()) {
                        $skipped++;
                        continue;
                    }

                    $exists = Storage::disk($disk)->exists($path);

                    if (!$dry && $exists) {
                        Storage::disk($disk)->delete($path);
                    }

                    if (!$exists) {
                        $missing++;
                    } else {
                        $deleted++;
                    }

                    if (!$dry) {
                        $meta = $order->meta ?? [];
                        $meta = is_array($meta) ? $meta : (json_decode((string) $meta, true) ?: []);
                        data_set($meta, 'mail.product_images_zip.deleted_at', now()->toISOString());
                        data_set($meta, 'mail.product_images_zip.delete_reason', $exists ? 'expired' : 'missing_on_disk');

                        $order->forceFill(['meta' => $meta])->save();
                    }
                }
            });

        $this->info('Pulizia zip foto prodotti completata' . ($dry ? ' (dry-run)' : '') . '.');
        $this->line("Archivi controllati: {$checked}");
        $this->line("Archivi cancellati:  {$deleted}");
        $this->line("Archivi mancanti:    {$missing}");
        $this->line("Archivi skippati:    {$skipped}");

        return self::SUCCESS;
    }

    /**
     * @param array<string, mixed> $archive
     */
    private function archiveExpiresAt(array $archive): ?Carbon
    {
        try {
            $expiresAt = data_get($archive, 'expires_at');

            if (filled($expiresAt)) {
                return Carbon::parse($expiresAt);
            }

            $createdAt = data_get($archive, 'created_at');

            if (filled($createdAt)) {
                return Carbon::parse($createdAt)->addDays(
                    max(1, (int) config('mail.storefront.order_product_images.retention_days', 7))
                );
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }
}
