<?php

namespace App\Http\Controllers;

use App\Models\Label;
use Illuminate\Http\Request;

class LabelController extends Controller
{
    public function index()
    {
        return response()->json(Label::with('bonusReward')->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:labels,name',
            'reward' => 'nullable|string|max:255',
            'is_bankable' => 'boolean',
            'bonus_reward' => 'nullable|array',
            'bonus_reward.required_days' => 'required_with:bonus_reward|array',
            'bonus_reward.reward_value' => 'required_with:bonus_reward|string',
            'bonus_reward.expires_in_days' => 'nullable|integer',
            'bonus_reward.requires_approval' => 'boolean',
        ]);

        $label = Label::create($validated);

        if (! empty($validated['bonus_reward'])) {
            $label->bonusReward()->create([
                'profile' => null,
                'required_days' => $validated['bonus_reward']['required_days'],
                'reward_value' => $validated['bonus_reward']['reward_value'],
                'expires_in_days' => $validated['bonus_reward']['expires_in_days'] ?? null,
                'requires_approval' => $validated['bonus_reward']['requires_approval'] ?? true,
            ]);
        }

        return response()->json($label->load('bonusReward'), 201);
    }

    public function update(Request $request, Label $label)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:labels,name,'.$label->id,
            'reward' => 'nullable|string|max:255',
            'is_bankable' => 'boolean',
            'bonus_reward' => 'nullable|array',
            'bonus_reward.required_days' => 'required_with:bonus_reward|array',
            'bonus_reward.reward_value' => 'required_with:bonus_reward|string',
            'bonus_reward.expires_in_days' => 'nullable|integer',
            'bonus_reward.requires_approval' => 'boolean',
        ]);

        $label->update($validated);

        if ($request->has('bonus_reward')) {
            if (empty($validated['bonus_reward'])) {
                $label->bonusReward()->delete();
            } else {
                $label->bonusReward()->updateOrCreate(
                    ['label_id' => $label->id],
                    [
                        'profile' => null,
                        'required_days' => $validated['bonus_reward']['required_days'],
                        'reward_value' => $validated['bonus_reward']['reward_value'],
                        'expires_in_days' => $validated['bonus_reward']['expires_in_days'] ?? null,
                        'requires_approval' => $validated['bonus_reward']['requires_approval'] ?? true,
                    ]
                );
            }
        }

        return response()->json($label->load('bonusReward'));
    }

    public function destroy(Label $label)
    {
        $label->delete();

        return response()->json(null, 204);
    }
}
