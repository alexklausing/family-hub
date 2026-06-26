<?php

namespace App\Http\Controllers;

use App\Models\Chore;
use App\Models\ChoreCompletion;
use App\Models\RewardLedger;
use App\Services\BonusRewardService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ChoreController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->query('date', Carbon::today()->toDateString());
        $profile = $request->query('profile', 'Family');

        $chores = Chore::with(['label.bonusReward', 'bonusReward', 'completions' => function ($query) use ($date) {
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
            'is_bankable' => 'boolean',
            'label_id' => 'nullable|integer|exists:labels,id',
            'bonus_reward' => 'nullable|array',
            'bonus_reward.required_days' => 'required_with:bonus_reward|array',
            'bonus_reward.reward_value' => 'required_with:bonus_reward|string',
            'bonus_reward.expires_in_days' => 'nullable|integer',
            'bonus_reward.requires_approval' => 'boolean',
        ]);

        $chore = Chore::create($validated);

        if (! empty($validated['bonus_reward'])) {
            $chore->bonusReward()->create([
                'profile' => $chore->profile,
                'required_days' => $validated['bonus_reward']['required_days'],
                'reward_value' => $validated['bonus_reward']['reward_value'],
                'expires_in_days' => $validated['bonus_reward']['expires_in_days'] ?? null,
                'requires_approval' => $validated['bonus_reward']['requires_approval'] ?? true,
            ]);
        }

        return response()->json($chore->load('bonusReward'), 201);
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
            'is_bankable' => 'boolean',
            'label_id' => 'nullable|integer|exists:labels,id',
            'bonus_reward' => 'nullable|array',
            'bonus_reward.required_days' => 'required_with:bonus_reward|array',
            'bonus_reward.reward_value' => 'required_with:bonus_reward|string',
            'bonus_reward.expires_in_days' => 'nullable|integer',
            'bonus_reward.requires_approval' => 'boolean',
        ]);

        $chore->update($validated);

        if ($request->has('bonus_reward')) {
            if (empty($validated['bonus_reward'])) {
                $chore->bonusReward()->delete();
            } else {
                $chore->bonusReward()->updateOrCreate(
                    ['chore_id' => $chore->id],
                    [
                        'profile' => $chore->profile,
                        'required_days' => $validated['bonus_reward']['required_days'],
                        'reward_value' => $validated['bonus_reward']['reward_value'],
                        'expires_in_days' => $validated['bonus_reward']['expires_in_days'] ?? null,
                        'requires_approval' => $validated['bonus_reward']['requires_approval'] ?? true,
                    ]
                );
            }
        }

        return response()->json($chore->load('bonusReward'));
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

            // Revoke label reward if applicable
            $chore->loadMissing('label');
            if ($chore->label && ! empty($chore->label->reward)) {
                RewardLedger::where('profile', $chore->profile)
                    ->where('source', 'label_reward')
                    ->where(function ($q) use ($chore) {
                        $q->where('reward_text', $chore->label->name)
                            ->orWhere('reward_text', 'like', "%|{$chore->label->name}");
                    })
                    ->whereDate('created_at', $date)
                    ->delete();
            }

            return response()->json(['completed' => false]);
        } else {
            $chore->loadMissing('label');
            $reward = $chore->reward;

            $val = trim($reward ?? '');
            $isMonetary = preg_match('/^\$([\d\.]+)$/', $val, $matches);
            $amount = $isMonetary ? (float) $matches[1] : 1;

            $autoApprove = false;
            if (!empty($reward)) {
                if (!$chore->is_bankable && !$isMonetary) {
                    $autoApprove = true;
                }
            }

            $status = (empty($reward) || $autoApprove) ? 'approved' : 'pending';

            $completion = ChoreCompletion::create([
                'chore_id' => $chore->id,
                'date' => $date,
                'status' => $status,
                'awarded_value' => $autoApprove ? $reward : null,
            ]);

            app(BonusRewardService::class)->evaluateForChore($completion);

            if ($autoApprove && !empty($reward)) {
                $ledger = RewardLedger::create([
                    'profile' => $chore->profile,
                    'type' => $isMonetary ? 'monetary' : 'textual',
                    'amount' => $amount,
                    'reward_text' => $isMonetary ? null : $reward,
                    'source' => 'chore_completion',
                    'chore_completion_id' => $completion->id,
                    'status' => 'approved',
                    'expires_at' => !$chore->is_bankable ? Carbon::parse($date)->endOfDay() : null,
                ]);
                $ledger->created_at = Carbon::parse($date)->startOfDay();
                $ledger->save();
            }

            // Check if label reward should be awarded
            if ($chore->label && ! empty($chore->label->reward)) {
                $dayOfWeek = Carbon::parse($date)->dayOfWeek;

                // Find all active chores for this profile, label, and day of week
                $labelChores = Chore::where('profile', $chore->profile)
                    ->where('label_id', $chore->label_id)
                    ->where('is_active', true)
                    ->get()
                    ->filter(function ($c) use ($dayOfWeek) {
                        return empty($c->days) || in_array($dayOfWeek, $c->days);
                    });

                if ($labelChores->isNotEmpty()) {
                    // Check if all are completed
                    $completedCount = ChoreCompletion::whereIn('chore_id', $labelChores->pluck('id'))
                        ->where('date', $date)
                        ->where('status', '!=', 'rejected')
                        ->count();

                    if ($completedCount === $labelChores->count()) {
                        // Check if it already exists for today
                        $exists = RewardLedger::where('profile', $chore->profile)
                            ->where('source', 'label_reward')
                            ->where(function ($q) use ($chore) {
                                $q->where('reward_text', $chore->label->name)
                                    ->orWhere('reward_text', 'like', "%|{$chore->label->name}");
                            })
                            ->whereDate('created_at', $date)
                            ->exists();

                        if (! $exists) {
                            $val = trim($chore->label->reward);
                            $type = preg_match('/^\$([\d\.]+)$/', $val, $matches) ? 'monetary' : 'textual';
                            $amount = $type === 'monetary' ? (float) $matches[1] : 1;

                            // Store formatted string if textual, otherwise just label name
                            $rewardText = $type === 'textual' ? "{$val}|{$chore->label->name}" : $chore->label->name;

                            // All monetary rewards must be approved (pending).
                            // Non-bankable textual rewards get banked automatically.
                            $labelIsBankable = $chore->label->is_bankable ?? true;
                            $ledgerStatus = (!$labelIsBankable && $type !== 'monetary') ? 'approved' : 'pending';

                            $ledger = RewardLedger::create([
                                'profile' => $chore->profile,
                                'type' => $type,
                                'amount' => $amount,
                                'reward_text' => $rewardText,
                                'source' => 'label_reward',
                                'status' => $ledgerStatus,
                                'expires_at' => !$labelIsBankable ? Carbon::parse($date)->endOfDay() : null,
                            ]);

                            // Explicitly set created_at to the completion date
                            $ledger->created_at = Carbon::parse($date)->startOfDay();
                            $ledger->save();
                        }
                    }
                }
            }

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
                'is_bankable' => $source->is_bankable,
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

    public function approvals()
    {
        $pendingChores = ChoreCompletion::with('chore')
            ->where('status', 'pending')
            ->get();

        $pendingBonus = RewardLedger::where('status', 'pending')
            ->where('source', 'bonus_reward')
            ->get()
            ->map(function ($ledger) {
                return [
                    'id' => 'bonus_'.$ledger->id,
                    'is_bonus' => true,
                    'date' => $ledger->created_at->toDateString(),
                    'awarded_value' => $ledger->type === 'monetary' ? '$'.number_format($ledger->amount, 2) : $ledger->reward_text,
                    'chore' => [
                        'title' => 'Bonus Reward: Streak Completed!',
                        'profile' => $ledger->profile,
                    ],
                ];
            });

        $pendingLabels = RewardLedger::where('status', 'pending')
            ->where('source', 'label_reward')
            ->get()
            ->map(function ($ledger) {
                $labelName = 'Label';
                $awardedValue = '';

                if ($ledger->type === 'monetary') {
                    $labelName = $ledger->reward_text ?: 'Label';
                    $awardedValue = '$'.number_format($ledger->amount, 2);
                } else {
                    $parts = explode('|', $ledger->reward_text);
                    if (count($parts) === 2) {
                        $awardedValue = $parts[0];
                        $labelName = $parts[1];
                    } else {
                        $awardedValue = $ledger->reward_text;
                    }
                }

                return [
                    'id' => 'label_'.$ledger->id,
                    'is_label' => true,
                    'date' => $ledger->created_at->toDateString(),
                    'awarded_value' => $awardedValue,
                    'chore' => [
                        'title' => "Label Reward: {$labelName} Completed!",
                        'profile' => $ledger->profile,
                    ],
                ];
            });

        $combinedBonus = $pendingBonus->concat($pendingLabels);

        return response()->json($pendingChores->concat($combinedBonus)->sortByDesc('date')->values());
    }

    public function processApproval(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'awarded_value' => 'nullable|string',
        ]);

        if (str_starts_with($id, 'bonus_') || str_starts_with($id, 'label_')) {
            $prefix = str_starts_with($id, 'bonus_') ? 'bonus_' : 'label_';
            $ledgerId = str_replace($prefix, '', $id);
            $ledger = RewardLedger::findOrFail($ledgerId);

            if ($validated['action'] === 'approve') {
                if ($ledger->source === 'label_reward' && $ledger->type === 'textual') {
                    $parts = explode('|', $ledger->reward_text);
                    $cleanRewardText = count($parts) === 2 ? $parts[0] : $ledger->reward_text;
                    $ledger->update([
                        'status' => 'approved',
                        'reward_text' => $cleanRewardText,
                    ]);
                } else {
                    $ledger->update(['status' => 'approved']);
                }
            } else {
                $ledger->update(['status' => 'rejected']);
            }

            return response()->json(['message' => 'Reward processed']);
        }

        $completion = ChoreCompletion::findOrFail($id);

        if ($validated['action'] === 'reject') {
            $completion->update(['status' => 'rejected']);

            return response()->json(['message' => 'Chore rejected']);
        }

        $completion->update([
            'status' => 'approved',
            'awarded_value' => $validated['awarded_value'] ?? null,
        ]);

        // Determine if chore is bankable
        $isBankable = $completion->chore->is_bankable;
        if ($completion->chore->label && $completion->chore->label->is_bankable === false) {
            $isBankable = false;
        }

        // If there's a reward and it is bankable, create a ledger entry
        if ($completion->awarded_value && $isBankable) {
            $val = trim($completion->awarded_value);
            // check if monetary (e.g. "$5.00" or "$5")
            if (preg_match('/^\$([\d\.]+)$/', $val, $matches)) {
                RewardLedger::create([
                    'profile' => $completion->chore->profile,
                    'type' => 'monetary',
                    'amount' => (float) $matches[1],
                    'source' => 'chore_completion',
                    'chore_completion_id' => $completion->id,
                ]);
            } else {
                RewardLedger::create([
                    'profile' => $completion->chore->profile,
                    'type' => 'textual',
                    'amount' => 1, // 1 instance of this textual reward
                    'reward_text' => $val,
                    'source' => 'chore_completion',
                    'chore_completion_id' => $completion->id,
                ]);
            }
        }

        return response()->json(['message' => 'Chore approved']);
    }
}
