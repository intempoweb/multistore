@php
    $desktopUrl = media_url($media?->desktop_path);
    $mobileUrl = media_url($media?->mobile_path);
    $posterUrl = media_url($media?->poster_path);
    $prefix = "blocks[{$blockIndex}][media][{$mediaIndex}]";
@endphp

<div class="border rounded-3 bg-white p-3" data-hero-media-row>
    @if($media?->id)
        <input type="hidden" name="{{ $prefix }}[id]" value="{{ $media->id }}">
    @endif
    <input type="hidden" name="{{ $prefix }}[desktop_path]" value="{{ $media?->desktop_path }}">
    <input type="hidden" name="{{ $prefix }}[mobile_path]" value="{{ $media?->mobile_path }}">
    <input type="hidden" name="{{ $prefix }}[poster_path]" value="{{ $media?->poster_path }}">

    <div class="row g-3">
        <div class="col-6 col-lg-3">
            <label class="form-label small fw-semibold">Formato media</label>
            <select name="{{ $prefix }}[media_type]" class="form-select form-select-sm">
                <option value="image" @selected(($media?->media_type ?? 'image') === 'image')>Immagine</option>
                <option value="video" @selected($media?->media_type === 'video')>Video</option>
            </select>
        </div>
        <div class="col-6 col-lg-3">
            <label class="form-label small fw-semibold">Posizione</label>
            <input type="number" name="{{ $prefix }}[sort_order]" class="form-control form-control-sm" min="0" value="{{ $media?->sort_order ?? $mediaIndex }}">
        </div>
        <div class="col-6 col-lg-3 d-flex align-items-end">
            <div class="form-check form-switch mb-1">
                <input type="hidden" name="{{ $prefix }}[is_active]" value="0">
                <input type="checkbox" name="{{ $prefix }}[is_active]" value="1" class="form-check-input" @checked($media?->is_active ?? true)>
                <label class="form-check-label small">Attivo</label>
            </div>
        </div>
        <div class="col-6 col-lg-3 d-flex align-items-end justify-content-end">
            @if($media?->id)
                <div class="form-check mb-1">
                    <input type="checkbox" name="{{ $prefix }}[delete]" value="1" class="form-check-input">
                    <label class="form-check-label small text-danger">Elimina</label>
                </div>
            @endif
        </div>
        <div class="col-12">
            <label class="form-label small fw-semibold">Testo alternativo per accessibilità e SEO</label>
            <input type="text" name="{{ $prefix }}[alt_text]" class="form-control form-control-sm" maxlength="255" value="{{ $media?->alt_text }}">
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label small fw-semibold">File desktop</label>
            @if($desktopUrl)
                <div class="small mb-2"><a href="{{ $desktopUrl }}" target="_blank">Visualizza attuale</a></div>
            @endif
            <input type="file" name="{{ $prefix }}[desktop_file]" class="form-control form-control-sm" accept="image/*,video/mp4,video/webm,video/quicktime">
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label small fw-semibold">Immagine mobile</label>
            @if($mobileUrl)
                <div class="small mb-2"><a href="{{ $mobileUrl }}" target="_blank">Visualizza attuale</a></div>
            @endif
            <input type="file" name="{{ $prefix }}[mobile_file]" class="form-control form-control-sm" accept="image/*">
        </div>
        <div class="col-12 col-lg-4">
            <label class="form-label small fw-semibold">Anteprima video</label>
            @if($posterUrl)
                <div class="small mb-2"><a href="{{ $posterUrl }}" target="_blank">Visualizza attuale</a></div>
            @endif
            <input type="file" name="{{ $prefix }}[poster_file]" class="form-control form-control-sm" accept="image/*">
        </div>
    </div>
</div>
