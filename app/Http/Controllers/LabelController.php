<?php

namespace App\Http\Controllers;

use App\Models\Label;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function index()
    {
        return response()->json(Label::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:labels,name',
            'reward' => 'nullable|string|max:255',
        ]);

        $label = Label::create($validated);

        return response()->json($label, 201);
    }

    public function update(Request $request, Label $label)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:labels,name,'.$label->id,
            'reward' => 'nullable|string|max:255',
        ]);

        $label->update($validated);

        return response()->json($label);
    }

    public function destroy(Label $label)
    {
        $label->delete();

        return response()->json(null, 204);
    }
}
