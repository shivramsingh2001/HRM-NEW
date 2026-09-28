<?php

namespace App\Http\Controllers;

use App\Services\Storage\FileStorageService;

/**
 * Safety net for links built from a raw stored path (`asset($path)`,
 * `'/' + path` in JS, `$baseUrl . $path`).
 *
 * The web server serves files that still exist in public/uploads directly, so
 * this only runs for files that are no longer on local disk, i.e. ones that live
 * in cloud storage. It sends a logged-in user on to a short-lived signed URL.
 * Code should link with file_url(); this route only keeps older links working.
 */
class FileController extends Controller
{
    public function __construct(private FileStorageService $files)
    {
    }

    public function uploads(string $path)
    {
        $stored = 'uploads/' . $path;

        // On the local disk a missing static file is simply missing.
        abort_unless($this->files->isCloud(), 404);

        // No existence round-trip: a missing object simply 404s at the bucket.
        $url = $this->files->url($stored);
        abort_unless($url, 404);

        return redirect()->away($url)->header('Cache-Control', 'private, no-store');
    }
}
