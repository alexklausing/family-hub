<?php

namespace App\Http\Controllers;

use App\Models\Countdown;
use Illuminate\Http\Request;

class CountdownController extends Controller
{
    public function index()
    {
        return response()->json(Countdown::orderBy('target_date', 'asc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'target_date' => 'required|date',
            'icon' => 'nullable|string|max:50',
        ]);

        $countdown = Countdown::create($validated);

        return response()->json($countdown, 201);
    }

    public function update(Request $request, Countdown $countdown)
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'target_date' => 'sometimes|required|date',
            'icon' => 'nullable|string|max:50',
        ]);

        $countdown->update($validated);

        return response()->json($countdown);
    }

    public function destroy(Countdown $countdown)
    {
        $countdown->delete();
        return response()->json(null, 204);
    }
}
