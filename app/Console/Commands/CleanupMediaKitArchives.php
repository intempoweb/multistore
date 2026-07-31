<?php

namespace App\Console\Commands;

use App\Models\MediaKitRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class CleanupMediaKitArchives extends Command
{
    protected $signature = 'mediakit:cleanup {--limit=500} {--dry-run}';
    protected $description = 'Cancella da storage gli archivi MediaKit scaricati o scaduti.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        $requests = MediaKitRequest::query()
            ->dueForCleanup()
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $deleted = 0;
        $failed = 0;

        foreach ($requests as $request) {
            $reason = $request->downloaded_at ? 'downloaded' : 'expired';

            if ($dryRun) {
                $this->line("[DRY] {$request->uuid} {$request->output_disk}:{$request->output_path} ({$reason})");
                continue;
            }

            try {
                $disk = Storage::disk((string) $request->output_disk);
                $exists = $disk->exists((string) $request->output_path);

                if ($exists && !$disk->delete((string) $request->output_path)) {
                    throw new \RuntimeException('Storage delete ha restituito false.');
                }

                if ($request->input_disk && $request->input_path) {
                    $inputDisk = Storage::disk((string) $request->input_disk);

                    if ($inputDisk->exists((string) $request->input_path)) {
                        $inputDisk->delete((string) $request->input_path);
                    }
                }

                $request->forceFill([
                    'status' => MediaKitRequest::STATUS_DELETED,
                    'deleted_at' => now(),
                    'delete_reason' => $reason,
                ])->save();

                $deleted++;
            } catch (Throwable $e) {
                $failed++;
                $this->error("{$request->uuid}: {$e->getMessage()}");
            }
        }

        $this->info("MediaKit cleanup: eliminati={$deleted}, errori={$failed}, analizzati={$requests->count()}.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
