<?php

namespace App\Services\MediaKit\Selection;

use App\Models\MediaKitRequest;
use App\Services\MediaKit\MediaKitContext;
use App\Services\MediaKit\MediaKitSelection;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

final class UploadedDdtSelectionResolver implements SelectionResolver
{
    public function __construct(
        private readonly CatalogSelectionResolver $catalog,
    ) {
    }

    public function supports(string $sourceType): bool
    {
        return $sourceType === MediaKitRequest::SOURCE_UPLOADED_DDT;
    }

    public function resolve(MediaKitRequest $request, MediaKitContext $context): MediaKitSelection
    {
        if (!$request->input_disk || !$request->input_path) {
            throw new RuntimeException('File DDT non configurato nella richiesta MediaKit.');
        }

        $disk = Storage::disk($request->input_disk);

        if (!$disk->exists($request->input_path)) {
            throw new RuntimeException('File DDT non trovato.');
        }

        $tmpDir = storage_path('app/tmp/mediakit-inputs');

        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Impossibile creare la cartella temporanea MediaKit.');
        }

        $extension = pathinfo($request->input_path, PATHINFO_EXTENSION) ?: 'xlsx';
        $tmpPath = $tmpDir . '/' . $request->uuid . '.' . $extension;

        $stream = $disk->readStream($request->input_path);

        if (!is_resource($stream)) {
            throw new RuntimeException('Impossibile leggere il file DDT.');
        }

        $target = fopen($tmpPath, 'wb');

        if (!is_resource($target)) {
            fclose($stream);
            throw new RuntimeException('Impossibile creare il file DDT temporaneo.');
        }

        stream_copy_to_stream($stream, $target);
        fclose($stream);
        fclose($target);

        try {
            $spreadsheet = IOFactory::load($tmpPath);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
            $skus = $this->extractSkus($rows);

            $meta = $request->meta ?? [];
            $meta['skus'] = $skus;
            $request->forceFill(['meta' => $meta])->save();

            $catalogRequest = clone $request;
            $catalogRequest->source_type = MediaKitRequest::SOURCE_CATALOG;

            $selection = $this->catalog->resolve($catalogRequest, $context);

            return new MediaKitSelection(
                $selection->products,
                MediaKitRequest::SOURCE_UPLOADED_DDT,
                $request->source_reference ?: basename($request->input_path),
                $selection->warnings,
            );
        } finally {
            @unlink($tmpPath);
        }
    }

    /**
     * Cerca automaticamente una colonna SKU/codice articolo.
     *
     * @param array<int, array<int, mixed>> $rows
     * @return array<int, string>
     */
    private function extractSkus(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $headers = array_map(
            fn ($value) => mb_strtoupper(trim((string) $value)),
            array_shift($rows)
        );

        $preferred = [
            'SKU',
            'CODART_MG66',
            'CODICE ARTICOLO',
            'CODICE_ARTICOLO',
            'COD ARTICOLO',
            'ARTICOLO',
        ];

        $column = null;

        foreach ($preferred as $candidate) {
            $index = array_search($candidate, $headers, true);

            if ($index !== false) {
                $column = (int) $index;
                break;
            }
        }

        if ($column === null) {
            $column = 0;
        }

        return collect($rows)
            ->map(fn (array $row) => trim((string) ($row[$column] ?? '')))
            ->filter()
            ->reject(fn (string $value) => mb_strtoupper($value) === 'SKU')
            ->unique()
            ->values()
            ->all();
    }
}
