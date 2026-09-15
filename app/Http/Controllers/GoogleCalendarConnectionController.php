<?php

namespace App\Http\Controllers;

use App\Enums\CalendarSyncMode;
use App\Services\GoogleOAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Connects a Google account for Calendar sync. Two independent connect/
 * callback pairs rather than one shared callback carrying an encoded
 * intent — Google requires an exact pre-registered redirect_uri per flow
 * anyway, so separate routes are simpler and leave no tampering surface.
 * `state` here is only a CSRF token, not an intent carrier.
 *
 * The shared-mode routes are gated by the `manage settings` permission at
 * the route level (see routes/web.php), matching every other admin-settings
 * route in this app — there's no AuthorizesRequests trait on the base
 * Controller here, so policy checks happen via ->can(...) on the route.
 */
class GoogleCalendarConnectionController extends Controller
{
    public function __construct(private readonly GoogleOAuthService $oauth) {}

    public function redirectForShared(Request $request): RedirectResponse
    {
        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away(
            $this->oauth->buildAuthorizationUrl(config('services.google_calendar.redirect_uri_shared'), $state)
        );
    }

    public function callbackForShared(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()->route('settings.calendar')->with('status', __('Google Calendar connection was cancelled.'));
        }

        if (! $this->hasValidState($request)) {
            abort(403);
        }

        $tokens = $this->oauth->exchangeCodeForTokens($request->string('code'), config('services.google_calendar.redirect_uri_shared'));

        auth()->user()->organization->calendarSetting()->updateOrCreate([], [
            'google_account_email' => $tokens['email'],
            'google_access_token' => $tokens['access_token'],
            'google_refresh_token' => $tokens['refresh_token'],
            'google_token_expires_at' => now()->addSeconds($tokens['expires_in']),
            'google_calendar_id' => 'primary',
            'google_connected_at' => now(),
        ]);

        return redirect()->route('settings.calendar')->with('status', __('Google Calendar connected successfully.'));
    }

    public function redirectForIndividual(Request $request): RedirectResponse
    {
        abort_unless($this->individualModeAllowed(), 403);

        $state = Str::random(40);
        $request->session()->put('google_oauth_state', $state);

        return redirect()->away(
            $this->oauth->buildAuthorizationUrl(config('services.google_calendar.redirect_uri_individual'), $state)
        );
    }

    public function callbackForIndividual(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect()->route('profile')->with('status', __('Google Calendar connection was cancelled.'));
        }

        abort_unless($this->individualModeAllowed(), 403);

        if (! $this->hasValidState($request)) {
            abort(403);
        }

        $tokens = $this->oauth->exchangeCodeForTokens($request->string('code'), config('services.google_calendar.redirect_uri_individual'));

        auth()->user()->googleAccount()->updateOrCreate([], [
            'organization_id' => auth()->user()->organization_id,
            'google_account_email' => $tokens['email'],
            'google_access_token' => $tokens['access_token'],
            'google_refresh_token' => $tokens['refresh_token'],
            'google_token_expires_at' => now()->addSeconds($tokens['expires_in']),
            'google_calendar_id' => 'primary',
            'google_connected_at' => now(),
        ]);

        return redirect()->route('profile')->with('status', __('Google Calendar connected successfully.'));
    }

    /**
     * Defense in depth: the profile UI already hides the connect button
     * unless the org is in Individual mode, but the callback re-checks
     * server-side since a user could otherwise hit the route directly.
     */
    private function individualModeAllowed(): bool
    {
        return auth()->user()->organization?->calendarSetting?->sync_mode === CalendarSyncMode::Individual;
    }

    private function hasValidState(Request $request): bool
    {
        $expected = $request->session()->pull('google_oauth_state');

        return $expected !== null && hash_equals($expected, (string) $request->query('state'));
    }
}
