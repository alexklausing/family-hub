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

        $chores = Chore::with(['label.bonusReward', 'bonusReward', 'subtasks', 'completions' => function ($query) use ($date) {
            $query->where('date', $date);
        }])
            ->where('profile', $profile)
            ->orderBy('order', 'asc')
            ->orderBy('time', 'asc')
            ->get();

        // Transform to include a simple "completed" boolean for the specific date
        $chores->transform(function ($chore) {
            $completion = $chore->completions->first();
            $subtaskIds = $chore->subtasks->pluck('id');
            $doneIds = collect($completion->completed_subtasks ?? []);

            $chore->completed = $completion
                && ($subtaskIds->isEmpty() || $doneIds->intersect($subtaskIds)->count() === $subtaskIds->count());
            $chore->completed_subtasks = $completion ? $doneIds->values() : [];

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
            'subtasks' => 'nullable|array',
            'subtasks.*.id' => 'nullable|integer',
            'subtasks.*.title' => 'required_with:subtasks|string|max:255',
            'subtasks.*.order' => 'nullable|integer',
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

        if (! empty($validated['subtasks'])) {
            $this->syncSubtasks($chore, $validated['subtasks']);
        }

        return response()->json($chore->load('bonusReward', 'subtasks'), 201);
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
            'subtasks' => 'nullable|array',
            'subtasks.*.id' => 'nullable|integer',
            'subtasks.*.title' => 'required_with:subtasks|string|max:255',
            'subtasks.*.order' => 'nullable|integer',
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

        if ($request->has('subtasks')) {
            $this->syncSubtasks($chore, $validated['subtasks'] ?? []);
        }

        return response()->json($chore->load('bonusReward', 'subtasks'));
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
            'subtask_ids' => 'nullable|array',
            'subtask_ids.*' => 'integer',
        ]);

        $date = $validated['date'];
        $chore->loadMissing(['label', 'subtasks']);

        // Chores without subtasks keep the original toggle semantics:
        // a bare request toggles completion on/off.
        if ($chore->subtasks->isEmpty()) {
            $completion = ChoreCompletion::where('chore_id', $chore->id)
                ->where('date', $date)
                ->first();

            if ($completion) {
                $completion->delete();
                $this->revokeLabelReward($chore, $date);

                return response()->json(['completed' => false]);
            }

            $this->completeChore($chore, $date);

            return response()->json(['completed' => true]);
        }

        // Chores WITH subtasks use a checklist-only interaction: the request
        // carries the full set of subtask ids completed for the date.
        $allSubtaskIds = $chore->subtasks->pluck('id');
        $subtaskIds = collect($validated['subtask_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $allSubtaskIds->contains($id))
            ->values();

        $completion = ChoreCompletion::where('chore_id', $chore->id)
            ->where('date', $date)
            ->first();

        // Nothing checked -> no completion record for the day
        if ($subtaskIds->isEmpty()) {
            if ($completion) {
                $completion->delete();
                $this->revokeCompletionRewards($chore, $completion, $date);
            }

            return response()->json(['completed' => false, 'progress' => 0]);
        }

        $allDone = $subtaskIds->count() === $allSubtaskIds->count();

        if (! $completion) {
            $completion = ChoreCompletion::create([
                'chore_id' => $chore->id,
                'date' => $date,
                'status' => $allDone ? 'approved' : 'in_progress',
                'awarded_value' => null,
                'completed_subtasks' => $subtaskIds->all(),
            ]);
        } else {
            $wasDone = $this->isFullyDone($completion, $allSubtaskIds);
            $completion->update(['completed_subtasks' => $subtaskIds->all()]);

            if (! $allDone && $wasDone) {
                // Full -> partial: revoke previously awarded rewards
                $completion->update(['status' => 'in_progress', 'awarded_value' => null]);
                $this->revokeCompletionRewards($chore, $completion, $date);
            }
        }

        if ($allDone) {
            $this->awardCompletionRewards($chore, $completion, $date);
        }

        $progress = round($subtaskIds->count() / $allSubtaskIds->count(), 2);

        return response()->json([
            'completed' => $allDone,
            'progress' => $progress,
        ]);
    }

    /**
     * Create a completion record for a chore and award any due rewards.
     */
    private function completeChore(Chore $chore, string $date): void
    {
        $completion = ChoreCompletion::create([
            'chore_id' => $chore->id,
            'date' => $date,
            'status' => 'approved',
            'awarded_value' => null,
        ]);

        $this->awardCompletionRewards($chore, $completion, $date);
    }

    /**
     * Whether a completion record covers every subtask of the chore.
     */
    private function isFullyDone(ChoreCompletion $completion, $allSubtaskIds): bool
    {
        $done = collect($completion->completed_subtasks ?? []);

        return $done->isNotEmpty()
            && $done->intersect($allSubtaskIds)->count() === $allSubtaskIds->count();
    }

    /**
     * Award individual chore, bonus, and label rewards for a full completion.
     */
    private function awardCompletionRewards(Chore $chore, ChoreCompletion $completion, string $date): void
    {
        $reward = $chore->reward;
        $val = trim($reward ?? '');
        $isMonetary = preg_match('/^\$([\d\.]+)$/', $val, $matches);
        $amount = $isMonetary ? (float) $matches[1] : 1;

        $autoApprove = false;
        if (! empty($reward) && ! $chore->is_bankable && ! $isMonetary) {
            $autoApprove = true;
        }

        $status = (empty($reward) || $autoApprove) ? 'approved' : 'pending';

        $completion->update([
            'status' => $status,
            'awarded_value' => $autoApprove ? $reward : null,
        ]);

        app(BonusRewardService::class)->evaluateForChore($completion);

        if ($autoApprove && ! empty($reward)) {
            $ledger = RewardLedger::firstOrCreate(
                ['chore_completion_id' => $completion->id, 'source' => 'chore_completion'],
                [
                    'profile' => $chore->profile,
                    'type' => $isMonetary ? 'monetary' : 'textual',
                    'amount' => $amount,
                    'reward_text' => $isMonetary ? null : $reward,
                    'status' => 'approved',
                    'expires_at' => ! $chore->is_bankable ? Carbon::parse($date)->endOfDay() : null,
                ]
            );
            $ledger->created_at = Carbon::parse($date)->startOfDay();
            $ledger->save();
        }

        if ($chore->label && ! empty($chore->label->reward)) {
            $this->awardLabelReward($chore, $date);
        }
    }

    /**
     * Award the label group reward once every chore in the group is completed.
     */
    private function awardLabelReward(Chore $chore, string $date): void
    {
        if (! $chore->label || empty($chore->label->reward)) {
            return;
        }

        $dayOfWeek = Carbon::parse($date)->dayOfWeek;

        // Find all active chores for this profile, label, and day of week
        $labelChores = Chore::where('profile', $chore->profile)
            ->where('label_id', $chore->label_id)
            ->where('is_active', true)
            ->get()
            ->filter(function ($c) use ($dayOfWeek) {
                return empty($c->days) || in_array($dayOfWeek, $c->days);
            });

        if ($labelChores->isEmpty()) {
            return;
        }

        // Check if all are completed
        $completedCount = ChoreCompletion::whereIn('chore_id', $labelChores->pluck('id'))
            ->where('date', $date)
            ->where('status', '!=', 'rejected')
            ->count();

        if ($completedCount !== $labelChores->count()) {
            return;
        }

        // Check if it already exists for today
        $exists = RewardLedger::where('profile', $chore->profile)
            ->where('source', 'label_reward')
            ->where(function ($q) use ($chore) {
                $q->where('reward_text', $chore->label->name)
                    ->orWhere('reward_text', 'like', "%|{$chore->label->name}");
            })
            ->whereDate('created_at', $date)
            ->exists();

        if ($exists) {
            return;
        }

        $val = trim($chore->label->reward);
        $type = preg_match('/^\$([\d\.]+)$/', $val, $matches) ? 'monetary' : 'textual';
        $amount = $type === 'monetary' ? (float) $matches[1] : 1;

        // Store formatted string if textual, otherwise just label name
        $rewardText = $type === 'textual' ? "{$val}|{$chore->label->name}" : $chore->label->name;

        // All monetary rewards must be approved (pending).
        // Non-bankable textual rewards get banked automatically.
        $labelIsBankable = $chore->label->is_bankable ?? true;
        $ledgerStatus = (! $labelIsBankable && $type !== 'monetary') ? 'approved' : 'pending';

        $ledger = RewardLedger::create([
            'profile' => $chore->profile,
            'type' => $type,
            'amount' => $amount,
            'reward_text' => $rewardText,
            'source' => 'label_reward',
            'status' => $ledgerStatus,
            'expires_at' => ! $labelIsBankable ? Carbon::parse($date)->endOfDay() : null,
        ]);

        // Explicitly set created_at to the completion date
        $ledger->created_at = Carbon::parse($date)->startOfDay();
        $ledger->save();
    }

    /**
     * Remove the label group reward ledger entry for a date.
     */
    private function revokeLabelReward(Chore $chore, string $date): void
    {
        if (! $chore->label || empty($chore->label->reward)) {
            return;
        }

        RewardLedger::where('profile', $chore->profile)
            ->where('source', 'label_reward')
            ->where(function ($q) use ($chore) {
                $q->where('reward_text', $chore->label->name)
                    ->orWhere('reward_text', 'like', "%|{$chore->label->name}");
            })
            ->whereDate('created_at', $date)
            ->delete();
    }

    /**
     * Remove all rewards awarded for a specific completion.
     */
    private function revokeCompletionRewards(Chore $chore, ChoreCompletion $completion, string $date): void
    {
        RewardLedger::where('chore_completion_id', $completion->id)
            ->where('source', 'chore_completion')
            ->delete();

        $this->revokeLabelReward($chore, $date);
    }

    /**
     * Persist the subtask list, preserving ids for existing rows.
     */
    private function syncSubtasks(Chore $chore, array $subtasks): void
    {
        $existing = $chore->subtasks()->get()->keyBy('id');
        $seen = [];

        foreach ($subtasks as $index => $subtask) {
            $id = $subtask['id'] ?? null;
            $data = [
                'title' => $subtask['title'],
                'order' => $subtask['order'] ?? $index,
            ];

            if ($id && $existing->has($id)) {
                $existing->get($id)->update($data);
                $seen[] = $id;
            } else {
                $model = $chore->subtasks()->create($data);
                $seen[] = $model->id;
            }
        }

        $chore->subtasks()->whereNotIn('id', $seen)->delete();
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
