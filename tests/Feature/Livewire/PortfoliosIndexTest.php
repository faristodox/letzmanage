<?php

namespace Tests\Feature\Livewire;

use App\Enums\RoleName;
use App\Livewire\Portfolios\Index;
use App\Models\Portfolio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PortfoliosIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_create_edit_and_delete_a_portfolio(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('create')
            ->set('name', 'Jawatankuasa WANITA')
            ->call('save')
            ->assertHasNoErrors();

        $portfolio = Portfolio::where('name', 'Jawatankuasa WANITA')->first();
        $this->assertNotNull($portfolio);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('edit', $portfolio->id)
            ->set('name', 'Jawatankuasa Belia')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Jawatankuasa Belia', $portfolio->refresh()->name);

        Livewire::actingAs($admin)
            ->test(Index::class)
            ->call('confirmDelete', $portfolio->id)
            ->call('delete');

        $this->assertNull(Portfolio::find($portfolio->id));
    }

    public function test_staff_cannot_access_portfolios_component(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->assertForbidden();
    }

    public function test_the_full_portfolios_page_renders_for_an_admin(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin->value);

        $this->actingAs($admin)
            ->get(route('portfolios.index'))
            ->assertOk()
            ->assertSee('Portfolios');
    }
}
