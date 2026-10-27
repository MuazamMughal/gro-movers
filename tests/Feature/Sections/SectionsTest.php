<?php

namespace Tests\Feature\Sections;

use App\Models\Area;
use App\Models\Faq;
use App\Models\Media;
use App\Models\MoveType;
use App\Models\Page;
use App\Models\Review;
use App\Models\Section;
use App\Models\Service;
use App\Sections\SectionRegistry;
use App\Sections\SectionRenderer;
use App\Sections\SectionSync;
use App\Services\Media\MediaService;
use App\Services\Media\MediaUsage;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SectionsTest extends TestCase
{
    use RefreshDatabase;

    private SectionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Storage::fake('public');
        $this->registry = app(SectionRegistry::class);
    }

    public static function types(): array
    {
        // Data providers run before the app boots, so read the config file directly.
        $types = array_keys((require dirname(__DIR__, 3).'/config/sections.php')['types']);

        return collect($types)->mapWithKeys(fn ($t) => [$t => [$t]])->all();
    }

    #[DataProvider('types')]
    public function test_every_type_renders_with_defaults_and_with_full_sample_data(string $type)
    {
        $page = Page::factory()->create();
        $image = $this->image();
        Service::factory()->count(2)->create();
        Area::factory()->count(2)->create();
        Review::factory()->count(3)->create(['is_featured' => true]);
        $faq = Faq::factory()->create();
        $page->faqs()->attach($faq, ['sort' => 0]);
        MoveType::factory()->create(['name' => 'Local Move']);

        $page->sections()->create(['type' => $type, 'data' => $this->registry->defaults($type), 'sort' => 0]);
        $sample = $this->sample($this->registry->definition($type)['fields'], $image->id);
        $page->sections()->create(['type' => $type, 'data' => $this->registry->sanitize($type, $sample), 'sort' => 1]);

        // Full sample data must pass validation as well.
        $validator = Validator::make(['sections' => [['type' => $type, 'data' => $sample]]], $this->registry->rules([['type' => $type]]));
        $this->assertTrue($validator->passes(), json_encode($validator->errors()->all()));

        $html = app(SectionRenderer::class)->render($page)->html;

        $this->assertNotSame('', trim($html), "{$type} rendered nothing");
        $this->assertStringNotContainsString('{company}', $html);
    }

    public function test_only_the_first_visible_hero_is_the_h1()
    {
        $page = Page::factory()->create();
        $page->sections()->create(['type' => 'hero', 'data' => ['heading' => 'Hidden'], 'sort' => 0, 'is_visible' => false]);
        $page->sections()->create(['type' => 'hero', 'data' => ['heading' => 'First'], 'sort' => 1]);
        $page->sections()->create(['type' => 'hero', 'data' => ['heading' => 'Second'], 'sort' => 2]);
        $page->sections()->create(['type' => 'rich_text', 'data' => ['heading' => 'More', 'content' => '<p>x</p>'], 'sort' => 3]);

        $rendered = app(SectionRenderer::class)->render($page);

        $this->assertTrue($rendered->hasH1);
        $this->assertSame(1, substr_count($rendered->html, '<h1'));
        $this->assertMatchesRegularExpression('#<h1[^>]*>First</h1>#', $rendered->html);
        $this->assertStringNotContainsString('Hidden', $rendered->html);
        $this->assertMatchesRegularExpression('#<h2[^>]*>Second</h2>#', $rendered->html);

        $noHero = Page::factory()->create();
        $noHero->sections()->create(['type' => 'rich_text', 'data' => ['heading' => 'Intro', 'content' => '<p>x</p>'], 'sort' => 0]);
        $this->assertFalse(app(SectionRenderer::class)->render($noHero)->hasH1);
    }

    public function test_tokens_are_replaced_and_escaped_in_rich_text()
    {
        $page = Page::factory()->create();
        $area = Area::factory()->create(['city' => 'Plano<b>', 'state_code' => 'TX']);
        $service = Service::factory()->create(['title' => 'Packing']);
        $page->sections()->create(['type' => 'rich_text', 'data' => ['heading' => '{service} in {city}, {state_code}', 'content' => '<p>Call {company} at {phone} in {city}. {unknown}</p>'], 'sort' => 0]);

        $html = app(SectionRenderer::class)->render($page, area: $area, service: $service)->html;

        $this->assertStringContainsString('Packing in Plano&lt;b&gt;, TX', $html);
        $this->assertStringContainsString('Call Grosolve Moving Network at (214) 555-0100 in Plano&lt;b&gt;.', $html);
        $this->assertStringContainsString('{unknown}', $html, 'unknown tokens stay visible');
    }

    public function test_validation_rules()
    {
        $rules = fn (array $sections) => Validator::make(['sections' => $sections], $this->registry->rules($sections), [], $this->registry->attributes($sections));

        $v = $rules([['type' => 'hero', 'data' => ['heading' => '']]]);
        $this->assertTrue($v->fails());
        $this->assertSame('The Hero › Heading field is required.', $v->errors()->first('sections.0.data.heading'));

        $this->assertTrue($rules([['type' => 'hero', 'data' => ['heading' => 'Hi', 'primary_button' => ['label' => 'Go', 'url' => 'javascript:alert(1)']]]])->fails());
        $this->assertTrue($rules([['type' => 'hero', 'data' => ['heading' => 'Hi', 'primary_button' => ['label' => 'Call', 'url' => 'tel:+12145550100']]]])->passes());
        $this->assertTrue($rules([['type' => 'cards_grid', 'data' => ['cards' => [['title' => 'A', 'icon' => 'not-an-icon']]]]])->fails());
        $this->assertTrue($rules([['type' => 'cards_grid', 'data' => ['cards' => []]]])->fails(), 'min one card');
        $this->assertTrue($rules([['type' => 'nope', 'data' => []]])->fails());
        $this->assertTrue($rules([['type' => 'hero', 'data' => ['heading' => 'x', 'bullets' => array_fill(0, 6, ['text' => 'a'])]]])->fails(), 'max 5 bullets');
    }

    public function test_sanitize_purifies_rich_text_drops_unknown_keys_and_empty_rows()
    {
        $clean = $this->registry->sanitize('image_text', [
            'heading' => '  Packing  ',
            'content' => '<p onclick="x()">Hi <script>alert(1)</script><a href="javascript:x">bad</a> <a href="/ok">ok</a></p>',
            'bullets' => [['text' => 'One'], ['text' => '  '], []],
            'evil' => 'drop me',
            'image' => '7',
        ]);

        $this->assertSame('Packing', $clean['heading']);
        $this->assertStringNotContainsString('script', $clean['content']);
        $this->assertStringNotContainsString('onclick', $clean['content']);
        $this->assertStringNotContainsString('javascript', $clean['content']);
        $this->assertStringContainsString('<a href="/ok">ok</a>', $clean['content']);
        $this->assertSame([['text' => 'One']], $clean['bullets']);
        $this->assertArrayNotHasKey('evil', $clean);
        $this->assertSame(7, $clean['image']);
        $this->assertSame('right', $clean['image_position'], 'select falls back to its default');
    }

    public function test_sync_creates_updates_deletes_orders_and_tracks_media()
    {
        $page = Page::factory()->create();
        $other = Page::factory()->create();
        $foreign = $other->sections()->create(['type' => 'rich_text', 'data' => ['content' => '<p>x</p>'], 'sort' => 0]);
        $image = $this->image();
        $sync = app(SectionSync::class);

        $sync->sync($page, [
            ['type' => 'hero', 'data' => ['heading' => 'A']],
            ['type' => 'gallery', 'data' => ['images' => [['image' => $image->id]]]],
        ]);
        [$hero, $gallery] = $page->sections()->get()->all();

        $this->assertSame([$image->id], $gallery->media_ids);
        $this->assertSame([['label' => "Page: {$page->title}", 'field' => 'Gallery section']], app(MediaUsage::class)->find($image));

        // Reorder, update, drop the gallery, and try to hijack another page's section ID.
        $sync->sync($page, [
            ['id' => $foreign->id, 'type' => 'rich_text', 'data' => ['content' => '<p>new</p>']],
            ['id' => $hero->id, 'type' => 'hero', 'data' => ['heading' => 'B'], 'is_visible' => false],
        ]);

        $sections = $page->sections()->get();
        $this->assertSame(['rich_text', 'hero'], $sections->pluck('type')->all());
        $this->assertSame($hero->id, $sections[1]->id);
        $this->assertSame('B', $sections[1]->data['heading']);
        $this->assertFalse($sections[1]->is_visible);
        $this->assertNotSame($foreign->id, $sections[0]->id, 'foreign IDs are never reused');
        $this->assertModelExists($foreign);
        $this->assertModelMissing($gallery);
        $this->assertFalse(app(MediaUsage::class)->isUsed($image));
    }

    public function test_pickers_keep_admin_order_and_reviews_fall_back()
    {
        $page = Page::factory()->create();
        [$a, $b, $c] = Service::factory()->count(3)->create()->all();
        $area = Area::factory()->create();
        Review::factory()->create(['name' => 'Featured Fan', 'is_featured' => true]);

        $page->sections()->create(['type' => 'services_grid', 'data' => ['mode' => 'picked', 'services' => [$c->id, $a->id]], 'sort' => 0]);
        $page->sections()->create(['type' => 'reviews_slider', 'data' => ['mode' => 'context'], 'sort' => 1]);

        $html = app(SectionRenderer::class)->render($page, area: $area)->html;

        $this->assertLessThan(strpos($html, $a->title), strpos($html, $c->title));
        $this->assertStringNotContainsString($b->title, $html);
        $this->assertStringContainsString('Featured Fan', $html, 'no reviews for the city yet: show featured');
    }

    public function test_rendered_faqs_are_collected_for_schema()
    {
        $page = Page::factory()->create();
        $faq = Faq::factory()->create(['question' => 'Are you insured?']);
        $page->faqs()->attach($faq, ['sort' => 0]);
        $page->sections()->create(['type' => 'faq', 'data' => ['mode' => 'attached'], 'sort' => 0]);

        $rendered = app(SectionRenderer::class)->render($page);

        $this->assertSame([$faq->id], $rendered->faqs->pluck('id')->all());
        $this->assertStringContainsString('Are you insured?', $rendered->html);
    }

    public function test_admin_schema_includes_every_type_and_icon_svgs()
    {
        $schema = $this->registry->forAdmin();

        $this->assertCount(count(config('sections.types')), $schema['types']);
        $this->assertCount(count(config('sections.icons')), $schema['icons']);
        $this->assertStringStartsWith('<svg', $schema['icons']['truck']);
        $this->assertSame('Stress-free moving in {city}', $schema['types']['hero']['defaults']['heading']);
    }

    private function image(): Media
    {
        return app(MediaService::class)->upload(UploadedFile::fake()->image('a.jpg', 800, 600), 'card');
    }

    /**
     * Valid sample data for every field.
     */
    private function sample(array $fields, int $mediaId): array
    {
        $data = [];

        foreach ($fields as $field) {
            $data[$field['key']] = match ($field['type']) {
                'text', 'textarea' => $field['key'] === 'embed_url' ? 'https://www.google.com/maps/embed?pb=abc' : 'Sample {company}',
                'code' => '<div>embed</div>',
                'richtext' => '<p>Rich <strong>text</strong> for {city}</p>',
                'image' => $mediaId,
                'link' => ['label' => 'Learn more', 'url' => '/get-a-quote'],
                'toggle' => true,
                'select' => (string) array_key_first($field['options']),
                'number' => $field['min'] ?? 1,
                'icon' => 'truck',
                'services' => Service::query()->pluck('id')->all(),
                'areas' => Area::query()->pluck('id')->all(),
                'reviews' => Review::query()->pluck('id')->all(),
                'faqs' => Faq::query()->pluck('id')->all(),
                'repeater' => [$this->sample($field['fields'], $mediaId), $this->sample($field['fields'], $mediaId)],
            };
        }

        return $data;
    }
}
