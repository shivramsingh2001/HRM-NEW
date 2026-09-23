<?php

namespace App\Console\Commands;

use App\Models\FieldTrackingUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Records monthly field-tracking usage per tenant — the auditable figure the
 * external billing system reconciles against. Schedule daily.
 */
class FieldTrackingMeter extends Command
{
    protected $signature = 'field-tracking:meter
                            {--month= : Y-m (default: current month)}
                            {--tenant= : restrict to one tenant}
                            {--dry-run : compute and print, write nothing}';

    protected $description = 'Meter field-tracking seat usage per tenant per month.';

    public function handle(): int
    {
        $ym = $this->option('month') ?: now()->format('Y-m');
        $dry = (bool) $this->option('dry-run');
        [$mStart, $mEnd] = [
            Carbon::parse($ym . '-01')->startOfMonth(),
            Carbon::parse($ym . '-01')->endOfMonth()->endOfDay(),
        ];

        $tenants = DB::table('tenants')
            ->when($this->option('tenant'), fn ($q) => $q->where('id', $this->option('tenant')))
            ->where(function ($q) {
                $q->where('field_tracking_enabled', 1)
                    ->orWhereExists(function ($sub) {
                        $sub->select(DB::raw(1))
                            ->from('user_job_details as jd')
                            ->join('users as u', 'u.id', '=', 'jd.user_id')
                            ->whereColumn('u.tenant_id', 'tenants.id')
                            ->where('jd.location_tracking_enabled', 1);
                    });
            })
            ->get(['id', 'company_name', 'field_tracking_seats']);

        if ($tenants->isEmpty()) {
            $this->info('No tenants with field tracking.');

            return self::SUCCESS;
        }

        $rows = [];
        foreach ($tenants as $t) {
            $enabledIds = DB::table('user_job_details as jd')
                ->join('users as u', 'u.id', '=', 'jd.user_id')
                ->where('u.tenant_id', $t->id)
                ->where('u.status', 1)
                ->where('jd.location_tracking_enabled', 1)
                ->pluck('u.id')->all();

            $nowUsed = count($enabledIds);
            $trackRows = $enabledIds
                ? DB::table('attendance_tracking_points')
                    ->where('tenant_id', $t->id)
                    ->whereIn('user_id', $enabledIds)
                    ->whereBetween('track_time', [$mStart, $mEnd])
                    ->count()
                : 0;

            $rows[] = [$t->id, $t->company_name, $t->field_tracking_seats, $nowUsed, $trackRows];

            if ($dry) {
                continue;
            }

            $existing = FieldTrackingUsage::where('tenant_id', $t->id)->where('year_month', $ym)->first();
            $prevAvg = (float) ($existing->avg_seats_used ?? 0);
            $prevSamples = (int) ($existing->samples ?? 0);
            $prevPeak = (int) ($existing->peak_seats_used ?? 0);

            FieldTrackingUsage::updateOrCreate(
                ['tenant_id' => $t->id, 'year_month' => $ym],
                [
                    'seats_purchased' => (int) $t->field_tracking_seats,
                    'peak_seats_used' => max($prevPeak, $nowUsed),
                    'avg_seats_used' => round(($prevAvg * $prevSamples + $nowUsed) / ($prevSamples + 1), 2),
                    'samples' => $prevSamples + 1,
                    'enabled_user_ids' => $enabledIds,
                    'track_rows_written' => $trackRows,
                    'computed_at' => now(),
                ]
            );
        }

        $this->table(['Tenant', 'Name', 'Seats', 'In use', 'Track rows (mo)'], $rows);

        // Finalise the month that just ended (runs on the 1st-3rd).
        if (! $dry && now()->day <= 3) {
            $prevYm = now()->subMonthNoOverflow()->format('Y-m');
            [$pStart, $pEnd] = [
                Carbon::parse($prevYm . '-01')->startOfMonth(),
                Carbon::parse($prevYm . '-01')->endOfMonth()->endOfDay(),
            ];
            FieldTrackingUsage::where('year_month', $prevYm)->whereNull('finalized_at')
                ->get()->each(function ($u) use ($pStart, $pEnd) {
                    $ids = $u->enabled_user_ids ?: [];
                    $u->update([
                        'track_rows_written' => $ids
                            ? DB::table('attendance_tracking_points')->where('tenant_id', $u->tenant_id)
                                ->whereIn('user_id', $ids)->whereBetween('track_time', [$pStart, $pEnd])->count()
                            : 0,
                        'seats_purchased' => (int) (DB::table('tenants')->where('id', $u->tenant_id)->value('field_tracking_seats') ?? $u->seats_purchased),
                        'finalized_at' => now(),
                    ]);
                });
            $this->info("Finalised {$prevYm}.");
        }

        if ($dry) {
            $this->warn('Dry run — nothing written.');
        }

        return self::SUCCESS;
    }
}
