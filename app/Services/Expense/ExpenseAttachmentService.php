<?php

namespace App\Services\Expense;

use App\Exceptions\ExpenseException;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The one place expense receipts are stored, referenced and served.
 *
 * - Files go to the PRIVATE `local` disk (storage/app/private), never under
 *   public/, so nothing an employee uploads can ever be executed or fetched
 *   without going through the signed download route.
 * - The stored name AND extension are generated server-side (a UUID plus the
 *   extension guessed from the file's real content). The client-supplied
 *   name/extension is never used, so a script/HTML payload cannot be stored
 *   under an executable extension.
 * - Rows created before this service existed keep a legacy `uploads/expense/...`
 *   path under public/; isLegacy() / url() / stream() handle both until
 *   `php artisan expense:migrate-uploads` moves them.
 */
class ExpenseAttachmentService
{
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf'];
    public const MAX_KB = 5120;
    public const DISK = 'local';
    /** Types the OLD upload code accepted — the only legacy files we will ever serve or delete. */
    private const LEGACY_SERVABLE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx'];
    private const URL_TTL_MINUTES = 30;

    /** Validation-rule fragment shared by web + API form requests. */
    public static function rule(): string
    {
        return 'mimes:' . implode(',', self::ALLOWED_EXTENSIONS) . '|max:' . self::MAX_KB;
    }

    /** @return string path relative to the private disk root, to store in expenses.file */
    public function store(UploadedFile $file, int $tenantId): string
    {
        $extension = strtolower((string) $file->guessExtension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new ExpenseException('Unsupported file type. Allowed: ' . implode(', ', self::ALLOWED_EXTENSIONS) . '.', 422);
        }

        $path = $file->storeAs("expense/{$tenantId}/" . date('Y'), Str::uuid() . '.' . $extension, self::DISK);
        if ($path === false) {
            throw new ExpenseException('The attachment could not be saved. Please try again.', 500);
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if (! $path) {
            return;
        }

        if ($this->isLegacy($path)) {
            $full = $this->legacyFullPath($path);
            if ($full && is_file($full)) {
                @unlink($full);
            }

            return;
        }

        Storage::disk(self::DISK)->delete($path);
    }

    /**
     * A path is "private" only when it matches the layout store() produces
     * (`expense/<tenantId>/<year>/<uuid>.<ext>`). Everything else is a legacy
     * public-relative path. The old code wrote several shapes over time —
     * `uploads/expense/file/…`, `expenses/2026/05/…`, `expenses/…` — so legacy
     * must be "anything that is not private", not one specific prefix.
     */
    public function isLegacy(string $path): bool
    {
        return ! preg_match('#^expense/\d+/\d{4}/[^/]+$#', $path);
    }

    /**
     * URL the UI/mobile app should use for the receipt: a short-lived signed
     * link for private files (works without a session, so the Flutter app can
     * load it directly), the old public URL for not-yet-migrated files.
     */
    public function url(?string $path, int $expenseId, ?int $tenantId = null): ?string
    {
        if (! $path) {
            return null;
        }

        if ($this->isLegacy($path)) {
            return asset($path);
        }

        // The tenant travels inside the signed URL: the download route runs with
        // no session/tenant context, so the controller re-scopes by it explicitly
        // instead of bypassing the tenant global scope unfiltered.
        $tenantId ??= app()->bound('current_tenant') ? (int) app('current_tenant')->id : 0;

        return URL::temporarySignedRoute(
            'expense.file',
            now()->addMinutes(self::URL_TTL_MINUTES),
            ['id' => $expenseId, 'tenant' => $tenantId]
        );
    }

    // ------------------------------------------------------------------
    //  Additional receipts (expense_attachments)
    //
    //  An expense keeps its FIRST receipt in `expenses.file` (this is what the mobile app and every
    //  existing screen read), and any further receipts in `expense_attachments`. receipts() merges both.
    // ------------------------------------------------------------------

    public const MAX_FILES = 5;

    /** Store one extra receipt for an expense and record it. The caller owns the surrounding transaction. */
    public function storeAttachment(Expense $expense, UploadedFile $file, int $uploadedBy): ExpenseAttachment
    {
        return $this->recordAttachment($expense, $this->store($file, (int) $expense->tenant_id), $file, $uploadedBy);
    }

    /** Record an ALREADY-stored file as an extra receipt (used when the caller stored it earlier). */
    public function recordAttachment(Expense $expense, string $path, UploadedFile $file, int $uploadedBy): ExpenseAttachment
    {
        return ExpenseAttachment::create([
            'tenant_id' => $expense->tenant_id,
            'expense_id' => $expense->id,
            'file_name' => $this->displayName($file),
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'mime_type' => $file->getMimeType(),
            'uploaded_by' => $uploadedBy,
        ]);
    }

    public function attachmentUrl(ExpenseAttachment $attachment, ?int $tenantId = null): ?string
    {
        if (! $attachment->file_path) {
            return null;
        }

        $tenantId ??= (int) ($attachment->tenant_id ?: (app()->bound('current_tenant') ? app('current_tenant')->id : 0));

        return URL::temporarySignedRoute(
            'expense.attachment',
            now()->addMinutes(self::URL_TTL_MINUTES),
            ['id' => $attachment->id, 'tenant' => $tenantId]
        );
    }

    public function streamAttachment(ExpenseAttachment $attachment): Response
    {
        abort_unless($attachment->file_path && Storage::disk(self::DISK)->exists($attachment->file_path), 404);

        return Storage::disk(self::DISK)->response($attachment->file_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    /** Remove only the stored FILE (the row is deleted by the caller inside its transaction). */
    public function deleteAttachmentFile(ExpenseAttachment $attachment): void
    {
        $this->delete($attachment->file_path);
    }

    /**
     * Every receipt on an expense — the primary one (expenses.file) first, then the extras — as
     * display-ready arrays with signed URLs. Eager-load `attachments` on lists to avoid N+1.
     *
     * @return array<int, array{id:?int, name:string, url:?string, is_image:bool, primary:bool}>
     */
    public function receipts(Expense $expense): array
    {
        $receipts = [];
        // Some list queries select only a few columns (no tenant_id): fall back to the current tenant
        // instead of signing tenant=0, which would 404 when the link is opened.
        $tenantId = $expense->tenant_id ? (int) $expense->tenant_id : null;

        if ($expense->file) {
            $ext = strtolower(pathinfo($expense->file, PATHINFO_EXTENSION));
            $receipts[] = [
                'id' => null,
                'name' => 'Receipt 1' . ($ext ? ".{$ext}" : ''),
                'url' => $this->url($expense->file, (int) $expense->id, $tenantId),
                'is_image' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
                'primary' => true,
            ];
        }

        foreach ($expense->attachments as $a) {
            $ext = strtolower(pathinfo((string) $a->file_name, PATHINFO_EXTENSION));
            $receipts[] = [
                'id' => (int) $a->id,
                'name' => (string) $a->file_name,
                'url' => $this->attachmentUrl($a, $tenantId),
                'is_image' => in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true),
                'primary' => false,
            ];
        }

        return $receipts;
    }

    /** Client-supplied file name kept ONLY for display: stripped of paths/control chars and length-capped. */
    private function displayName(UploadedFile $file): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/u', '', basename((string) $file->getClientOriginalName())) ?? '';

        return mb_substr(trim($name) !== '' ? $name : 'receipt', 0, 200);
    }

    public function stream(Expense $expense): Response
    {
        $path = $expense->file;
        if (! $path) {
            abort(404);
        }

        if ($this->isLegacy($path)) {
            $full = $this->legacyFullPath($path);
            abort_unless($full && is_file($full), 404);

            return response()->file($full, ['X-Content-Type-Options' => 'nosniff']);
        }

        abort_unless(Storage::disk(self::DISK)->exists($path), 404);

        return Storage::disk(self::DISK)->response($path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    /**
     * Resolve a legacy public-relative path to a real file, refusing anything
     * that escapes public/ (`..`) and anything that is not a receipt-type file,
     * so a bad `expenses.file` value can never be used to read or serve
     * a script or config file.
     */
    public function legacyFullPath(string $path): ?string
    {
        $base = realpath(public_path());
        $full = realpath(public_path($path));

        if ($base === false || $full === false || ! is_file($full) || ! str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }

        if (! in_array(strtolower(pathinfo($full, PATHINFO_EXTENSION)), self::LEGACY_SERVABLE_EXTENSIONS, true)) {
            return null;
        }

        return $full;
    }
}
