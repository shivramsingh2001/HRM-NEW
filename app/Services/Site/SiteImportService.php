<?php

namespace App\Services\Site;

use App\Models\Site;
use App\Models\User;
use App\Support\Spreadsheet\SpreadsheetReader;
use App\Support\Spreadsheet\SpreadsheetWriter;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Sites → Import from Excel / CSV, in two steps:
 *   1. preview() reads and checks every row and keeps the result for 30
 *      minutes under a token (valid rows ready to save, failed rows with
 *      their reasons);
 *   2. confirm() saves the valid rows through SiteService (so each one gets
 *      the next site code) — failed rows are never saved, and can be
 *      downloaded with errorFile() to fix and upload again.
 * Site codes are never read from the file; they are always generated.
 */
class SiteImportService
{
    public const MAX_ROWS = 2000;

    private const CACHE_MINUTES = 30;

    /** Template header, in order (* = required). */
    public const COLUMNS = ['Site Name*', 'State*', 'District', 'Address*', 'Location', 'Latitude', 'Longitude', 'Onboarding Date'];

    /** Header aliases (lower-cased, spaces/underscores/dots/dashes/* removed) → field. */
    private const HEADERS = [
        'sitename' => 'site_name', 'name' => 'site_name',
        'state' => 'state', 'statename' => 'state',
        'district' => 'district',
        'address' => 'address', 'siteaddress' => 'address',
        'location' => 'location',
        'latitude' => 'latitude', 'lat' => 'latitude',
        'longitude' => 'longitude', 'long' => 'longitude', 'lng' => 'longitude',
        'onboardingdate' => 'onboarding_date', 'onboarding' => 'onboarding_date', 'siteonboardingdate' => 'onboarding_date',
    ];

    private const FIELDS = ['site_name', 'state', 'district', 'address', 'location', 'latitude', 'longitude', 'onboarding_date'];

    private const REQUIRED_HEADERS = ['site_name' => 'Site Name', 'state' => 'State', 'address' => 'Address'];

    /** Date formats accepted for text dates (Excel date cells arrive as serial numbers). */
    private const DATE_FORMATS = ['d-m-Y', 'd/m/Y', 'd.m.Y', 'Y-m-d', 'Y/m/d', 'd-M-Y', 'd M Y', 'j-n-Y', 'j/n/Y'];

    public function __construct(
        private SiteService $sites,
        private SpreadsheetReader $reader,
        private SpreadsheetWriter $writer,
    ) {
    }

    /**
     * @return array{token:string,total:int,valid:int,invalid:int,
     *   errors:array<int,array{row:int,site_name:string,messages:array<int,string>}>,
     *   sample:array<int,array<string,mixed>>}
     */
    public function preview(UploadedFile $file, int $tenantId, int $userId): array
    {
        $rows = $this->readRows($file);
        if ($rows === []) {
            throw new SiteException('The file has no site rows.');
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new SiteException('Maximum '.self::MAX_ROWS.' sites per file.');
        }

        $states = $this->stateLookup();
        $stateNames = $this->sites->stateOptions();
        $taken = $this->existingKeys($tenantId);
        $seen = [];
        $valid = [];
        $failed = [];

        foreach ($rows as $rowNo => $row) {
            [$data, $messages] = $this->checkRow($row, $states);

            $key = $this->duplicateKey($row['site_name'], $row['address']);
            if ($row['site_name'] !== '' && $row['address'] !== '') {
                if (isset($taken[$key])) {
                    $messages[] = 'A site with this name and address already exists.';
                } elseif (isset($seen[$key])) {
                    $messages[] = "Same name and address as row {$seen[$key]}.";
                } else {
                    $seen[$key] = $rowNo;
                }
            }

            if ($messages) {
                $failed[] = ['row' => $rowNo, 'cells' => $row, 'messages' => $messages];
            } else {
                $valid[$rowNo] = $data;
            }
        }

        $token = Str::random(32);
        Cache::put($this->cacheKey($tenantId, $userId, $token), ['valid' => $valid, 'failed' => $failed], now()->addMinutes(self::CACHE_MINUTES));

        return [
            'token' => $token,
            'total' => count($rows),
            'valid' => count($valid),
            'invalid' => count($failed),
            'errors' => array_map(fn ($f) => ['row' => $f['row'], 'site_name' => $f['cells']['site_name'], 'messages' => $f['messages']], array_slice($failed, 0, 200)),
            'sample' => array_map(fn ($rowNo, $d) => [
                'row' => $rowNo, 'site_name' => $d['site_name'], 'state' => $stateNames[$d['state_code']] ?? '',
                'district' => $d['district'], 'address' => $d['address'],
                'onboarding_date' => $d['onboarding_date'] ? Carbon::parse($d['onboarding_date'])->format('d M Y') : null,
            ], array_slice(array_keys($valid), 0, 10), array_slice($valid, 0, 10)),
        ];
    }

