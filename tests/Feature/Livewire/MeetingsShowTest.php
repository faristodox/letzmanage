<?php

namespace Tests\Feature\Livewire;

use App\Enums\MeetingAttendanceMode;
use App\Enums\RoleName;
use App\Livewire\Meetings\Show;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MeetingsShowTest extends TestCase
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
        $meeting = Meeting::factory()->for($organization)->create();

        Livewire::actingAs($staff)
            ->test(Show::class, ['meeting' => $meeting])
            ->assertForbidden();
    }

    public function test_shows_transcript_and_minutes_when_ready(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create([
            'title' => 'Usrah Session',
            'transcript' => 'Faris: hello everyone.',
            'minutes' => 'Title: Usrah Session',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->assertSee('Usrah Session')
            ->assertSee('Faris: hello everyone.')
            ->assertSee('Title: Usrah Session');
    }

    public function test_shows_failure_reason_when_failed(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->failed()->for($organization)->create([
            'failure_reason' => 'Transcription timed out after 2 hours.',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->assertSee('Transcription timed out after 2 hours.');
    }

    public function test_admin_can_delete_a_meeting(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->call('delete')
            ->assertRedirect(route('meetings.index'));

        $this->assertDatabaseCount('meetings', 0);
    }

    public function test_can_download_the_transcript(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create([
            'title' => 'Usrah Session',
            'transcript' => 'Faris: hello everyone.',
        ]);

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->call('downloadTranscript')
            ->assertFileDownloaded('Usrah Session-transcript.txt');
    }

    public function test_admin_can_enable_open_checkin(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->set('attendanceMode', 'checkin')
            ->call('saveAttendanceSettings');

        $meeting->refresh();
        $this->assertSame(MeetingAttendanceMode::CheckIn, $meeting->attendance_mode);
        $this->assertNotNull($meeting->checkin_token);
    }

    public function test_admin_can_enable_allow_new_registration(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->set('attendanceMode', 'checkin')
            ->set('allowNewRegistration', true)
            ->call('saveAttendanceSettings');

        $meeting->refresh();
        $this->assertSame(MeetingAttendanceMode::CheckIn, $meeting->attendance_mode);
        $this->assertTrue($meeting->allow_new_registration);
    }

    public function test_allow_new_registration_is_ignored_when_checkin_is_off(): void
    {
        $organization = Organization::factory()->create();
        $meeting = Meeting::factory()->for($organization)->create();

        Livewire::actingAs($this->admin($organization))
            ->test(Show::class, ['meeting' => $meeting])
            ->set('attendanceMode', 'none')
            ->set('allowNewRegistration', true)
            ->call('saveAttendanceSettings');

        $meeting->refresh();
        $this->assertSame(MeetingAttendanceMode::None, $meeting->attendance_mode);
        $this->assertFalse($meeting->allow_new_registration);
    }
}
