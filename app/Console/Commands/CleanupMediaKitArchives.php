<?php

namespace App\Console\Commands;

use App\Models\MediaKitRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

final class CleanupMediaKitArchives extends Command
{
    protected $signature = 'mediakit:cleanup
        {--limit=500}
        {--dry-run}
        {--local-tmp-only : Pulisce solo le cartelle locali storage/app/tmp/mediakit senza interrogare DB/storage remoto}
        {--tmp-retention-hours= : Ore di conservazione cartelle locali tmp. Default config mediakit.local_tmp_retention_hours}';
    protected $description = 'Cancella da storage gli archivi MediaKit scaricati/scaduti e i residui temporanei locali.';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $dryRun = (bool) $this->option('dry-run');

        if ((bool) $this->option('local-tmp-only')) {
            [$tmpDeleted, $tmpFailed, $tmpBytes] = $this->cleanupLocalTemporaryDirectories($dryRun);
            $this->info("MediaKit tmp cleanup: tmp_eliminati={$tmpDeleted}, tmp_errori={$tmpFailed}, tmp_bytes={$tmpBytes}.");

            return $tmpFailed === 0 ? self::SUCCESS : self::FAILURE;
        }

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

        [$tmpDeleted, $tmpFailed, $tmpBytes] = $this->cleanupLocalTemporaryDirectories($dryRun);

        $this->info(
            'MediaKit cleanup: '
            . "eliminati={$deleted}, errori={$failed}, analizzati={$requests->count()}, "
            . "tmp_eliminati={$tmpDeleted}, tmp_errori={$tmpFailed}, tmp_bytes={$tmpBytes}."
        );

        return $failed === 0 && $tmpFailed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return array{0:int,1:int,2:int}
     */
    private function cleanupLocalTemporaryDirectories(bool $dryRun): array
    {
        $root = storage_path('app/tmp/mediakit');

        if (!is_dir($root)) {
            return [0, 0, 0];
        }

        $retentionHours = $this->option('tmp-retention-hours');
        $retentionHours = $retentionHours !== null && $retentionHours !== ''
            ? (int) $retentionHours
            : (int) config('mediakit.local_tmp_retention_hours', 24);

        $cutoff = now()->subHours(max(0, $retentionHours))->getTimestamp();
        $deleted = 0;
        $failed = 0;
        $bytes = 0;

        foreach (File::directories($root) as $directory) {
            $mtime = @filemtime($directory);

            if ($mtime === false || $mtime > $cutoff) {
                continue;
            }

            $size = $this->directorySize($directory);

            if ($dryRun) {
                $this->line("[DRY] tmp {$directory} ({$size} bytes)");
                continue;
            }

            if (File::deleteDirectory($directory)) {
                $deleted++;
                $bytes += $size;
                continue;
            }

            $failed++;
            $this->error("tmp {$directory}: eliminazione non riuscita.");
        }

        return [$deleted, $failed, $bytes];
    }

    private function directorySize(string $directory): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $bytes = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $bytes += (int) $file->getSize();
            }
        }

        return $bytes;
    }
}
