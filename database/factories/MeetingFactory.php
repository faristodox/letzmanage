<?php

namespace Database\Factories;

use App\Enums\MeetingAttendanceMode;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'title' => fake()->sentence(3),
            'status' => MeetingStatus::Ready,
            'audio_gcs_object' => null,
            'gcs_operation_name' => null,
            'transcript' => fake()->paragraphs(5, true),
            'minutes' => "Attendees: ".fake()->name()."\n\nKey Discussion Points:\n- ".fake()->sentence(),
            'duration_seconds' => fake()->numberBetween(600, 7200),
            'failure_reason' => null,
            'attendance_mode' => MeetingAttendanceMode::None,
            'checkin_token' => null,
            'allow_new_registration' => false,
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status' => MeetingStatus::Pending,
            'transcript' => null,
            'minutes' => null,
        ]);
    }

    public function transcribing(): static
    {
        return $this->state([
            'status' => MeetingStatus::Transcribing,
            'audio_gcs_object' => 'meetings/1/'.fake()->uuid().'.webm',
            'gcs_operation_name' => 'operations/'.fake()->uuid(),
            'transcript' => null,
            'minutes' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state([
            'status' => MeetingStatus::Failed,
            'transcript' => null,
            'minutes' => null,
            'failure_reason' => 'Transcription failed.',
        ]);
    }
}
