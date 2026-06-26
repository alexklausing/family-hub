<?php

namespace App\Services;

use App\Models\BonusReward;
use App\Models\BonusRewardGrant;
use App\Models\ChoreCompletion;
use App\Models\RewardLedger;
use Carbon\Carbon;

class BonusRewardService
{
    /**
     * Evaluate if a chore completion triggers any bonus rewards.
     * Call this when a chore completion is created (status = pending/approved).
     */
    public function evaluateForChore(ChoreCompletion $completion)
    {
        $chore = $completion->chore;
        if (! $chore) {
            return;
        }

        // Get all rules that apply to this chore OR its label
        $rules = BonusReward::where(function ($query) use ($chore) {
            $query->where('chore_id', $chore->id)
                ->orWhere(function ($q) use ($chore) {
                    if ($chore->label_id) {
                        $q->where('label_id', $chore->label_id);
                    } else {
                        $q->whereRaw('1 = 0'); // false condition
                    }
                });
        })->get();

        foreach ($rules as $rule) {
            $this->evaluateRule($rule, $chore->profile, $completion->date);
        }
    }

    /**
     * Evaluate a specific rule for a profile on a given date.
     */
    protected function evaluateRule(BonusReward $rule, string $profile, string $dateString)
    {
        $date = Carbon::parse($dateString);
        $dayOfWeek = $date->dayOfWeek; // 0 = Sunday, 6 = Saturday

        $requiredDays = $rule->required_days; // e.g. [0, 1, 2, 3, 4]

        // Only evaluate if the current day is the LAST day in the required_days array
        // Or actually, just evaluate if the current day is IN the required_days array
        // However, if we evaluate on every day, we might grant it too early if they do Thu before Wed?
        // Usually, checking if the current day is the MAXIMUM day of the sequence is easiest for standard Sun-Thu streaks.
        // Wait, what if they complete Thursday first, then Wednesday?
        // Safer: Evaluate if ALL required days are completed for the CURRENT WEEK.

        // Determine the start of the week for this date (e.g. Sunday)
        // Carbon's startOfWeek defaults to Monday unless configured. Let's force Sunday as start of week.
        $startOfWeek = $date->copy()->startOfWeek(Carbon::SUNDAY);

        // Has this rule already been granted for this week?
        $alreadyGranted = BonusRewardGrant::where('bonus_reward_id', $rule->id)
            ->where('profile', $profile)
            ->where('week_start_date', $startOfWeek->toDateString())
            ->exists();

        if ($alreadyGranted) {
            return; // Already granted this week
        }

        // Check if all required days are completed
        $allCompleted = true;
        foreach ($requiredDays as $reqDay) {
            // Find the date for this required day in the current week
            $reqDate = $startOfWeek->copy()->addDays($reqDay)->toDateString();

            // Check if there is a completed chore on this date that matches the rule
            $hasCompletion = ChoreCompletion::where('date', $reqDate)
                ->where('status', '!=', 'rejected') // pending or approved
                ->whereHas('chore', function ($query) use ($rule, $profile) {
                    $query->where('profile', $profile);
                    if ($rule->chore_id) {
                        $query->where('id', $rule->chore_id);
                    } elseif ($rule->label_id) {
                        $query->where('label_id', $rule->label_id);
                    }
                })->exists();

            if (! $hasCompletion) {
                $allCompleted = false;
                break;
            }
        }

        if ($allCompleted) {
            // Grant the reward!
            $this->grantReward($rule, $profile, $startOfWeek);
        }
    }

    protected function grantReward(BonusReward $rule, string $profile, Carbon $startOfWeek)
    {
        // 1. Record the grant
        BonusRewardGrant::create([
            'bonus_reward_id' => $rule->id,
            'profile' => $profile,
            'week_start_date' => $startOfWeek->toDateString(),
        ]);

        // 2. Add to Ledger
        $val = trim($rule->reward_value);
        $type = preg_match('/^\$([\d\.]+)$/', $val, $matches) ? 'monetary' : 'textual';
        $amount = $type === 'monetary' ? (float) $matches[1] : 1;
        $rewardText = $type === 'textual' ? $val : null;

        $expiresAt = $rule->expires_in_days ? Carbon::now()->addDays($rule->expires_in_days) : null;
        $status = $rule->requires_approval ? 'pending' : 'approved';

        RewardLedger::create([
            'profile' => $profile,
            'type' => $type,
            'amount' => $amount,
            'reward_text' => $rewardText,
            'source' => 'bonus_reward',
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }
}
