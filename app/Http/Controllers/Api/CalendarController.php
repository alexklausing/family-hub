<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\Calendar\CalendarManager;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function __construct(
        protected CalendarManager $calendarManager
    ) {}

    public function index(Request $request)
    {
        $profile = Profile::where('name', $request->query('profile', 'Family'))->first();

        if (! $profile) {
            return response()->json(['error' => 'Profile not found'], 404);
        }

        $tab = $profile->tabs()->whereHas('calendars')->first();

        if (! $tab) {
            return response()->json(['events' => [], 'calendars' => []]);
        }

        $start = $request->query('start') ? Carbon::parse($request->query('start')) : now()->subMonth();
        $end = $request->query('end') ? Carbon::parse($request->query('end')) : now()->addMonths(6);

        // SYNC LOGIC: Removed synchronous sync to prevent memory issues.
        // Dashboard should rely on the background scheduler or a separate async trigger.
        if ($request->has('sync')) {
            // In a real app, we might dispatch individual jobs for each calendar
            // For now, we'll just return a 202 to indicate we're not doing it here.
            return response()->json(['message' => 'Sync should be handled by background workers'], 202);
        }

        // Always return from cache for speed
        $events = \App\Models\CalendarEventCache::with('calendar')
            ->whereIn('calendar_id', $tab->calendars->pluck('id'))
            ->where(function ($query) use ($start, $end) {
                if ($start) $query->where('end', '>=', $start);
                if ($end) $query->where('start', '<=', $end);
            })
            ->orderBy('start', 'asc')
            ->get();

        $formattedEvents = $events->map(function ($event) {
            return [
                'id' => $event->id,
                'calendar_id' => $event->calendar_id,
                'title' => $event->title,
                'start' => $event->all_day ? $event->start->format('Y-m-d') : $event->start->toIso8601String(),
                'end' => $event->all_day ? $event->end->format('Y-m-d') : $event->end->toIso8601String(),
                'all_day' => (bool)$event->all_day,
                'calendar' => $event->calendar,
            ];
        });

        return response()->json([
            'events' => $formattedEvents,
            'calendars' => $tab->calendars
        ]);
    }
}
