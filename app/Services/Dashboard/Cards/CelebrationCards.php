<?php

namespace App\Services\Dashboard\Cards;

use App\Models\User;
use Carbon\Carbon;

/**
 * Dashboard — Upcoming birthdays and work anniversaries.
 * Moved out of DashboardController unchanged (code-quality plan, Phase 2).
 */
class CelebrationCards
{
    public function upcomingBirthdays()
    {
        $today = Carbon::today();

        $users = User::join('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->whereNotNull('user_basic_details.dob')
            ->where('users.status', 1)
            ->select('users.id', 'users.name', 'user_basic_details.dob', 'user_basic_details.profile_image')
            ->get();

        $birthdays = [];

        foreach ($users as $user) {
            if ($user->dob) {
                $dob = Carbon::parse($user->dob);
                $nextBirthday = Carbon::create($today->year, $dob->month, $dob->day);

                if ($nextBirthday->lt($today)) {
                    $nextBirthday->addYear();
                }

                $daysUntil = (int) $today->diffInDays($nextBirthday); // whole days (Carbon 3 returns a float)

                if ($daysUntil <= 30) {
                    $birthdays[] = [
                        'user' => $user,
                        'date' => $nextBirthday,
                        'days_until' => $daysUntil,
                        'age' => $dob->age + ($nextBirthday->year - $today->year),
                    ];
                }
            }
        }

        usort($birthdays, function ($a, $b) {
            return $a['days_until'] <=> $b['days_until'];
        });

        return array_slice($birthdays, 0, 5);
    }

    public function upcomingAnniversaries()
    {
        $today = Carbon::today();

        $users = User::join('user_job_details', 'users.id', '=', 'user_job_details.user_id')
            ->whereNotNull('user_job_details.joining_date')
            ->where('users.status', 1)
            ->select('users.id', 'users.name', 'user_job_details.joining_date as joined_on', 'user_basic_details.profile_image') // alias: User::getJoiningDateAttribute() would lazy-load jobDetails per row
            ->leftJoin('user_basic_details', 'users.id', '=', 'user_basic_details.user_id')
            ->get();

        $anniversaries = [];

        foreach ($users as $user) {
            if ($user->joined_on) {
                $joining = Carbon::parse($user->joined_on);
                $nextAnniversary = Carbon::create($today->year, $joining->month, $joining->day);

                if ($nextAnniversary->lt($today)) {
                    $nextAnniversary->addYear();
                }

                $daysUntil = (int) $today->diffInDays($nextAnniversary); // whole days (Carbon 3 returns a float)
                $years = $nextAnniversary->year - $joining->year;

                if ($daysUntil <= 30 && $years >= 1) { // no "0-year" anniversary for this year's joiners
                    $anniversaries[] = [
                        'user' => $user,
                        'date' => $nextAnniversary,
                        'days_until' => $daysUntil,
                        'years' => $years,
                    ];
                }
            }
        }

        usort($anniversaries, function ($a, $b) {
            return $a['days_until'] <=> $b['days_until'];
        });

        return array_slice($anniversaries, 0, 5);
    }
}
