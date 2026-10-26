<?php

namespace Tests\Feature\Models;

use App\Models;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    use RefreshDatabase;

    public static function factoryModels(): array
    {
        return collect([
            Models\User::class, Models\Media::class, Models\Page::class, Models\Service::class,
            Models\Area::class, Models\AreaServicePage::class, Models\MoveType::class,
            Models\Category::class, Models\Author::class, Models\Tag::class, Models\Post::class,
            Models\Review::class, Models\Faq::class, Models\Menu::class, Models\MenuItem::class,
            Models\Lead::class, Models\LeadNote::class, Models\ContactMessage::class,
            Models\Redirect::class,
        ])->mapWithKeys(fn ($class) => [class_basename($class) => [$class]])->all();
    }

    #[DataProvider('factoryModels')]
    public function test_factory_creates_a_valid_record(string $class)
    {
        $model = $class::factory()->create();

        $this->assertModelExists($model);
    }
}
