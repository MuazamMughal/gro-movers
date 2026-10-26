<?php

namespace Tests\Feature\Media;

use App\Models\Media;
use App\Models\Service;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    public function test_library_page_lists_media_with_filters()
    {
        $this->upload('dallas-office.jpg');
        $this->upload('plano-truck.jpg');

        $this->get(route('admin.media.index', ['q' => 'plano']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('media/Index')
                ->has('media.data', 1)
                ->where('media.data.0.filename', 'plano-truck.jpg')
                ->has('presets.hero')
                ->has('months', 1)
            );
    }

    public function test_upload_endpoint_returns_media_json()
    {
        $this->postJson(route('admin.media.store'), [
            'file' => UploadedFile::fake()->image('hero.jpg', 2000, 1000),
            'preset' => 'hero',
            'crop' => ['x' => 0, 'y' => 0, 'w' => 1920, 'h' => 800],
            'alt' => 'Movers loading a truck',
        ])
            ->assertCreated()
            ->assertJsonPath('media.preset', 'hero')
            ->assertJsonPath('media.alt', 'Movers loading a truck')
            ->assertJsonPath('media.display_width', 1920);
    }

    public function test_upload_validation()
    {
        $this->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->image('a.jpg'), 'preset' => 'poster'])
            ->assertUnprocessable()->assertJsonValidationErrors('preset');

        $svg = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"/>');
        $this->postJson(route('admin.media.store'), ['file' => $svg, 'preset' => 'hero'])
            ->assertUnprocessable()->assertJsonPath('message', 'SVG files can only be uploaded as logos.');
    }

    public function test_alt_text_can_be_updated()
    {
        $media = $this->upload('a.jpg');

        $this->patchJson(route('admin.media.update', $media), ['alt' => 'Packed boxes', 'title' => null, 'caption' => 'Our team'])
            ->assertOk()
            ->assertJsonPath('media.alt', 'Packed boxes');
    }

    public function test_media_in_use_cannot_be_deleted_and_usages_are_listed()
    {
        $hero = $this->upload('hero.jpg');
        $logo = $this->upload('logo.jpg');
        Service::factory()->create(['title' => 'Local Moving', 'hero_media_id' => $hero->id]);
        Settings::set('branding.logo_light_id', $logo->id);

        $this->getJson(route('admin.media.show', $hero))
            ->assertJsonPath('usages.0', ['label' => 'Service: Local Moving', 'field' => 'Hero image']);

        $this->deleteJson(route('admin.media.destroy', $hero))->assertStatus(409);
        $this->deleteJson(route('admin.media.destroy', $logo))
            ->assertStatus(409)
            ->assertJsonPath('usages.0.field', 'Logo (for light backgrounds)');

        $unused = $this->upload('unused.jpg');
        $this->deleteJson(route('admin.media.destroy', $unused))->assertOk();
        $this->assertModelMissing($unused);
    }

    public function test_crop_and_derive_endpoints()
    {
        $media = $this->upload('a.jpg', 1600, 1200);

        $this->postJson(route('admin.media.crop', $media), ['preset' => 'square', 'crop' => ['x' => 0, 'y' => 0, 'w' => 1200, 'h' => 1200]])
            ->assertOk()
            ->assertJsonPath('media.preset', 'square');

        $this->postJson(route('admin.media.derive', $media), ['preset' => 'card'])
            ->assertCreated()
            ->assertJsonPath('media.preset', 'card');

        $this->assertSame(2, Media::count());
    }

    public function test_browse_endpoint_paginates_for_the_picker()
    {
        foreach (range(1, 3) as $i) {
            $this->upload("img-{$i}.jpg", 300, 200);
        }

        $this->getJson(route('admin.media.browse'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('next_page', null);
    }

    public function test_guests_cannot_upload()
    {
        auth()->logout();

        $this->postJson(route('admin.media.store'), ['file' => UploadedFile::fake()->image('a.jpg')])->assertUnauthorized();
    }

    private function upload(string $name, int $w = 800, int $h = 600): Media
    {
        $id = $this->postJson(route('admin.media.store'), [
            'file' => UploadedFile::fake()->image($name, $w, $h),
            'preset' => 'card',
        ])->assertCreated()->json('media.id');

        return Media::findOrFail($id);
    }
}
