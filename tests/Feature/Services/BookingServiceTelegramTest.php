<?php

namespace Tests\Feature\Services;

use App\Enums\ApprovalMode;
use App\Enums\BookingStatus;
use App\Enums\RoleName;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\OfficeSpace;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\BookingAutoApprovedNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\BookingSubmittedNotification;
use App\Services\BookingService;
use App\Services\SystemSettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BookingServiceTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_guest_booking_sends_telegram_http_message_when_configured(): void
    {
        config([
            'services.telegram.chat_id' => 'test-chat-id',
            'services.telegram.token' => 'test-token',
        ]);

        Http::fake(['https://api.telegram.org/*' => Http::response(['ok' => true])]);
        Notification::fake();

        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);
        $date = now()->addDay()->format('Y-m-d');

        app(BookingService::class)->createGuestBooking([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'start_time' => "{$date} 09:00:00",
            'end_time' => "{$date} 10:00:00",
            'guest_name' => 'Jane Visitor',
            'guest_email' => 'jane@example.com',
        ]);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'sendMessage')
            && $request['chat_id'] === 'test-chat-id'
            && str_contains($request['reply_markup'], 'approve_')
        );
    }

    public function test_telegram_broadcast_is_skipped_when_chat_id_not_configured(): void
    {
        config(['services.telegram.chat_id' => null, 'services.telegram.token' => null]);

        Http::fake();
        Notification::fake();

        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);
        $date = now()->addDay()->format('Y-m-d');

        app(BookingService::class)->createGuestBooking([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'start_time' => "{$date} 09:00:00",
            'end_time' => "{$date} 10:00:00",
            'guest_name' => 'Jane Visitor',
            'guest_email' => 'jane@example.com',
        ]);

        Http::assertNothingSent();
    }

    public function test_approve_and_reject_broadcast_to_telegram(): void
    {
        config(['services.telegram.chat_id' => 'test-chat-id']);

        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Manual, $branch->id);

        $manager = User::factory()->create(['branch_id' => $branch->id]);
        $manager->assignRole(RoleName::Manager->value);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $date = now()->addDay()->format('Y-m-d');

        $approveBooking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $staff->id,
            'status' => BookingStatus::Pending,
            'start_time' => "{$date} 09:00:00",
            'end_time' => "{$date} 10:00:00",
        ]);

        $rejectBooking = Booking::factory()->create([
            'branch_id' => $branch->id,
            'space_id' => $space->id,
            'user_id' => $staff->id,
            'status' => BookingStatus::Pending,
            'start_time' => "{$date} 11:00:00",
            'end_time' => "{$date} 12:00:00",
        ]);

        Notification::fake();

        $bookingService = app(BookingService::class);
        $bookingService->approve($approveBooking, $manager);
        $bookingService->reject($rejectBooking, $manager, 'No longer needed');

        Notification::assertSentOnDemand(
            BookingApprovedNotification::class,
            fn ($notification, $channels, $notifiable) => in_array('telegram', $channels)
                && $notifiable->routeNotificationFor('telegram') === 'test-chat-id'
        );

        Notification::assertSentOnDemand(
            BookingRejectedNotification::class,
            fn ($notification, $channels, $notifiable) => in_array('telegram', $channels)
                && $notifiable->routeNotificationFor('telegram') === 'test-chat-id'
        );
    }

    public function test_auto_approved_booking_broadcasts_to_telegram(): void
    {
        config(['services.telegram.chat_id' => 'test-chat-id']);

        $branch = Branch::factory()->create();
        $space = OfficeSpace::factory()->create(['branch_id' => $branch->id]);

        app(SystemSettingService::class)->setApprovalMode(ApprovalMode::Auto, $branch->id);

        $staff = User::factory()->create(['branch_id' => $branch->id]);
        $staff->assignRole(RoleName::Staff->value);

        $date = now()->addDay()->format('Y-m-d');

        Notification::fake();

        app(BookingService::class)->create([
            'branch_id' => $branch->id,
            'user_id' => $staff->id,
            'space_id' => $space->id,
            'start_time' => "{$date} 09:00:00",
            'end_time' => "{$date} 10:00:00",
        ]);

        Notification::assertSentOnDemand(
            BookingAutoApprovedNotification::class,
            fn ($notification, $channels, $notifiable) => in_array('telegram', $channels)
                && $notifiable->routeNotificationFor('telegram') === 'test-chat-id'
        );
    }
}
