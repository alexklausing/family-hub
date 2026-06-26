<?php

namespace App\Http\Controllers;

use App\Models\ChoreCompletion;
use App\Models\RewardLedger;
use Illuminate\Http\Request;

class RewardLedgerController extends Controller
{
    /**
     * Get the current balance and history for a specific profile.
     */
    public function index(Request $request)
    {
        $profile = $request->query('profile');
        if (! $profile) {
            return response()->json(['message' => 'Profile is required'], 400);
        }

        $now = now();
        $ledgers = RewardLedger::where('profile', $profile)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $approvedLedgers = $ledgers->where('status', 'approved');
        $pendingLedgers = $ledgers->where('status', 'pending');

        // Calculate monetary balance
        $monetaryBalance = $approvedLedgers->where('type', 'monetary')->sum('amount');

        // Calculate textual rewards (sum up positive vs negative count of exact strings)
        // or we just return the raw textual rewards that are positive and not yet redeemed.
        // A simpler approach for textual rewards: Just list all unredeemed textual rewards.
        // To do this simply, we can just group by reward_text and sum amounts.
        $textualBalances = [];
        $dailyBalances = [];
        $textLedgers = $approvedLedgers->where('type', 'textual');
        foreach ($textLedgers as $item) {
            if ($item->expires_at) {
                if (! isset($dailyBalances[$item->reward_text])) {
                    $dailyBalances[$item->reward_text] = 0;
                }
                $dailyBalances[$item->reward_text] += $item->amount; // +1 for earned, -1 for redeemed
            } else {
                if (! isset($textualBalances[$item->reward_text])) {
                    $textualBalances[$item->reward_text] = 0;
                }
                $textualBalances[$item->reward_text] += $item->amount; // +1 for earned, -1 for redeemed
            }
        }

        // Filter out textual rewards that are <= 0
        $activeTextualRewards = [];
        foreach ($textualBalances as $text => $count) {
            if ($count > 0) {
                $activeTextualRewards[] = [
                    'text' => $text,
                    'count' => $count,
                ];
            }
        }

        $activeDailyRewards = [];
        foreach ($dailyBalances as $text => $count) {
            if ($count > 0) {
                $activeDailyRewards[] = [
                    'text' => $text,
                    'count' => $count,
                ];
            }
        }

        // Pending Rewards calculation
        $pendingMonetaryBalance = 0;
        $pendingTextualBalances = [];

        $pendingCompletions = ChoreCompletion::with('chore.label')
            ->whereHas('chore', function ($q) use ($profile) {
                $q->where('profile', $profile);
            })
            ->where('status', 'pending')
            ->get();

        foreach ($pendingCompletions as $completion) {
            $chore = $completion->chore;

            $isBankable = $chore->is_bankable;
            if ($chore->label && $chore->label->is_bankable === false) {
                $isBankable = false;
            }

            if (! $isBankable) {
                continue;
            }

            $reward = $chore->reward;
            if ($reward) {
                $val = trim($reward);
                if (preg_match('/^\$([\d\.]+)$/', $val, $matches)) {
                    $pendingMonetaryBalance += (float) $matches[1];
                } else {
                    if (! isset($pendingTextualBalances[$val])) {
                        $pendingTextualBalances[$val] = 0;
                    }
                    $pendingTextualBalances[$val]++;
                }
            }
        }

        // Add pending ledgers to pending balances
        $pendingMonetaryBalance += $pendingLedgers->where('type', 'monetary')->sum('amount');
        $pendingTextLedgers = $pendingLedgers->where('type', 'textual');
        foreach ($pendingTextLedgers as $item) {
            if (! isset($pendingTextualBalances[$item->reward_text])) {
                $pendingTextualBalances[$item->reward_text] = 0;
            }
            $pendingTextualBalances[$item->reward_text] += $item->amount;
        }

        $activePendingTextualRewards = [];
        foreach ($pendingTextualBalances as $text => $count) {
            if ($count > 0) {
                $activePendingTextualRewards[] = [
                    'text' => $text,
                    'count' => $count,
                ];
            }
        }

        return response()->json([
            'monetary_balance' => $monetaryBalance,
            'textual_rewards' => $activeTextualRewards,
            'daily_rewards' => $activeDailyRewards,
            'pending_monetary_balance' => $pendingMonetaryBalance,
            'pending_textual_rewards' => $activePendingTextualRewards,
            'history' => $ledgers->take(50)->values(), // recent 50
        ]);
    }

    /**
     * Redeem a reward (monetary or textual).
     */
    public function redeem(Request $request)
    {
        $validated = $request->validate([
            'profile' => 'required|string',
            'type' => 'required|in:monetary,textual',
            'amount' => 'required|numeric|min:0.01',
            'reward_text' => 'nullable|string',
        ]);

        $isDaily = $request->boolean('is_daily', false);

        // When redeeming, amount is negative
        RewardLedger::create([
            'profile' => $validated['profile'],
            'type' => $validated['type'],
            'amount' => -$validated['amount'],
            'reward_text' => $validated['reward_text'],
            'source' => 'redemption',
            'expires_at' => $isDaily ? now()->endOfDay() : null,
        ]);

        return response()->json(['message' => 'Reward redeemed successfully']);
    }
}
