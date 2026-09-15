<?php

namespace Tests\Feature\Livewire;

use App\Enums\SpiKawasan;
use App\Livewire\SpiMembers\NaqibUsrah;
use App\Models\Organization;
use App\Models\SpiNaqibUsrah;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SpiNaqibUsrahTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(CurrentOrganization::class)->set(Organization::factory()->create());
    }

    public function test_it_lists_naqib_and_temporary_groups(): void
    {
        SpiNaqibUsrah::create(['level' => '00', 'naqib_name' => 'Ahmad Ashraf']);
        SpiNaqibUsrah::create(['level' => '00', 'is_temporary_group' => true]);

        Livewire::test(NaqibUsrah::class)
            ->assertSee('Ahmad Ashraf')
            ->assertSee('Belum ada naqib (grup sementara)');
    }

    public function test_search_filters_by_naqib_name(): void
    {
        SpiNaqibUsrah::create(['level' => '00', 'naqib_name' => 'Ahmad Ashraf']);
        SpiNaqibUsrah::create(['level' => '00', 'naqib_name' => 'Zainal Fikri']);

        Livewire::test(NaqibUsrah::class)
            ->set('search', 'Ashraf')
            ->assertSee('Ahmad Ashraf')
            ->assertDontSee('Zainal Fikri');
    }

    public function test_search_matches_a_member_name_within_the_group(): void
    {
        SpiNaqibUsrah::create([
            'level' => '00',
            'naqib_name' => 'Ahmad Ashraf',
            'members' => [['nama' => 'Zainal Fikri', 'umur' => 37, 'no_tel' => '0173427958']],
        ]);
        SpiNaqibUsrah::create(['level' => '00', 'naqib_name' => 'Someone Else']);

        Livewire::test(NaqibUsrah::class)
            ->set('search', 'Zainal Fikri')
            ->assertSee('Ahmad Ashraf')
            ->assertDontSee('Someone Else');
    }

    public function test_level_filter_narrows_the_list(): void
    {
        SpiNaqibUsrah::create(['level' => '00', 'naqib_name' => 'Level 00 Naqib']);
        SpiNaqibUsrah::create(['level' => '01', 'naqib_name' => 'Level 01 Naqib']);

        Livewire::test(NaqibUsrah::class)
            ->set('filterLevel', '01')
            ->assertSee('Level 01 Naqib')
            ->assertDontSee('Level 00 Naqib');
    }

    public function test_kawasan_filter_narrows_the_list_without_hiding_other_kawasan_from_the_options(): void
    {
        SpiNaqibUsrah::create(['level' => '00', 'naqib_name' => 'Setiawangsa Naqib', 'kawasan' => 'Setiawangsa']);
        SpiNaqibUsrah::create(['level' => '00', 'naqib_name' => 'Batu Naqib', 'kawasan' => 'Batu']);

        Livewire::test(NaqibUsrah::class)
            ->set('filterKawasan', '')
            ->assertSee('Setiawangsa Naqib')
            ->assertSee('Batu Naqib')
            ->set('filterKawasan', 'Batu')
            ->assertSee('Batu Naqib')
            ->assertDontSee('Setiawangsa Naqib');
    }

    public function test_kawasan_filter_can_isolate_groups_with_no_kawasan_recorded(): void
    {
        SpiNaqibUsrah::create(['level' => '00', 'naqib_name' => 'No Kawasan Naqib', 'kawasan' => null]);
        SpiNaqibUsrah::create(['level' => '00', 'naqib_name' => 'Setiawangsa Naqib', 'kawasan' => 'Setiawangsa']);

        Livewire::test(NaqibUsrah::class)
            ->set('filterKawasan', NaqibUsrah::NO_KAWASAN)
            ->assertSee('No Kawasan Naqib')
            ->assertDontSee('Setiawangsa Naqib');
    }

    public function test_kawasan_filter_defaults_to_the_users_own_organization_branch(): void
    {
        $organization = Organization::factory()->create(['spi_district_code' => SpiKawasan::Setiawangsa->value]);
        $user = User::factory()->create(['organization_id' => $organization->id]);

        app(CurrentOrganization::class)->set($organization);
        SpiNaqibUsrah::create(['level' => '00', 'naqib_name' => 'Setiawangsa Naqib', 'kawasan' => 'Setiawangsa']);
        SpiNaqibUsrah::create(['level' => '00', 'naqib_name' => 'Batu Naqib', 'kawasan' => 'Batu']);

        Livewire::actingAs($user)
            ->test(NaqibUsrah::class)
            ->assertSet('filterKawasan', 'Setiawangsa')
            ->assertSee('Setiawangsa Naqib')
            ->assertDontSee('Batu Naqib');
    }
}
