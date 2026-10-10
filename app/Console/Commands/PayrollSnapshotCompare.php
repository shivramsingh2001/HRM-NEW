<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Compares two payroll:snapshot files (storage/app/payroll-snapshots/{label}.json)
 * and lists every value that differs. Exit code 1 when anything differs.
 *
 *   php artisan payroll:snapshot-compare before after
 */
class PayrollSnapshotCompare extends Command
{
    protected $signature = 'payroll:snapshot-compare {before} {after} {--max=200 : Max differences to print}';

    protected $description = 'Diff two payroll golden-master snapshots';

    private array $diffs = [];

    public function handle(): int
    {
        $load = function (string $label) {
            $file = storage_path('app/payroll-snapshots/'.$label.'.json');
            if (! is_file($file)) {
                $this->error("No snapshot {$file}");

                return null;
            }

            return json_decode(file_get_contents($file), true);
        };
        $a = $load($this->argument('before'));
        $b = $load($this->argument('after'));
        if ($a === null || $b === null) {
            return self::FAILURE;
        }

        $this->walk($a['results'] ?? [], $b['results'] ?? [], '');

        if ($this->diffs === []) {
            $this->info('Identical: '.count($a['results'] ?? []).' runs compared, no differences.');

            return self::SUCCESS;
        }

        foreach (array_slice($this->diffs, 0, (int) $this->option('max')) as $d) {
            $this->line($d);
        }
        $this->error(count($this->diffs).' difference(s).');

        return self::FAILURE;
    }

    private function walk($a, $b, string $path): void
    {
        if (is_array($a) && is_array($b)) {
            foreach (array_unique(array_merge(array_keys($a), array_keys($b))) as $k) {
                $p = $path === '' ? (string) $k : $path.'.'.$k;
                if (! array_key_exists($k, $a)) {
                    $this->diffs[] = "+ {$p} = ".json_encode($b[$k], JSON_UNESCAPED_UNICODE);
                } elseif (! array_key_exists($k, $b)) {
                    $this->diffs[] = "- {$p} = ".json_encode($a[$k], JSON_UNESCAPED_UNICODE);
                } else {
                    $this->walk($a[$k], $b[$k], $p);
                }
            }

            return;
        }
        if ($a !== $b) {
            $this->diffs[] = "~ {$path}: ".json_encode($a, JSON_UNESCAPED_UNICODE).' → '.json_encode($b, JSON_UNESCAPED_UNICODE);
        }
    }
}