    /** @return array{created:int,skipped:int,first_code:?string,last_code:?string} */
    public function confirm(string $token, int $tenantId, User $actor): array
    {
        $key = $this->cacheKey($tenantId, (int) $actor->id, $token);
        $payload = Cache::get($key);
        if (! is_array($payload)) {
            throw new SiteException('This preview has expired. Please upload the file again.');
        }
        if ($payload['valid'] === []) {
            throw new SiteException('There are no valid rows to import.');
        }

        // Someone may have added the same site since the preview — skip those, never duplicate.
        $taken = $this->existingKeys($tenantId);
        $created = [];
        $skipped = 0;

        DB::transaction(function () use ($payload, $tenantId, $actor, &$taken, &$created, &$skipped) {
            foreach ($payload['valid'] as $data) {
                $dupKey = $this->duplicateKey($data['site_name'], $data['address']);
                if (isset($taken[$dupKey])) {
                    $skipped++;
                    continue;
                }
                $created[] = $this->sites->create($tenantId, $data + ['status' => 'active'], $actor)->site_code;
                $taken[$dupKey] = true;
            }
        });

        // Failed rows stay downloadable; the valid ones can't be imported twice.
        Cache::put($key, ['valid' => [], 'failed' => $payload['failed']], now()->addMinutes(self::CACHE_MINUTES));

        return [
            'created' => count($created),
            'skipped' => $skipped,
            'first_code' => $created[0] ?? null,
            'last_code' => $created ? end($created) : null,
        ];
    }

    /** Path of a temporary .xlsx holding the failed rows plus an "Errors" column (caller deletes it). */
    public function errorFile(string $token, int $tenantId, int $userId): string
    {
        $payload = Cache::get($this->cacheKey($tenantId, $userId, $token));
        if (! is_array($payload) || $payload['failed'] === []) {
            throw new SiteException('There are no failed rows to download, or the preview has expired.');
        }

        $rows = [array_merge(self::COLUMNS, ['Errors'])];
        foreach ($payload['failed'] as $f) {
            $rows[] = array_merge(array_map(fn ($field) => $f['cells'][$field], self::FIELDS), [implode(' ', $f['messages'])]);
        }

        return $this->writer->write(['Sites' => $rows]);
    }

    /** Path of a temporary .xlsx template: a "Sites" sheet to fill and a "States" sheet of accepted names. */
    public function templateFile(): string
    {
        $states = [['State']];
        foreach ($this->sites->stateOptions() as $name) {
            $states[] = [$name];
        }

        return $this->writer->write([
            'Sites' => [
                self::COLUMNS,
                ['Sector 62 Office', 'Uttar Pradesh', 'Gautam Buddha Nagar', 'A-12, Sector 62, Noida', 'Near Metro Station', '28.6270', '77.3727', '15-10-2026'],
            ],
            'States' => $states,
        ]);
    }

