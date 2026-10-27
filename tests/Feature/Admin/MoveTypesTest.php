<?php

namespace Tests\Feature\Admin;

use App\Models\Lead;
use App\Models\MoveType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MoveTypesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_add_rename_reorder_and_toggle()
    {
        $a = MoveType::factory()->create(['name' => 'Local Move', 'sort' => 0]);

        $this->post(route('admin.move-types.store'), ['name' => 'Senior Move'])->assertSessionHasNoErrors();
        $this->post(route('admin.move-types.store'), ['name' => 'Senior Move'])->assertSessionHasErrors('name');
        $b = MoveType::where('name', 'Senior Move')->sole();
        $this->assertSame(1, $b->sort);

        $this->put(route('admin.move-types.update', $a), ['name' => 'Local', 'is_active' => true]);
        $this->assertSame('Local', $a->fresh()->name);

        $this->post(route('admin.move-types.reorder'), ['ids' => [$b->id, $a->id]]);
        $this->assertSame([$b->id, $a->id], MoveType::query()->ordered()->pluck('id')->all());

        $this->put(route('admin.move-types.update', $a), ['name' => 'Local', 'is_active' => false]);
        $this->assertFalse($a->fresh()->is_active);
    }

    public function test_the_last_active_type_cannot_be_disabled_or_deleted()
    {
        $only = MoveType::factory()->create();
        MoveType::factory()->inactive()->create();

        $this->put(route('admin.move-types.update', $only), ['name' => $only->name, 'is_active' => false]);
        $this->assertTrue($only->fresh()->is_active);

        $this->delete(route('admin.move-types.destroy', $only));
        $this->assertModelExists($only);
    }

    public function test_deleting_keeps_the_name_on_existing_leads()
    {
        $type = MoveType::factory()->create(['name' => 'Office Move']);
        MoveType::factory()->create();
        $lead = Lead::factory()->create(['move_type_id' => $type->id, 'move_type_name' => 'Office Move']);

        $this->delete(route('admin.move-types.destroy', $type));

        $this->assertModelMissing($type);
        $this->assertNull($lead->fresh()->move_type_id);
        $this->assertSame('Office Move', $lead->fresh()->move_type_name);
    }
}
