<?php

namespace Tests\Feature\Storage;

use App\Exceptions\FileStorageException;
use App\Services\Storage\FileStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * FileStorageService against a faked cloud disk: no real bucket, no DB writes,
 * no files left in public/uploads (legacy roots point at a temp folder).
 */
class FileStorageServiceTest extends TestCase
{
    private FileStorageService $files;

    private string $legacyRoot;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('gcs');
        Storage::fake('uploads');
        Storage::disk('gcs')->buildTemporaryUrlsUsing(
            fn ($path, $expiration) => 'https://signed.test/' . $path . '?expires=' . $expiration->getTimestamp()
        );

        $this->legacyRoot = storage_path('framework/testing/legacy_' . uniqid());
        File::ensureDirectoryExists($this->legacyRoot . '/uploads/leave');

        config([
            'file_storage.disk' => 'gcs',
            'file_storage.legacy_roots' => [
                ['root' => $this->legacyRoot, 'url' => '', 'prefixes' => ['uploads/']],
            ],
        ]);

        $this->files = new FileStorageService();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->legacyRoot);
        parent::tearDown();
    }

    private function pdf(string $name = 'medical.pdf', int $padKb = 1): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n" . str_repeat('A', $padKb * 1024) . "\n%%EOF");
    }

    public function test_upload_stores_under_module_folder_with_server_generated_name(): void
    {
        $stored = $this->files->upload($this->pdf('My Report (final).pdf'), 'leave', ['tenant' => 7]);

        $this->assertMatchesRegularExpression('#^uploads/leave/7/[0-9a-f\-]{36}\.pdf$#', $stored->path);
        $this->assertSame('My Report (final).pdf', $stored->originalName);
        $this->assertSame('pdf', $stored->extension);
        $this->assertSame('gcs', $stored->disk);
        Storage::disk('gcs')->assertExists($stored->path);
    }

    public function test_disallowed_and_blocked_types_are_rejected(): void
    {
        foreach ([['shell.php', '<?php echo 1;'], ['notes.txt', 'plain text'], ['page.html', '<html></html>']] as [$name, $body]) {
            try {
                $this->files->upload(UploadedFile::fake()->createWithContent($name, $body), 'leave', ['tenant' => 1]);
                $this->fail("{$name} should have been rejected");
            } catch (FileStorageException $e) {
                $this->assertSame(422, $e->httpStatus());
            }
        }
        $this->assertSame([], Storage::disk('gcs')->allFiles());
    }

    public function test_oversize_file_is_rejected(): void
    {
        config(['file_storage.modules.leave.max' => 1]);

        $this->expectException(FileStorageException::class);
        $this->expectExceptionMessage('larger than 1 KB');
        $this->files->upload($this->pdf('big.pdf', 4), 'leave', ['tenant' => 1]);
    }

    public function test_cloud_url_is_signed_and_null_or_external_values_pass_through(): void
    {
        $stored = $this->files->upload($this->pdf(), 'leave', ['tenant' => 1]);

        $this->assertStringStartsWith('https://signed.test/' . $stored->path, $this->files->url($stored->path, 'leave'));
        $this->assertNull($this->files->url(null));
        $this->assertNull($this->files->url(''));
        $this->assertSame('https://cdn.example.com/a.png', $this->files->url('https://cdn.example.com/a.png'));
        $this->assertNull($this->files->url('uploads/../.env'), 'path traversal must never produce a URL');
    }

    public function test_public_modules_get_the_long_lifetime(): void
    {
        config(['file_storage.signed_url_ttl' => 60, 'file_storage.public_url_ttl' => 10080]);
        $this->travelTo(now()->startOfMinute());

        $private = $this->files->url('uploads/leave/1/x.pdf', 'leave');
        $public = $this->files->url('uploads/users/1/profile/x.jpg', 'profile_photo');

        $this->assertStringEndsWith('expires=' . now()->addMinutes(60)->getTimestamp(), $private);
        $this->assertStringEndsWith('expires=' . now()->addMinutes(10080)->getTimestamp(), $public);
    }

    public function test_file_still_on_local_disk_is_served_locally_until_migrated(): void
    {
        File::put($this->legacyRoot . '/uploads/leave/old.pdf', '%PDF-1.4 old');

        $this->assertSame(asset('uploads/leave/old.pdf'), $this->files->url('uploads/leave/old.pdf', 'leave'));
        $this->assertTrue($this->files->exists('uploads/leave/old.pdf', 'leave'));
        $this->assertSame('%PDF-1.4 old', $this->files->contents('uploads/leave/old.pdf', 'leave'));

        $this->assertTrue($this->files->delete('uploads/leave/old.pdf', 'leave'));
        $this->assertFileDoesNotExist($this->legacyRoot . '/uploads/leave/old.pdf');
    }

    public function test_replace_stores_new_file_then_removes_old_one(): void
    {
        $old = $this->files->upload($this->pdf('v1.pdf'), 'leave', ['tenant' => 1]);
        $new = $this->files->replace($old->path, $this->pdf('v2.pdf'), 'leave', ['tenant' => 1]);

        Storage::disk('gcs')->assertExists($new->path);
        Storage::disk('gcs')->assertMissing($old->path);
    }

    public function test_replace_keeps_old_file_when_new_upload_is_invalid(): void
    {
        $old = $this->files->upload($this->pdf('v1.pdf'), 'leave', ['tenant' => 1]);

        try {
            $this->files->replace($old->path, UploadedFile::fake()->createWithContent('x.php', '<?php'), 'leave', ['tenant' => 1]);
            $this->fail('invalid replacement should throw');
        } catch (FileStorageException) {
        }

        Storage::disk('gcs')->assertExists($old->path);
    }

    public function test_replace_inside_transaction_deletes_old_file_only_after_commit(): void
    {
        $old = $this->files->upload($this->pdf('v1.pdf'), 'leave', ['tenant' => 1]);

        DB::beginTransaction();
        $this->files->replace($old->path, $this->pdf('v2.pdf'), 'leave', ['tenant' => 1]);
        Storage::disk('gcs')->assertExists($old->path);
        DB::rollBack();

        $this->assertTrue(Storage::disk('gcs')->exists($old->path), 'a rolled-back update must not lose the original file');
    }

    public function test_upload_many_is_all_or_nothing(): void
    {
        try {
            $this->files->uploadMany([
                $this->pdf('a.pdf'),
                $this->pdf('b.pdf'),
                UploadedFile::fake()->createWithContent('c.exe', 'MZ'),
            ], 'task_attachment', ['tenant' => 1]);
            $this->fail('batch with a bad file should throw');
        } catch (FileStorageException) {
        }

        $this->assertSame([], Storage::disk('gcs')->allFiles());
    }

    public function test_delete_is_null_safe_and_removes_cloud_object(): void
    {
        $this->assertFalse($this->files->delete(null));
        $this->assertFalse($this->files->delete('uploads/leave/1/missing.pdf', 'leave'));

        $stored = $this->files->upload($this->pdf(), 'leave', ['tenant' => 1]);
        $this->assertTrue($this->files->delete($stored->path, 'leave'));
        Storage::disk('gcs')->assertMissing($stored->path);
    }

    public function test_local_mode_writes_to_public_uploads_disk_with_plain_urls(): void
    {
        config(['file_storage.disk' => 'uploads']);

        $stored = $this->files->upload($this->pdf(), 'leave', ['tenant' => 3]);

        $this->assertSame('uploads', $stored->disk);
        Storage::disk('uploads')->assertExists($stored->path);
        $this->assertSame(asset($stored->path), $this->files->url($stored->path, 'leave'));
    }

    public function test_store_contents_and_map_urls(): void
    {
        $voice = $this->files->storeContents("RIFF....WAVEfmt ", 'task_voice', 'wav', ['tenant' => 2]);
        $this->assertMatchesRegularExpression('#^uploads/task/2/voice/[0-9a-f\-]{36}\.wav$#', $voice->path);

        $rows = collect([(object) ['file_url' => $voice->path], (object) ['file_url' => null]]);
        $this->files->mapUrls($rows, ['file_url' => 'task_voice']);

        $this->assertStringStartsWith('https://signed.test/' . $voice->path, $rows[0]->file_url);
        $this->assertNull($rows[1]->file_url);
    }

    public function test_unknown_module_fails_loudly(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->files->upload($this->pdf(), 'no_such_module');
    }
}
