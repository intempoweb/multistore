<form
    method="POST"
    action="{{ route('admin.shipping-rules.import.store') }}"
    enctype="multipart/form-data"
    class="row g-3 align-items-end"
>
    @csrf

    @php
        $shareableStores = collect($shareableStores ?? []);
        $sharedStoreIds = collect(old('shared_store_ids', $sharedStoreIds ?? []))
            ->map(fn ($id) => (int) $id)
            ->all();
    @endphp

    @isset($store)
        @if(($store->is_b2b ?? false) === false)
            <div class="col-12">
                <div class="alert alert-info mb-0">
                    Import dedicato ai siti <strong>B2C</strong>: verranno create regole di tipo <strong>table</strong>
                    basate su <strong>peso e destinazione</strong> (nazione / provincia / CAP).
                </div>
            </div>
        @else
            <div class="col-12">
                <div class="alert alert-warning mb-0">
                    Questo store è <strong>B2B</strong>: normalmente non si usa il CSV table rate.
                </div>
            </div>
        @endif
    @endisset

    <div class="col-12 col-lg-8">
        <label for="csv_file" class="form-label">File CSV</label>
        <input
            type="file"
            name="file"
            id="csv_file"
            class="form-control @error('file') is-invalid @enderror"
            accept=".csv,text/csv"
            required
        >
        @error('file')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12 col-lg-4">
        <div class="form-check mb-3">
            <input
                class="form-check-input"
                type="checkbox"
                name="replace_existing"
                id="replace_existing"
                value="1"
                {{ old('replace_existing') ? 'checked' : '' }}
            >
            <label class="form-check-label" for="replace_existing">
                Sostituisci regole esistenti (solo questo store / site)
            </label>
        </div>

        <button type="submit" class="btn btn-outline-primary w-100">
            <i class="fa-solid fa-file-import me-1"></i>
            Importa CSV
        </button>
    </div>

    @if(($store->is_b2b ?? false) === false && $shareableStores->isNotEmpty())
        <div class="col-12">
            <div class="border rounded p-3">
                <div class="fw-semibold mb-2">Applica questo listino anche ad altri siti B2C</div>
                <div class="small text-muted mb-3">
                    Dopo l'import il listino table rate dello store corrente verrà associato anche ai siti selezionati.
                </div>

                <div class="row g-3">
                    @foreach($shareableStores as $shareableStore)
                        <div class="col-12 col-lg-6">
                            <label class="border rounded p-3 d-flex align-items-start gap-3 w-100">
                                <input
                                    class="form-check-input mt-1"
                                    type="checkbox"
                                    name="shared_store_ids[]"
                                    value="{{ $shareableStore->id }}"
                                    {{ in_array((int) $shareableStore->id, $sharedStoreIds, true) ? 'checked' : '' }}
                                >

                                <span>
                                    <span class="fw-semibold d-block">{{ $shareableStore->name }}</span>
                                    <span class="small text-muted d-block">
                                        Ditta {{ $shareableStore->ditta_cg18 }} • Site {{ $shareableStore->erp_site_code }}
                                    </span>
                                    @if(!empty($shareableStore->domain))
                                        <span class="small text-muted d-block">{{ $shareableStore->domain }}</span>
                                    @endif
                                </span>
                            </label>
                        </div>
                    @endforeach
                </div>

                @error('shared_store_ids')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
                @error('shared_store_ids.*')
                    <div class="text-danger small mt-2">{{ $message }}</div>
                @enderror
            </div>
        </div>
    @endif

    <div class="col-12">
        <div class="small text-muted">
            Lo store/site viene preso automaticamente dal contesto admin corrente.<br>
            Verranno create righe di tipo <code>table</code>.<br><br>

            <strong>Formato CSV richiesto:</strong><br>
            <code>Nazione,Provincia,CAP,Peso (e superiore),Prezzo di spedizione</code><br>
            <span>oppure</span><br>
            <code>Nazione;Provincia;CAP;Peso (e superiore);Prezzo di spedizione</code><br><br>

            <strong>Esempio:</strong><br>
            <code>IT,MI,201*,0,5.900</code><br>
            <code>IT,MI,201*,5,7.900</code><br><br>

            <span>
                Sono accettati sia <strong>,</strong> sia <strong>;</strong> come separatori.<br>
                I valori <code>*</code> / <code>ALL</code> vengono trattati come wildcard.
            </span>
        </div>
    </div>
</form>