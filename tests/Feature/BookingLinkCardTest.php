<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingLinkCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function orgAdmin(string $slug): User
    {
        $org = Organization::factory()->create(['slug' => $slug]);
        $branch = Branch::factory()->for($org)->create();
        $user = User::factory()->for($org)->create(['branch_id' => $branch->id]);
        $user->assignRole(RoleName::Admin->value);

        return $user;
    }

    public function test_org_user_sees_their_public_booking_link_on_the_bookings_page(): void
    {
        $user = $this->orgAdmin('acme-co');

        $this->actingAs($user)
            ->get(route('bookings.index'))
            ->assertOk()
            ->assertSee('Your public booking link')
            ->assertSee(route('booking.request', 'acme-co'));
    }

    public function test_booking_link_card_is_not_on_the_dashboard(): void
    {
        $user = $this->orgAdmin('acme-co');

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Your public booking link');
    }
}
