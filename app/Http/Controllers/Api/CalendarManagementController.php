<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Calendar;
use App\Models\Profile;
use App\Models\Tab;
use App\Services\Calendar\CalendarManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

use App\Services\Calendar\AppleCalendarService;

class CalendarManagementController extends Controller
{
    public function __construct(protected CalendarManager $manager, protected AppleCalendarService $appleService) {}

    public function fetchAppleCalendars(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (!$this->appleService->testConnection($validated['email'], $validated['password'])) {
            throw ValidationException::withMessages([
                'password' => 'Failed to connect to iCloud. Please ensure you are using an App-Specific Password.',
            ]);
        }

        $calendars = $this->appleService->getCalendars($validated['email'], $validated['password']);

        return response()->json([
            'calendars' => $calendars,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'profile_name' => 'required|string',
            'name' => 'required|string|max:255',
            'provider' => 'required|in:ical,apple',
            'url' => 'required_if:provider,ical|nullable|url',
            'email' => 'required_if:provider,apple|nullable|email',
            'password' => 'required_if:provider,apple|nullable|string',
            'apple_calendar_path' => 'required_if:provider,apple|nullable|string',
            'color' => 'required|string|max:20',
        ]);

        // Find the profile and its Calendar tab
        $profile = Profile::where('name', $validated['profile_name'])->firstOrFail();
        $tab = $profile->tabs()->firstOrCreate(['name' => 'Calendar'], ['order' => 1]);

        // Quick validation of credentials before creating
        if ($validated['provider'] === 'ical') {
            try {
                $url = str_replace('webcal://', 'https://', $validated['url']);
                $response = Http::timeout(10)->get($url);

                if ($response->status() === 429) {
                    throw ValidationException::withMessages([
                        'url' => 'The calendar server is currently rate limiting requests (429 Too Many Requests). Please try again later.',
                    ]);
                }

                if ($response->failed() || ! str_contains($response->body(), 'BEGIN:VCALENDAR')) {
                    throw ValidationException::withMessages([
                        'url' => 'The provided URL does not appear to be a valid iCal feed.',
                    ]);
                }
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Exception $e) {
                throw ValidationException::withMessages([
                    'url' => 'Failed to connect to the calendar server: '.$e->getMessage(),
                ]);
            }
        } elseif ($validated['provider'] === 'apple') {
            if (!$this->appleService->testConnection($validated['email'], $validated['password'])) {
                throw ValidationException::withMessages([
                    'password' => 'Failed to connect to iCloud. Please ensure you are using an App-Specific Password.',
                ]);
            }
        }

        // Setup Credentials
        $credentials = [];
        if ($validated['provider'] === 'ical') {
            $credentials['url'] = $validated['url'];
        } else {
            $credentials['email'] = $validated['email'];
            $credentials['password'] = $validated['password'];
            $credentials['path'] = $validated['apple_calendar_path'];
        }

        // Create the Calendar
        $calendar = $tab->calendars()->create([
            'provider' => $validated['provider'],
            'external_id' => 'imported_'.uniqid(),
            'name' => $validated['name'],
            'color' => $validated['color'],
            'refresh_rate' => 60,
            'credentials' => $credentials,
        ]);

        // Perform the initial sync
        $this->manager->syncCalendar($calendar);

        return response()->json([
            'message' => 'Calendar imported successfully.',
            'calendar' => $calendar,
        ], 201);
    }

    public function update(Request $request, Calendar $calendar)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|in:ical,apple',
            'url' => 'required_if:provider,ical|nullable|url',
            'email' => 'required_if:provider,apple|nullable|email',
            // Allow empty password on edit to keep the existing one
            'password' => 'nullable|string',
            'apple_calendar_path' => 'required_if:provider,apple|nullable|string',
            'color' => 'required|string|max:20',
        ]);

        $credentials = $calendar->credentials ?? [];
        if ($validated['provider'] === 'ical') {
            $credentials['url'] = $validated['url'];
        } elseif ($validated['provider'] === 'apple') {
            $credentials['email'] = $validated['email'];
            $credentials['path'] = $validated['apple_calendar_path'];
            if (!empty($validated['password'])) {
                $credentials['password'] = $validated['password'];
            }
        }

        $calendar->update([
            'name' => $validated['name'],
            'color' => $validated['color'],
            'provider' => $validated['provider'],
            'credentials' => $credentials,
        ]);

        return response()->json([
            'message' => 'Calendar updated successfully.',
            'calendar' => $calendar,
        ]);
    }

    public function destroy(Calendar $calendar)
    {
        // The CalendarEventCache foreign keys cascade on delete,
        // or we can manually delete the cached events if configured to do so.
        $calendar->events()->delete();
        $calendar->delete();

        return response()->json(['message' => 'Calendar deleted successfully.']);
    }

    public function setDefaultCalendar(Request $request, $profileName)
    {
        $validated = $request->validate([
            'calendar_id' => 'required|exists:calendars,id',
        ]);

        $profile = \App\Models\Profile::where('name', 'ILIKE', trim($profileName))->first();

        if (!$profile) {
            return response()->json(['error' => 'Profile not found'], 404);
        }

        $profile->update([
            'default_calendar_id' => $validated['calendar_id']
        ]);

        return response()->json([
            'message' => 'Default calendar updated successfully.',
            'default_calendar_id' => $profile->default_calendar_id
        ]);
    }
}
