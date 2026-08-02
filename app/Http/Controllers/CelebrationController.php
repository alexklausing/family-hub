<?php

namespace App\Http\Controllers;

use App\Models\Celebration;
use Illuminate\Http\Request;

class CelebrationController extends Controller
{
    public function index()
    {
        return response()->json(Celebration::orderBy('is_active', 'desc')->orderBy('updated_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:255',
            'background' => 'required|string|max:50',
            'font' => 'required|string|max:50',
            'font_color' => 'required|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        $celebration = Celebration::create($validated);

        return response()->json($celebration, 201);
    }

    public function update(Request $request, Celebration $celebration)
    {
        $validated = $request->validate([
            'message' => 'sometimes|required|string|max:255',
            'background' => 'sometimes|required|string|max:50',
            'font' => 'sometimes|required|string|max:50',
            'font_color' => 'sometimes|required|string|max:20',
            'is_active' => 'sometimes|boolean',
        ]);

        $celebration->update($validated);

        return response()->json($celebration);
    }

    public function destroy(Celebration $celebration)
    {
        $celebration->delete();

        return response()->json(null, 204);
    }
}
