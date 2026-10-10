<?php

namespace App\Services\Dashboard\Cards;

/**
 * Dashboard — Assets card: how many assets are in each state right now.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class AssetCard
{
    /** Assets card: how many assets are in each state right now. */
    public function build(): array
    {
        $byStatus = \App\Models\Asset::selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');
        $n = fn (string ...$statuses) => (int) collect($statuses)->sum(fn ($s) => $byStatus[$s] ?? 0);

        return [
            'total' => (int) $byStatus->except(['retired', 'disposed'])->sum(),
            'assigned' => $n('assigned'),
            'available' => $n('available'),
            'pending_acceptance' => $n('pending_acceptance'),
            'in_repair' => $n('in_repair'),
            'damaged_lost' => $n('damaged', 'lost'),
        ];
    }
}
