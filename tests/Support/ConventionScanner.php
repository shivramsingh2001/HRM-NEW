<?php

namespace Tests\Support;

/**
 * Static scans behind the convention guard tests (code-quality plan, Phase 5).
 * Pure token / text analysis of app/Http/Controllers — no database, runs in CI.
 */
class ConventionScanner
{
    /** @return array<string, string> repo-relative path => source, for every controller file */
    public static function controllers(): array
    {
        $root = dirname(__DIR__, 2);
        $base = str_replace('\\', '/', $root);
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/app/Http/Controllers', \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[substr(str_replace('\\', '/', $f->getPathname()), strlen($base) + 1)] = file_get_contents($f->getPathname());
            }
        }
        ksort($out);

        return $out;
    }

    /**
     * catch blocks that swallow the exception: their body neither logs it
     * (Log::…, logger(), report()), rethrows / throws, nor hands it to
     * something that does ($this->failed(…) style helpers that receive $e).
     * Those are how a crash turns into a silent "An error occurred".
     */
    public static function swallowedCatches(string $code): int
    {
        $tokens = token_get_all($code);
        $n = count($tokens);
        $count = 0;
        for ($i = 0; $i < $n; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_CATCH) {
                continue;
            }
            // the exception variable name
            $var = null;
            for ($j = $i; $j < $n && $tokens[$j] !== '{'; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_VARIABLE) {
                    $var = $tokens[$j][1];
                }
            }
            $depth = 0;
            $body = '';
            for ($k = $j; $k < $n; $k++) {
                $t = $tokens[$k];
                $body .= is_array($t) ? $t[1] : $t;
                if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                    $depth++;
                } elseif ($t === '}') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }
            $handled = preg_match('/\bLog::|\blogger\(|\breport\(|\bthrow\b|->(error|warning|critical)\(/', $body)
                // passed on to a helper that deals with it, e.g. $this->failed('x', $e)
                || ($var !== null && preg_match('/\(\s*[^;]*'.preg_quote($var, '/').'\s*[,)]/', $body) && ! preg_match('/'.preg_quote($var, '/').'->getMessage\(\)/', $body));
            if (! $handled) {
                $count++;
            }
            $i = $k;
        }

        return $count;
    }

    /**
     * Swallowed catches of a GENERIC exception (Exception / Throwable / Error) —
     * the "something went wrong" blocks that hide real failures. Catches of
     * specific, expected exceptions (validation, not found, a domain exception
     * turned into a message for the user) are not counted.
     */
    public static function genericSwallowedCatches(string $code): int
    {
        $generic = ['Exception', '\\Exception', 'Throwable', '\\Throwable', 'Error', '\\Error'];
        $tokens = token_get_all($code);
        $n = count($tokens);
        $count = 0;
        for ($i = 0; $i < $n; $i++) {
            if (! is_array($tokens[$i]) || $tokens[$i][0] !== T_CATCH) {
                continue;
            }
            $types = [];
            $var = '';
            for ($j = $i + 1; $j < $n && $tokens[$j] !== '{'; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_VARIABLE) {
                    $var = $tokens[$j][1];
                } elseif (is_array($tokens[$j]) && in_array($tokens[$j][0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    $types[] = $tokens[$j][1];
                }
            }
            $depth = 0;
            $body = '';
            for ($k = $j; $k < $n; $k++) {
                $t = $tokens[$k];
                $body .= is_array($t) ? $t[1] : $t;
                if ($t === '{' || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                    $depth++;
                } elseif ($t === '}') {
                    $depth--;
                    if ($depth === 0) {
                        break;
                    }
                }
            }
            if (array_intersect($types, $generic) && self::swallowedCatches('<?php try {} catch ('.implode('|', $types).' '.$var.') '.$body) > 0) {
                $count++;
            }
            $i = $k;
        }

        return $count;
    }

    public static function validatorMakes(string $code): int
    {
        return substr_count($code, 'Validator::make(');
    }

    /** Hand-written SQL run straight from a controller (DB::select / statement / insert / update / delete / unprepared). */
    public static function rawSql(string $code): int
    {
        return preg_match_all('/DB::(select|selectOne|statement|insert|update|delete|unprepared)\(/', $code);
    }
}
