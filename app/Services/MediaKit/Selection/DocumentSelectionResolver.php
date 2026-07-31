<?php

namespace App\Services\MediaKit\Selection;

use App\Models\Erp\DocumentHeader;
use App\Models\MediaKitRequest;
use App\Models\Product;
use App\Services\MediaKit\MediaKitContext;
use App\Services\MediaKit\MediaKitSelection;
use App\Services\Storefront\Documents\DocumentProductResolver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class DocumentSelectionResolver implements SelectionResolver
{
    public function __construct(
        private readonly DocumentProductResolver $documents,
    ) {
    }

    public function supports(string $sourceType): bool
    {
        return $sourceType === MediaKitRequest::SOURCE_DOCUMENT;
    }

    public function resolve(MediaKitRequest $request, MediaKitContext $context): MediaKitSelection
    {
        if (!$request->source_reference) {
            throw new RuntimeException('Numero registro documento mancante.');
        }

        try {
            /*
             * Le opzioni devono essere impostate anche qui: l'anteprima e il job
             * MediaKit eseguono nuove query ERP rispetto all'elenco documenti.
             */
            $erp = DB::connection('erp');
            $erp->statement('SET ANSI_NULLS ON');
            $erp->statement('SET ANSI_WARNINGS ON');

            $document = DocumentHeader::query()
                ->where('DITTA_CG18', $context->ditta())
                ->where('NUMREG_CO99', $request->source_reference)
                ->first();

            if (!$document) {
                throw new RuntimeException('Documento ERP non trovato.');
            }

            /*
             * Anche il caricamento delle righe deve avvenire sulla stessa
             * connessione/sessione già configurata con ANSI_NULLS/WARNINGS.
             */
            $document->loadMissing('rows');

            $this->documents->attachProducts($document, $context->store);

            $products = collect($document->rows ?? [])
                ->map(fn ($row) => method_exists($row, 'attachedProduct') ? $row->attachedProduct() : null)
                ->filter(fn ($product) => $product instanceof Product)
                ->unique(fn (Product $product) => (int) $product->getKey())
                ->values();

            return new MediaKitSelection(
                $products,
                $request->source_type,
                $request->source_reference,
                $products->isEmpty()
                    ? ['Nessun prodotto locale associato alle righe del documento.']
                    : [],
            );
        } catch (QueryException $e) {
            report($e);

            throw new RuntimeException(
                'Impossibile leggere il documento ERP. Verifica il collegamento SQL e riprova.',
                previous: $e,
            );
        } catch (RuntimeException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException(
                'Errore durante la lettura del documento ERP.',
                previous: $e,
            );
        }
    }
}
