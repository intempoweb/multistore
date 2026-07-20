<?php

namespace App\Services\Storefront\Content;

use App\Models\StorefrontPage;
use App\Models\StorefrontPageBlock;

class StaticPageEditorSchema
{
    /**
     * @return array<string, mixed>
     */
    public function page(StorefrontPage $page): array
    {
        $slug = $page->getRawOriginal('slug') ?: $page->slug;

        return [
            'title' => match ($slug) {
                'home' => 'Homepage',
                'login' => 'Pagina accesso clienti',
                'about' => 'Chi siamo',
                'vision' => 'Vision',
                default => $page->title ?: 'Pagina statica',
            },
            'description' => match ($slug) {
                'home' => 'Aggiorna testi, immagini e SEO della homepage senza modificare il layout.',
                'login' => 'Aggiorna immagini e testi della pagina di accesso clienti.',
                'about', 'vision' => 'Aggiorna il testo editoriale, l\'immagine principale e i dati SEO.',
                default => 'Aggiorna contenuti e SEO della pagina statica.',
            },
            'content_label' => match ($slug) {
                'about', 'vision' => 'Testo principale della pagina',
                default => 'Descrizione editoriale',
            },
            'content_help' => match ($slug) {
                'about', 'vision' => 'Puoi usare paragrafi separati da una riga vuota. Il layout resta quello previsto dal sito.',
                default => 'Testo usato dai template che leggono la descrizione della pagina.',
            },
            'show_sort_order' => false,
            'allow_slug_edit' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function block(StorefrontPageBlock $block): array
    {
        $type = (string) $block->type;
        $name = (string) $block->name;
        $base = [
            'label' => $this->label($block),
            'help' => $this->help($block),
            'editable' => true,
            'fields' => [
                'title' => true,
                'subtitle' => true,
                'content' => true,
                'image' => true,
                'mobile_image' => true,
                'image_alt' => true,
                'video' => false,
                'button' => true,
                'specs' => false,
                'media_gallery' => false,
                'active' => true,
            ],
            'labels' => [
                'title' => 'Titolo',
                'subtitle' => 'Sottotitolo',
                'content' => 'Testo',
                'image' => 'Immagine principale',
                'mobile_image' => 'Immagine mobile',
                'image_alt' => 'Testo alternativo immagine',
                'button_label' => 'Testo del bottone',
                'button_url' => 'Link del bottone',
                'specs' => 'Tag / caratteristiche',
            ],
            'media_help' => 'Formato consigliato: JPG/WebP, immagine nitida e coerente con la sezione.',
            'fallback_image' => null,
            'fallback_image_label' => null,
        ];

        if (in_array($type, ['section_intro'], true)) {
            $base['fields']['image'] = false;
            $base['fields']['mobile_image'] = false;
            $base['fields']['image_alt'] = false;
            $base['fields']['button'] = filled($block->button_label) || filled($block->button_url);
        }

        if (in_array($type, ['about_highlight', 'vision_highlight', 'value'], true)) {
            $base['fields']['subtitle'] = false;
            $base['fields']['image'] = false;
            $base['fields']['mobile_image'] = false;
            $base['fields']['image_alt'] = false;
            $base['fields']['button'] = false;
            $base['labels']['content'] = 'Descrizione breve';
        }

        if (in_array($name, ['home_about', 'home_vision'], true)) {
            $base['fallback_image'] = 'images/themes/b2c/ciak/formats/taccuino-puntini-color.png';
            $base['fallback_image_label'] = 'Immagine attuale del tema';
        }

        if (str_starts_with($name, 'about_section_')) {
            $base['fields']['subtitle'] = false;
            $base['fields']['image'] = false;
            $base['fields']['mobile_image'] = false;
            $base['fields']['image_alt'] = false;
            $base['fields']['button'] = false;
            $base['labels']['content'] = 'Testo sezione';
        }

        if (in_array($type, ['hero', 'gallery', 'instagram_gallery'], true) || in_array($name, ['home_hero', 'home_instagram', 'instagram'], true)) {
            $base['fields']['media_gallery'] = true;
            $base['fields']['video'] = true;
            $base['labels']['image'] = 'Immagine di copertina';
            $base['media_help'] = 'Consigliato: immagine orizzontale ampia. Per hero e gallery compila sempre il testo alternativo.';
        }

        if ($type === 'instagram_gallery' || in_array($name, ['home_instagram', 'instagram'], true)) {
            $base['editable'] = false;
            $base['fields']['title'] = false;
            $base['fields']['subtitle'] = false;
            $base['fields']['content'] = false;
            $base['fields']['image'] = false;
            $base['fields']['mobile_image'] = false;
            $base['fields']['image_alt'] = false;
            $base['fields']['video'] = false;
            $base['fields']['button'] = false;
            $base['fields']['media_gallery'] = false;
            $base['fields']['active'] = false;
            $base['help'] = 'Feed automatico collegato a Instagram: immagini e contenuti non si modificano da questo editor.';
        }

        if ($type === 'format') {
            $base['labels']['subtitle'] = 'Famiglia prodotto';
            $base['labels']['content'] = 'Descrizione';
            $base['fields']['specs'] = true;
            $base['media_help'] = 'Carica una nuova immagine solo se vuoi sostituire quella del tema per questo formato.';
        }

        if ($type === 'brand_grid') {
            $base['fields']['subtitle'] = false;
            $base['fields']['content'] = false;
            $base['fields']['button'] = false;
            $base['fields']['mobile_image'] = false;
            $base['labels']['title'] = 'Nome immagine';
            $base['labels']['image'] = 'Immagine';
            $base['media_help'] = 'Immagine decorativa della pagina di accesso. Evita testi importanti dentro l’immagine.';
        }

        return $base;
    }

    private function label(StorefrontPageBlock $block): string
    {
        $name = (string) $block->name;

        return match (true) {
            $name === 'home_hero' => 'Hero principale',
            $name === 'home_about_intro' => 'Introduzione Chi siamo & Vision',
            $name === 'home_about' => 'Sezione Chi siamo',
            str_starts_with($name, 'home_about_highlight') => 'Punto chiave Chi siamo',
            str_starts_with($name, 'about_section_') => 'Sezione About',
            $name === 'home_vision' => 'Sezione Vision',
            str_starts_with($name, 'home_vision_highlight') => 'Punto chiave Vision',
            $name === 'home_values_intro' => 'Titolo valori',
            str_starts_with($name, 'home_value') => 'Valore',
            $name === 'home_featured_intro' => 'Titolo prodotti in evidenza',
            $name === 'home_formats_intro' => 'Introduzione formati',
            str_starts_with($name, 'home_format') => 'Scheda formato',
            $name === 'home_story' => 'Racconto editoriale',
            $name === 'home_banner' => 'Banner editoriale',
            $name === 'home_instagram' => 'Sezione Instagram',
            str_starts_with($name, 'login_background') => 'Immagine accesso clienti',
            default => $block->title ?: 'Sezione contenuto',
        };
    }

    private function help(StorefrontPageBlock $block): string
    {
        return match ((string) $block->type) {
            'hero' => 'Prima area visibile della pagina. Usa testi brevi e immagini di forte impatto.',
            'section_intro' => 'Testo introduttivo che prepara la sezione successiva.',
            'about', 'vision', 'editorial', 'editorial_banner' => 'Sezione editoriale con testo, immagine e possibile bottone.',
            'about_highlight', 'vision_highlight', 'value' => 'Card breve: titolo e descrizione devono restare sintetici.',
            'format' => 'Scheda dedicata a un formato prodotto. Modifica solo testi e immagine, non la logica catalogo.',
            'instagram_gallery' => 'Area social/editoriale. Il layout resta gestito dal tema.',
            'brand_grid' => 'Immagine usata come sfondo o supporto visuale nella pagina di accesso.',
            default => 'Aggiorna solo i contenuti previsti per questa sezione.',
        };
    }
}
