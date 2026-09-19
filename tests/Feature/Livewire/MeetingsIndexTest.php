<?php

namespace Tests\Feature\Livewire;

use App\Enums\RoleName;
use App\Jobs\SubmitMeetingForTranscriptionJob;
use App\Livewire\Meetings\Index;
use App\Models\Event;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\Portfolio;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class MeetingsIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(Organization $organization): User
    {
        $admin = User::factory()->create(['organization_id' => $organization->id]);
        $admin->assignRole(RoleName::Admin->value);

        return $admin;
    }

    public function test_staff_without_permission_is_forbidden(): void
    {
        $organization = Organization::factory()->create();
        $staff = User::factory()->create(['organization_id' => $organization->id]);
        $staff->assignRole(RoleName::Staff->value);

        Livewire::actingAs($staff)
            ->test(Index::class)
            ->assertForbidden();
    }

    public function test_admin_can_create_a_meeting_from_an_uploaded_file(): void
    {
        Queue::fake();

        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->set('title', 'Usrah Session')
            ->set('file', UploadedFile::fake()->create('recording.wav', 500, 'audio/wav'))
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('meetings', [
            'organization_id' => $organization->id,
            'title' => 'Usrah Session',
            'status' => 'pending',
        ]);

        Queue::assertPushed(SubmitMeetingForTranscriptionJob::class);
    }

    public function test_can_preselect_and_filter_by_an_event(): void
    {
        $organization = Organization::factory()->create();
        $event = Event::factory()->for($organization)->create(['title' => 'Annual Dinner']);
        Meeting::factory()->for($organization)->create(['event_id' => $event->id, 'title' => 'Linked Meeting']);
        Meeting::factory()->for($organization)->create(['event_id' => null, 'title' => 'Standalone Meeting']);

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class, ['event' => $event->id])
            ->assertSet('eventId', $event->id)
            ->assertSee('Linked Meeting')
            ->assertDontSee('Standalone Meeting')
            ->call('clearEventFilter')
            ->assertSee('Standalone Meeting');
    }

    public function test_committee_member_only_sees_meetings_linked_to_their_own_portfolios_events(): void
    {
        $organization = Organization::factory()->create();
        $wanita = Portfolio::factory()->create(['organization_id' => $organization->id]);
        $belia = Portfolio::factory()->create(['organization_id' => $organization->id]);

        $wanitaMember = User::factory()->create(['organization_id' => $organization->id, 'portfolio_id' => $wanita->id]);
        $wanitaMember->assignRole(RoleName::CommitteeMember->value);

        $wanitaEvent = Event::factory()->for($organization)->create(['portfolio_id' => $wanita->id]);
        $beliaEvent = Event::factory()->for($organization)->create(['portfolio_id' => $belia->id]);

        Meeting::factory()->for($organization)->create(['event_id' => $wanitaEvent->id, 'title' => 'WANITA Meeting']);
        Meeting::factory()->for($organization)->create(['event_id' => $beliaEvent->id, 'title' => 'Belia Meeting']);
        Meeting::factory()->for($organization)->create(['event_id' => null, 'title' => 'Standalone Meeting']);

        Livewire::actingAs($wanitaMember)
            ->test(Index::class)
            ->assertSee('WANITA Meeting')
            ->assertDontSee('Belia Meeting')
            ->assertDontSee('Standalone Meeting');
    }

    public function test_admin_sees_meetings_from_every_portfolio(): void
    {
        $organization = Organization::factory()->create();
        $wanita = Portfolio::factory()->create(['organization_id' => $organization->id]);
        $belia = Portfolio::factory()->create(['organization_id' => $organization->id]);

        $wanitaEvent = Event::factory()->for($organization)->create(['portfolio_id' => $wanita->id]);
        $beliaEvent = Event::factory()->for($organization)->create(['portfolio_id' => $belia->id]);

        Meeting::factory()->for($organization)->create(['event_id' => $wanitaEvent->id, 'title' => 'WANITA Meeting']);
        Meeting::factory()->for($organization)->create(['event_id' => $beliaEvent->id, 'title' => 'Belia Meeting']);

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->assertSee('WANITA Meeting')
            ->assertSee('Belia Meeting');
    }

    public function test_validation_rejects_a_file_with_an_unsupported_extension(): void
    {
        $organization = Organization::factory()->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Index::class)
            ->set('title', 'Usrah Session')
            ->set('file', UploadedFile::fake()->create('recording.mp4', 500, 'video/mp4'))
            ->call('save')
            ->assertHasErrors(['file']);

        $this->assertDatabaseCount('meetings', 0);
    }
}
