<?php

namespace App\Http\Controllers\Asset;

use App\Http\Controllers\Controller;
use App\Models\Asset;
use App\Models\AssetAttachment;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Mirrors ProjectController::storeAttachment()/destroyAttachment() exactly:
 * public_path('uploads/...') + UploadedFile::move(), UUID filename, no
 * Storage::disk() facade (not used anywhere else in this codebase for
 * writes).
 */
class AssetAttachmentController extends Controller
{
    public function store(Request $request, $id)
    {
        $asset = Asset::findOrFail(decrypt($id));

        try {
            $request->validate([
                'file' => 'required|file|max:10240',
                'context' => 'nullable|string|max:40',
            ]);

            $file = $request->file('file');
            $relativeDir = 'uploads/assets/' . $asset->id . '/attachments';
            $fullDir = public_path($relativeDir);
            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }
            $filename = (string) Str::uuid() . '.' . $file->getClientOriginalExtension();
            $file->move($fullDir, $filename);

            $attachment = AssetAttachment::create([
                'asset_id' => $asset->id,
                'uploaded_by' => Auth::id(),
                'file_path' => $relativeDir . '/' . $filename,
                'context' => $request->context,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize(),
            ]);

            return response()->json(['success' => true, 'message' => 'File uploaded.', 'data' => $attachment]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation error', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to upload file.'], 500);
        }
    }

    public function destroy(Request $request, $attachmentId)
    {
        try {
            $attachment = AssetAttachment::findOrFail(decrypt($attachmentId));

            $fullPath = public_path($attachment->file_path);
            if (is_file($fullPath)) {
                @unlink($fullPath);
            }

            $attachment->delete();

            return response()->json(['success' => true, 'message' => 'Attachment deleted.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete attachment.'], 500);
        }
    }
}