    /**
     * Data rows keyed by sheet row number (header = row 1). Blank rows are skipped.
     *
     * @return array<int,array<string,string>>
     */
    private function readRows(UploadedFile $file): array
    {
        try {
            $sheet = $this->reader->read($file);
        } catch (RuntimeException $e) {
            throw new SiteException($e->getMessage());
        }
        if ($sheet === []) {
            return [];
        }

        $header = array_shift($sheet);
        $map = [];
        foreach ($header as $col => $title) {
            $key = preg_replace('/[\s_.\-*]+/', '', strtolower(trim((string) $title)));
            if (isset(self::HEADERS[$key]) && ! in_array(self::HEADERS[$key], $map, true)) {
                $map[$col] = self::HEADERS[$key];
            }
        }
        $missing = array_diff_key(self::REQUIRED_HEADERS, array_flip($map));
        if ($missing) {
            throw new SiteException('The header row is missing: '.implode(', ', $missing).'. Download the template to see the columns.');
        }

        $rows = [];
        foreach ($sheet as $i => $cells) {
            $row = array_fill_keys(self::FIELDS, '');
            foreach ($map as $col => $field) {
                $row[$field] = trim(preg_replace('/\s+/u', ' ', (string) ($cells[$col] ?? '')));
            }
            if (implode('', $row) === '') {
                continue;
            }
            $rows[$i + 2] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string,string> $row
     * @param array<string,int> $states
     * @return array{0:array<string,mixed>,1:array<int,string>} [site data, error messages]
     */
    private function checkRow(array $row, array $states): array
    {
        $messages = [];

        if ($row['site_name'] === '') {
            $messages[] = 'Site Name is required.';
        } elseif (mb_strlen($row['site_name']) > 191) {
            $messages[] = 'Site Name is longer than 191 characters.';
        }
        if ($row['address'] === '') {
            $messages[] = 'Address is required.';
        } elseif (mb_strlen($row['address']) > 500) {
            $messages[] = 'Address is longer than 500 characters.';
        }

        $stateCode = null;
        if ($row['state'] === '') {
            $messages[] = 'State is required.';
        } else {
            $stateCode = $states[$this->stateKey($row['state'])] ?? null;
            if ($stateCode === null) {
                $messages[] = "State \"{$row['state']}\" is not in the list (see the States sheet of the template).";
            }
        }

        if (mb_strlen($row['district']) > 120) {
            $messages[] = 'District is longer than 120 characters.';
        }
        if (mb_strlen($row['location']) > 191) {
            $messages[] = 'Location is longer than 191 characters.';
        }

        [$lat, $long] = [$row['latitude'], $row['longitude']];
        if (($lat === '') !== ($long === '')) {
            $messages[] = 'Fill both Latitude and Longitude, or leave both empty.';
        } elseif ($lat !== '') {
            if (! is_numeric($lat) || abs((float) $lat) > 90) {
                $messages[] = 'Latitude must be a number between -90 and 90.';
            }
            if (! is_numeric($long) || abs((float) $long) > 180) {
                $messages[] = 'Longitude must be a number between -180 and 180.';
            }
        }

        $date = null;
        if ($row['onboarding_date'] !== '') {
            $date = $this->parseDate($row['onboarding_date']);
            if ($date === null) {
                $messages[] = "Onboarding Date \"{$row['onboarding_date']}\" is not a date (use DD-MM-YYYY).";
            }
        }

        return [[
            'site_name' => $row['site_name'],
            'state_code' => $stateCode,
            'district' => $row['district'] !== '' ? $row['district'] : null,
            'address' => $row['address'],
            'location' => $row['location'] !== '' ? $row['location'] : null,
            'latitude' => $lat !== '' ? round((float) $lat, 7) : null,
            'longitude' => $long !== '' ? round((float) $long, 7) : null,
            'onboarding_date' => $date,
        ], $messages];
    }

    /** Y-m-d, or null when the cell isn't a date. Excel date cells arrive as serial day numbers. */
    private function parseDate(string $value): ?string
    {
        if (preg_match('/^\d+(\.\d+)?$/', $value)) {
            $serial = (int) floor((float) $value);

            return $serial > 0 && $serial < 2958466 ? Carbon::create(1899, 12, 30)->addDays($serial)->toDateString() : null;
        }
        foreach (self::DATE_FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }
            if ($date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    /** @return array<string,int> normalised state name / short name / code → state_code */
    private function stateLookup(): array
    {
        $lookup = [];
        foreach (DB::table('states')->where('status', 1)->get(['state_code', 'name', 'short_name']) as $s) {
            $code = (int) $s->state_code;
            $lookup[$this->stateKey((string) $s->name)] = $code;
            $lookup[(string) $code] = $code;
            if ($s->short_name) {
                $lookup += [$this->stateKey((string) $s->short_name) => $code];
            }
        }

        return $lookup;
    }

    private function stateKey(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', str_replace('&', 'and', strtolower($value)));
    }

    /** @return array<string,bool> duplicate keys of the company's current (not deleted) sites */
    private function existingKeys(int $tenantId): array
    {
        $keys = [];
        foreach (Site::where('tenant_id', $tenantId)->select(['site_name', 'address'])->cursor() as $site) {
            $keys[$this->duplicateKey((string) $site->site_name, (string) $site->address)] = true;
        }

        return $keys;
    }

    private function duplicateKey(string $name, string $address): string
    {
        $norm = fn (string $v) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', $v)));

        return $norm($name).'|'.$norm($address);
    }

    private function cacheKey(int $tenantId, int $userId, string $token): string
    {
        return "site-import:{$tenantId}:{$userId}:{$token}";
    }
}
