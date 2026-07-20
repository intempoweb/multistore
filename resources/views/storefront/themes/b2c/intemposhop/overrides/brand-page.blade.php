@extends($storefrontLayout)

@php
    $pageTitle = trim((string) ($storefrontPage?->title ?? '')) ?: __('themes_b2c.intempo.about_us');
    $pageLead = trim((string) ($storefrontPage?->description ?? '')) ?: 'Lo stile italiano sempre attuale pensato per conquistare il gusto di tutti, in qualsiasi parte del mondo.';
    $fallbackSections = [
        [
            'name' => 'about_section_1',
            'title' => 'Artigianalità italiana, la nostra passione',
            'body' => "InTempo nasce come laboratorio artigianale di pelletteria, affermandosi nel corso degli anni come un brand di riferimento nella produzione di <strong>agende, diari, taccuini, accessori da scrivania e cartelle da lavoro di alta qualità</strong>. La nostra storia è radicata nella cultura del <strong>Made in Italy</strong>, dove ogni prodotto riflette l'eccellenza artigianale italiana.",
        ],
        [
            'name' => 'about_section_2',
            'title' => 'Innovazione, qualità e sostenibilità',
            'body' => "Ogni creazione InTempo racchiude un perfetto equilibrio tra la tradizione artigiana e l'innovazione tecnologica. Scegliamo con cura <strong>materiali pregiati</strong>, lavorati con metodi tradizionali, integrati con tecnologie avanzate per garantire la <strong>massima funzionalità</strong> e un <strong>design ricercato</strong>. InTempo si distingue per il controllo completo sulla <strong>filiera produttiva</strong>, con un impegno costante verso la <strong>qualità e la sostenibilità</strong>. Utilizziamo <strong>materiali certificati</strong> e <strong>processi ecosostenibili, minimizzando l’impatto ambientale</strong> senza mai rinunciare allo stile.",
        ],
        [
            'name' => 'about_section_3',
            'title' => 'Un marchio internazionale',
            'body' => "Le collezioni InTempo si caratterizzano per un design elegante e funzionale, capace di adattarsi a diverse esigenze e personalità. Grazie alla nostra presenza in oltre 25 Paesi, siamo riconosciuti e apprezzati a livello internazionale per la nostra <strong>dedizione all’etica del lavoro, alla qualità e all'innovazione</strong>. Visita i nostri punti vendita per scoprire lo stile InTempo che ti rappresenta meglio, un connubio di tradizione, eccellenza e responsabilità verso il futuro.",
        ],
    ];

    $blocksByName = collect($storefrontPageBlocks ?? [])->keyBy(fn ($block) => (string) $block->name);
    $formatAboutBody = static function (?string $body): string {
        $escaped = e((string) $body);

        return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
    };

    $aboutSections = collect($fallbackSections)->map(function (array $fallback) use ($blocksByName, $formatAboutBody) {
        $block = $blocksByName->get($fallback['name']);
        $content = trim((string) ($block?->content ?? ''));

        return [
            'title' => trim((string) ($block?->title ?? '')) ?: $fallback['title'],
            'body' => $content !== '' ? $formatAboutBody($content) : $fallback['body'],
        ];
    })->values();
@endphp

@section('title', $storefrontPage?->meta_title ?: $pageTitle)
@section('meta_description', $storefrontPage?->meta_description ?: $pageLead)

@section('content')
<section class="intempo-b2c-about-page" aria-labelledby="intempo-b2c-about-title">
    <header class="intempo-b2c-about-hero">
        <div class="intempo-b2c-shell">
            <p class="intempo-b2c-eyebrow">{{ __('themes_b2c.intempo.about_us') }}</p>
            <h1 id="intempo-b2c-about-title">{{ $pageTitle }}</h1>
            <p>{{ $pageLead }}</p>
        </div>
    </header>

    <div class="intempo-b2c-about-sections intempo-b2c-shell">
        @foreach($aboutSections as $index => $section)
            <article class="intempo-b2c-about-section">
                <div class="intempo-b2c-about-section-media" aria-hidden="true">
                    <span>{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</span>
                </div>
                <div class="intempo-b2c-about-section-copy">
                    <h2>{{ $section['title'] }}</h2>
                    <p>{!! $section['body'] !!}</p>
                </div>
            </article>
        @endforeach
    </div>

    <div class="intempo-b2c-about-actions intempo-b2c-shell">
        <a class="intempo-b2c-primary-link" href="{{ route('storefront.store-locator.index', $contextParams ?? []) }}">
            {{ __('themes_b2c.intempo.find_store') }}
            <i data-lucide="arrow-right" aria-hidden="true"></i>
        </a>
        <a class="intempo-b2c-secondary-link" href="{{ route('storefront.catalog.index', $contextParams ?? []) }}">
            {{ __('themes_b2c.intempo.discover_collection') }}
        </a>
    </div>
</section>
@endsection
