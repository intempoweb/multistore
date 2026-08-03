@if(!empty($marketingPopup))
    <div
        class="storefront-popup @if(empty($marketingPopup['image_url'])) storefront-popup--text-only @endif"
        data-storefront-popup
        data-popup-key="{{ $marketingPopup['key'] }}"
        data-popup-frequency="{{ $marketingPopup['frequency'] }}"
        data-popup-delay="{{ $marketingPopup['delay_ms'] }}"
        style="
            --storefront-popup-bg: {{ $marketingPopup['background_color'] }};
            --storefront-popup-color: {{ $marketingPopup['text_color'] }};
            --storefront-popup-button-bg: {{ $marketingPopup['button_background_color'] }};
            --storefront-popup-button-color: {{ $marketingPopup['button_text_color'] }};
        "
        hidden
    >
        <div class="storefront-popup__overlay" data-storefront-popup-close></div>

        <section class="storefront-popup__dialog" role="dialog" aria-modal="true" aria-labelledby="storefront-popup-title">
            <button class="storefront-popup__close" type="button" data-storefront-popup-close aria-label="{{ __('Chiudi') }}">
                <span aria-hidden="true">&times;</span>
            </button>

            @if(!empty($marketingPopup['image_url']))
                <div class="storefront-popup__media">
                    <img src="{{ $marketingPopup['image_url'] }}" alt="{{ $marketingPopup['title_text'] ?: '' }}" loading="lazy">
                </div>
            @endif

            <div class="storefront-popup__content">
                @if(!empty($marketingPopup['subtitle_html']))
                    <p class="storefront-popup__eyebrow">{!! $marketingPopup['subtitle_html'] !!}</p>
                @endif

                @if(!empty($marketingPopup['title_html']))
                    <h2 id="storefront-popup-title">{!! $marketingPopup['title_html'] !!}</h2>
                @endif

                @if(!empty($marketingPopup['body_html']))
                    <div class="storefront-popup__body">{!! $marketingPopup['body_html'] !!}</div>
                @endif

                @if(!empty($marketingPopup['cta_url']) && !empty($marketingPopup['cta_label']))
                    <a
                        class="storefront-popup__cta"
                        href="{{ $marketingPopup['cta_url'] }}"
                        @if($marketingPopup['open_in_new_tab']) target="_blank" rel="noopener noreferrer" @endif
                        data-storefront-popup-cta
                    >
                        {{ $marketingPopup['cta_label_text'] }}
                    </a>
                @endif

                <label class="storefront-popup__optout">
                    <input type="checkbox" data-storefront-popup-optout>
                    <span>{{ __('Non mostrare piu questo avviso') }}</span>
                </label>
            </div>
        </section>
    </div>
@endif
