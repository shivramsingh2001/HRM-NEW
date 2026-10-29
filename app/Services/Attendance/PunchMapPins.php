<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePunch;

/**
 * Clock In / Clock Out pins for the Attendance Sessions route map: one pin per
 * active punch that carries GPS (biometric / manual punches have none). A
 * clock-in with no clock-out after it is `open` — the map draws it blinking.
 * Days from before attendance_punches existed fall back to the day row's own
 * clock_in_* / clock_out_* coordinates.
 */
class PunchMapPins
{
    /**
     * @return list<array{type:string, time:?string, lat:float, long:float, address:?string, open:bool}>
     */
    public function forAttendance(Attendance $attendance): array
    {
        $punches = AttendancePunch::active()
            ->where('attendance_id', $attendance->id)
            ->orderBy('punched_at')
            ->get(['direction', 'punched_at', 'lat', 'long', 'address']);

        // The legacy day-row columns are not in a migration, so read them by name.
        $day = fn (string $column) => $attendance->getAttribute($column);

        $lastIn = $punches->last()?->direction === 'in' && ! $day('clock_out') ? $punches->last() : null;

        $pins = $punches
            ->filter(fn ($p) => $this->hasFix($p->lat, $p->long))
            ->map(fn ($p) => $this->pin($p->direction, $p->punched_at->format('Y-m-d H:i:s'), $p->lat, $p->long, $p->address, $p === $lastIn))
            ->values()
            ->all();

        if ($punches->isEmpty()) {
            if ($this->hasFix($day('clock_in_lat'), $day('clock_in_long'))) {
                $pins[] = $this->pin('in', $day('clock_in'), $day('clock_in_lat'), $day('clock_in_long'), $day('clock_in_address'), ! $day('clock_out'));
            }
            if ($day('clock_out') && $this->hasFix($day('clock_out_lat'), $day('clock_out_long'))) {
                $pins[] = $this->pin('out', $day('clock_out'), $day('clock_out_lat'), $day('clock_out_long'), $day('clock_out_address'), false);
            }
        }

        return $pins;
    }

    private function hasFix($lat, $long): bool
    {
        return is_numeric($lat) && is_numeric($long) && ! ((float) $lat == 0.0 && (float) $long == 0.0);
    }

    private function pin(string $type, $time, $lat, $long, ?string $address, bool $open): array
    {
        return [
            'type' => $type,
            'time' => $time ? (string) $time : null,
            'lat' => (float) $lat,
            'long' => (float) $long,
            'address' => $address,
            'open' => $open,
        ];
    }
}
