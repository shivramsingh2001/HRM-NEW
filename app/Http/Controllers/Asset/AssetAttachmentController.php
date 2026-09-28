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
 * Mirrors ProjectController::storeAttachment()/destroyAttachment(): files go
 * through the shared FileStorageService (module 'asset_attachment').
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

            $stored = file_storage()->upload($request->file('file'), 'asset_attachment', ['id' => $asset->id]);

            $attachment = AssetAttachment::create([
                'asset_id' => $asset->id,
                'uploaded_by' => Auth::id(),
                'file_path' => $stored->path,
                'context' => $request->context,
                'original_filename' => $stored->originalName,
                'mime_type' => $stored->mimeType,
                'file_size' => $stored->size,
            ]);

            return response()->json(['success' => true, 'message' => 'File uploaded.', 'data' => $attachment]);
        } catch (\App\Exceptions\FileStorageException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], $e->httpStatus());
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

            file_storage()->delete($attachment->file_path, 'asset_attachment');

            $attachment->delete();

            return response()->json(['success' => true, 'message' => 'Attachment deleted.']);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete attachment.'], 500);
        }
    }
}
