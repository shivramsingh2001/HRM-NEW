<?php

namespace App\Http\Controllers\Api\Announcement;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AnnouncementAcknowledgment;
use App\Services\AnnouncementNotificationService;
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
            $baseUrl = config('app.url');
            $userId = Auth::id();

            $announcements = Announcement::notExpired()
                ->leftJoin('announcement_acknowledgments', function ($join) use ($userId) {
                    $join->on('announcement_acknowledgments.announcement_id', '=', 'announcements.id')
                        ->where('announcement_acknowledgments.user_id', $userId);
                })
                ->select(
                    'announcements.id',
                    'announcements.title',
                    'announcements.description',
                    'announcements.acknowledge',
                    'announcements.expire_date',
                    'announcement_acknowledgments.acknowledged_at',
                    DB::raw('(announcement_acknowledgments.id IS NOT NULL) as is_acknowledged'),
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
                ->orderBy('announcements.id', 'DESC')
                ->get()
                ->map(function ($item) {
                    $item->is_acknowledged = (bool) $item->is_acknowledged;
                    return $item;
                });

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
            $baseUrl = config('app.url');

            $announcements = Announcement::where('announcements.user_id', $userId)
                ->whereDate('announcements.created_at', '>=', now())
                ->notExpired()
                ->leftJoin('announcement_acknowledgments', function ($join) use ($userId) {
                    $join->on('announcement_acknowledgments.announcement_id', '=', 'announcements.id')
                        ->where('announcement_acknowledgments.user_id', $userId);
                })
                ->select(
                    'announcements.id',
                    'announcements.title',
                    'announcements.description',
                    'announcements.acknowledge',
                    'announcements.expire_date',
                    'announcement_acknowledgments.acknowledged_at',
                    DB::raw('(announcement_acknowledgments.id IS NOT NULL) as is_acknowledged'),
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
                ->get()
                ->map(function ($item) {
                    $item->is_acknowledged = (bool) $item->is_acknowledged;
                    return $item;
                });

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
            'expire_date' => 'nullable|date',
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

            $announcement = Announcement::create([
                'user_id' => auth()->id(),
                'title' => $request->title,
                'image' => $imagePath,
                'file' => $filePath,
                'status' => $status,
                'acknowledge' => $request->has('acknowledge') ? (int) $request->acknowledge : 0,
                'description' => $request->description,
                'expire_date' => $request->expire_date,
            ]);

            app(AnnouncementNotificationService::class)->notifyAnnouncementCreated($announcement);

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

    /**
     * Employee acknowledges an announcement that requires it.
     */
    public function acknowledge(Request $request, $id)
    {
        try {
            $announcement = Announcement::find($id);

            if (!$announcement) {
                return response()->json([
                    'success' => false,
                    'message' => 'Announcement not found',
                ], 200);
            }

            if (!$announcement->acknowledge) {
                return response()->json([
                    'success' => true,
                    'message' => 'This announcement does not require acknowledgment',
                    'data' => [
                        'announcement_id' => $announcement->id,
                        'is_acknowledged' => false,
                    ],
                ], 200);
            }

            $ack = AnnouncementAcknowledgment::firstOrCreate(
                ['announcement_id' => $announcement->id, 'user_id' => Auth::id()],
                ['acknowledged_at' => now()]
            );

            return response()->json([
                'success' => true,
                'message' => 'Announcement acknowledged successfully',
                'data' => [
                    'announcement_id' => $announcement->id,
                    'is_acknowledged' => true,
                    'acknowledged_at' => $ack->acknowledged_at,
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to acknowledge announcement',
            ], 500);
        }
    }
}
