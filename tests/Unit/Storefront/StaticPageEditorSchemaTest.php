<?php

namespace Tests\Unit\Storefront;

use App\Models\StorefrontPage;
use App\Models\StorefrontPageBlock;
use App\Services\Storefront\Content\StaticPageEditorSchema;
use PHPUnit\Framework\TestCase;

class StaticPageEditorSchemaTest extends TestCase
{
    public function test_it_describes_static_pages_for_editorial_users(): void
    {
        $schema = new StaticPageEditorSchema();
        $page = new StorefrontPage([
            'slug' => 'about',
            'title' => 'Chi siamo',
        ]);

        $definition = $schema->page($page);

        $this->assertSame('Chi siamo', $definition['title']);
        $this->assertSame('Testo principale della pagina', $definition['content_label']);
        $this->assertFalse($definition['show_sort_order']);
    }

    public function test_it_hides_technical_fields_for_highlight_blocks(): void
    {
        $schema = new StaticPageEditorSchema();
        $block = new StorefrontPageBlock([
            'type' => 'value',
            'name' => 'home_value_1',
            'title' => 'Laboratorio',
        ]);

        $definition = $schema->block($block);

        $this->assertSame('Valore', $definition['label']);
        $this->assertFalse($definition['fields']['subtitle']);
        $this->assertFalse($definition['fields']['button']);
        $this->assertFalse($definition['fields']['image_alt']);
        $this->assertSame('Descrizione breve', $definition['labels']['content']);
    }

    public function test_it_guides_image_alt_for_visual_sections(): void
    {
        $schema = new StaticPageEditorSchema();
        $block = new StorefrontPageBlock([
            'type' => 'hero',
            'name' => 'home_hero',
            'title' => 'Hero',
        ]);

        $definition = $schema->block($block);

        $this->assertTrue($definition['fields']['image_alt']);
        $this->assertTrue($definition['fields']['media_gallery']);
        $this->assertSame('Immagine di copertina', $definition['labels']['image']);
    }
}
