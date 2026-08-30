<?php

namespace App\Http\Controllers\Api\Announcement;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        try {
            $baseUrl = env('APP_URL');

            $announcements = Announcement::
                select(
                    'announcements.id',
                    'announcements.title',
                    'announcements.description',
                    'announcements.acknowledge',
                    DB::raw("
                        CASE 
                            WHEN announcements.file IS NULL OR announcements.image = '' 
                            THEN NULL
                            ELSE CONCAT('$baseUrl/', announcements.image)
                        END as file_url
                    "),
                    DB::raw("
                        CASE 
                            WHEN announcements.image IS NULL OR announcements.image = '' 
                            THEN NULL
                            ELSE CONCAT('$baseUrl/', announcements.image)
                        END as image_url
                    ")
                )
                ->orderBy('id','DESC')
                ->get();

            return response()->json([
                'success' => true,
                'message' => "Data fetched successfully!!!",
                'data' => $announcements
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "An error occured. Please try again later." . $e->getMessage(),
            ], 500);
        }
    }
    public function view(Request $request)
    {
        try {
            $userId = Auth::id();
            $baseUrl = env('APP_URL');

            $announcements = Announcement::where('announcements.user_id', $userId)
                ->whereDate('created_at', '>=', now())
                ->select(
                    'announcements.id',
                    'announcements.title',
                    'announcements.description',
                    'announcements.acknowledge',
                    DB::raw("
                        CASE 
                            WHEN announcements.file IS NULL OR announcements.file = '' 
                            THEN NULL
                            ELSE CONCAT('$baseUrl/', announcements.file)
                        END as file_url
                    "),
                    DB::raw("
                        CASE 
                            WHEN announcements.image IS NULL OR announcements.image = '' 
                            THEN NULL
                            ELSE CONCAT('$baseUrl/', announcements.image)
                        END as image_url
                    ")
                )
                ->get();

            return response()->json([
                'success' => true,
                'message' => "Data fetched successfully!!!",
                'data' => $announcements
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "An error occured. Please try again later." ,
            ], 500);
        }
    }

    public function store(Request $request)
    {
        // --------------------------
        // Validation
        // --------------------------
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|max:2048',
            'file' => 'nullable|file|max:5120',
            'acknowledge' => 'nullable|in:0,1',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first()
            ], 200);
        }

        try {
            // --------------------------
            // Upload image
            // --------------------------
            $imagePath = null;
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $extension = strtolower($file->getClientOriginalExtension());
                $filename = time() . '_' . uniqid() . '.' . $extension;
                $destinationPath = public_path('uploads/announcement/image');
                $file->move($destinationPath, $filename);
                $imagePath = 'uploads/announcement/image/' . $filename;
            }

            // --------------------------
            // Upload file (optional)
            // --------------------------
            $filePath = null;
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $extension = strtolower($file->getClientOriginalExtension());
                $filename = time() . '_' . uniqid() . '.' . $extension;
                $destinationPath = public_path('uploads/announcement/file');
                $file->move($destinationPath, $filename);
                $filePath = 'uploads/announcement/file/' . $filename;
            }

            // --------------------------
            // Create Announcement
            // --------------------------
           $status = $request->status ?? 1;   // or whatever default you want

            Announcement::create([
                'user_id' => auth()->id(),
                'title' => $request->title,
                'image' => $imagePath,
                'file' => $filePath,
                'status' => $status,
                'acknowledge' => 0,
                'description' => $request->description
            ]);


            return response()->json([
                'success' => true,
                'message' => 'Announcement created successfully',
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
