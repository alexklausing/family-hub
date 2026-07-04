<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\CalendarEventCache;
use App\Models\Profile;
use App\Services\Calendar\CalendarManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CalendarController extends Controller
{
    public function __construct(
        protected CalendarManager $calendarManager
    ) {}

    public function sync(Request $request)
    {
        $calendars = Calendar::all();

        foreach ($calendars as $calendar) {
            // We'll run this synchronously for now as per the user's manual trigger request,
            // but in a large production app, this should be a job.
            $this->calendarManager->syncCalendar($calendar);
        }

        return response()->json(['message' => 'Sync complete']);
    }

    public function index(Request $request)
    {
        $profileName = $request->query('profile', 'Family');
        $totalProfiles = Profile::count();
        Log::info("Fetching events for profile: {$profileName}. Total profiles in DB: {$totalProfiles}");

        // Use cross-database compatible case-insensitive search
        $operator = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
        $profile = Profile::where('name', $operator, trim($profileName))->first();

        // Fallback to default profile if requested one not found
        if (! $profile) {
            Log::info("Profile '{$profileName}' not found, attempting default fallback.");
            $profile = Profile::where('is_default', true)->first();
        }

        if (! $profile) {
            Log::error("Profile '{$profileName}' and default profile not found.");

            return response()->json([
                'error' => "Profile '{$profileName}' not found and no default profile exists.",
                'attempted' => $profileName,
            ], 404);
        }

        Log::info("Profile found: {$profile->name} (ID: {$profile->id})");

        $allCalendars = Calendar::all();

        $start = $request->query('start') ? Carbon::parse($request->query('start')) : now()->subMonth();
        $end = $request->query('end') ? Carbon::parse($request->query('end')) : now()->addMonths(6);

        // SYNC LOGIC: Removed synchronous sync to prevent memory issues.
        // Dashboard should rely on the background scheduler or a separate async trigger.
        if ($request->has('sync')) {
            // In a real app, we might dispatch individual jobs for each calendar
            // For now, we'll just return a 202 to indicate we're not doing it here.
            return response()->json(['message' => 'Sync should be handled by background workers'], 202);
        }

        // Always return from cache for speed, fetching for ALL calendars
        $events = CalendarEventCache::with('calendar')
            ->whereIn('calendar_id', $allCalendars->pluck('id'))
            ->where(function ($query) use ($start, $end) {
                if ($start) {
                    $query->where('end', '>=', $start);
                }
                if ($end) {
                    $query->where('start', '<=', $end);
                }
            })
            ->orderBy('start', 'asc')
            ->get();

        $formattedEvents = $events->map(function ($event) {
            // Description might be directly on the event cache or inside the raw 'data' json depending on implementation
            $description = $event->description ?? ($event->data['description'] ?? '');

            return [
                'id' => $event->id,
                'calendar_id' => $event->calendar_id,
                'title' => $event->title,
                'start' => $event->all_day ? $event->start->format('Y-m-d') : $event->start->toIso8601String(),
                'end' => $event->all_day ? $event->end->format('Y-m-d') : $event->end->toIso8601String(),
                'all_day' => (bool) $event->all_day,
                'description' => $description,
                'calendar' => $event->calendar,
            ];
        });

        $profiles = \App\Models\Profile::all(['id', 'name', 'visible_calendars']);

        return response()->json([
            'events' => $formattedEvents,
            'calendars' => $allCalendars,
            'profiles' => $profiles,
            'default_calendar_id' => $profile->default_calendar_id,
        ]);
    }

    public function storeEvent(Request $request, Calendar $calendar)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'start' => 'required|date',
            'end' => 'required|date|after_or_equal:start',
            'all_day' => 'boolean',
            'timezone' => 'string|nullable',
            'description' => 'string|nullable',
        ]);

        if ($calendar->provider !== 'apple') {
            return response()->json(['error' => 'This calendar provider does not support creating events via this application.'], 400);
        }

        $success = $this->calendarManager->createEvent($calendar, $validated);

        if ($success) {
            // Trigger a sync so the event shows up quickly
            $this->calendarManager->syncCalendar($calendar);

            return response()->json(['message' => 'Event created successfully']);
        }

        return response()->json(['error' => 'Failed to create event on the remote calendar.'], 500);
    }
}
