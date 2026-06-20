<?php

namespace App\Http\Controllers;

use App\Models\Chore;
use App\Models\ChoreCompletion;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ChoreController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', Carbon::today()->toDateString());
        $profile = $request->query('profile', 'Family');

        $chores = Chore::with(['label', 'completions' => function ($query) use ($date) {
            $query->where('date', $date);
        }])
            ->where('profile', $profile)
            ->orderBy('order', 'asc')
            ->orderBy('time', 'asc')
            ->get();

        // Transform to include a simple "completed" boolean for the specific date
        $chores->transform(function ($chore) {
            $chore->completed = $chore->completions->isNotEmpty();
            unset($chore->completions); // Clean up payload

            return $chore;
        });

        return response()->json($chores);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'profile' => 'required|string|max:255',
            'time' => 'nullable|string|max:10',
            'days' => 'nullable|array',
            'days.*' => 'integer|min:0|max:6',
            'is_active' => 'boolean',
            'order' => 'integer',
            'reward' => 'nullable|string|max:255',
            'label_id' => 'nullable|integer|exists:labels,id',
        ]);

        $chore = Chore::create($validated);

        return response()->json($chore, 201);
    }

    public function update(Request $request, Chore $chore)
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'profile' => 'sometimes|string|max:255',
            'time' => 'nullable|string|max:10',
            'days' => 'nullable|array',
            'days.*' => 'integer|min:0|max:6',
            'is_active' => 'boolean',
            'order' => 'integer',
            'reward' => 'nullable|string|max:255',
            'label_id' => 'nullable|integer|exists:labels,id',
        ]);

        $chore->update($validated);

        return response()->json($chore);
    }

    public function destroy(Chore $chore)
    {
        $chore->delete();

        return response()->json(null, 204);
    }

    public function toggle(Request $request, Chore $chore)
    {
        $validated = $request->validate([
            'date' => 'required|date',
        ]);

        $date = $validated['date'];

        $completion = ChoreCompletion::where('chore_id', $chore->id)
            ->where('date', $date)
            ->first();

        if ($completion) {
            $completion->delete();

            return response()->json(['completed' => false]);
        } else {
            ChoreCompletion::create([
                'chore_id' => $chore->id,
                'date' => $date,
            ]);

            return response()->json(['completed' => true]);
        }
    }

    /**
     * Clone all chores belonging to a label group (or the unlabelled group)
     * from one profile to another.
     */
    public function cloneGroup(Request $request)
    {
        $validated = $request->validate([
            'from_profile' => 'required|string|max:255',
            'to_profile' => 'required|string|max:255|different:from_profile',
            'label_id' => 'nullable|integer|exists:labels,id',
            'mode' => 'in:skip,replace', // skip = don't overwrite, replace = delete existing first
        ]);

        $fromProfile = $validated['from_profile'];
        $toProfile = $validated['to_profile'];
        $labelId = $validated['label_id'] ?? null;
        $mode = $validated['mode'] ?? 'skip';

        // Fetch source chores
        $query = Chore::where('profile', $fromProfile)
            ->orderBy('order')->orderBy('time');

        if ($labelId === null) {
            $query->whereNull('label_id');
        } else {
            $query->where('label_id', $labelId);
        }

        $sourceChores = $query->get();

        if ($sourceChores->isEmpty()) {
            return response()->json(['message' => 'No chores found in source group.'], 404);
        }

        // If replace mode, remove the target group first
        if ($mode === 'replace') {
            $deleteQuery = Chore::where('profile', $toProfile);
            if ($labelId === null) {
                $deleteQuery->whereNull('label_id');
            } else {
                $deleteQuery->where('label_id', $labelId);
            }
            $deleteQuery->delete();
        }

        // Clone each chore
        $cloned = 0;
        foreach ($sourceChores as $index => $source) {
            // In skip mode, don't duplicate by title
            if ($mode === 'skip') {
                $exists = Chore::where('profile', $toProfile)
                    ->where('title', $source->title)
                    ->where('label_id', $source->label_id)
                    ->exists();
                if ($exists) {
                    continue;
                }
            }

            Chore::create([
                'title' => $source->title,
                'profile' => $toProfile,
                'time' => $source->time,
                'days' => $source->days,
                'reward' => $source->reward,
                'label_id' => $source->label_id,
                'order' => $source->order ?? $index,
            ]);
            $cloned++;
        }

        return response()->json([
            'cloned' => $cloned,
            'skipped' => $sourceChores->count() - $cloned,
            'message' => "Cloned {$cloned} chore(s) to {$toProfile}.",
        ]);
    }
}
