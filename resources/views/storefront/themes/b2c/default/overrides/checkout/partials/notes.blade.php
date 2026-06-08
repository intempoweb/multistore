<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h5 class="mb-3">Note ordine</h5>

        <textarea
            name="notes"
            rows="8"
            form="checkout-place-form"
            class="form-control @error('notes') is-invalid @enderror"
            placeholder="Inserisci eventuali note per l'ordine"
        >{{ old('notes', $cart->notes) }}</textarea>

        @error('notes')
            <div class="invalid-feedback d-block">{{ $message }}</div>
        @enderror
    </div>
</div>