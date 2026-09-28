<?php

namespace App\Http\Controllers\Announcement;


use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Exception;

class AnnouncementController extends Controller
{
    /**
     * Display a listing of announcements.
     */
    public function index(Request $request)
    {
      
        try {
            $authUser = Auth::user();
           
            // Build query based on role
            $query = Announcement::where('user_id',$authUser->id)
                ->orderBy('created_at', 'desc');

            $announcements = $query->paginate(15);
           
            // Count statistics
            $totalAnnouncements = Announcement::where('user_id',$authUser->id)->count();
            $activeAnnouncements = Announcement::where('user_id',$authUser->id)->where('status', 1)->count();
            $inactiveAnnouncements = Announcement::where('user_id',$authUser->id)->where('status', 0)->count();
            
            return view('client.announcement.index', compact(
                'announcements',
                'totalAnnouncements',
                'activeAnnouncements',
                'inactiveAnnouncements'
            ));
            
        } catch (Exception $e) {
            Log::error('Announcement index error: ' . $e->getMessage());
            
            return redirect()->back()->with('error', 'Failed to load announcements. Please try again.');
        }
    }
    
    public function allAnnouncements(Request $request)
    {
        try {
            $authUser = Auth::user();
         
            // Build query with user join to get creator information
            $query = Announcement::where('announcements.status', '1')->join('users', 'announcements.user_id', '=', 'users.id')
                ->select(
                    'announcements.*',
                    'users.id as user_id',
                    'users.name as user_name',
                    'users.email as user_email',
                    'users.employee_id as user_employee_id'
                )
                
                ->orderBy('announcements.created_at', 'desc');

            // Apply status filter if provided
            if ($request->has('status') && $request->status !== '') {
                $query->where('announcements.status', $request->status);
            }

            $announcements = $query->paginate(15);
           
            // Count statistics for all announcements
            $totalAnnouncements = Announcement::count();
            $activeAnnouncements = Announcement::where('status', 1)->count();
            $inactiveAnnouncements = Announcement::where('status', 0)->count();
            
            return view('client.announcement.all', compact(
                'announcements',
                'totalAnnouncements',
                'activeAnnouncements',
                'inactiveAnnouncements'
            ));
            
        } catch (Exception $e) {
            
            return redirect()->back()->with('error', 'Failed to load announcements. Please try again.');
        }
    }

    /**
     * Store a newly created announcement.
     */
    public function store(Request $request)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
            'file' => 'nullable|file|mimes:pdf,doc,docx,txt|max:5120',
            'acknowledge' => 'nullable|in:0,1',
            'description' => 'nullable|string',
        ], [
            'title.required' => 'Please enter an announcement title',
            'image.required' => 'Please select an image',
            'image.image' => 'File must be an image',
            'image.mimes' => 'Image must be jpeg, png, jpg, or gif',
            'image.max' => 'Image size cannot exceed 2MB',
            'file.max' => 'File size cannot exceed 5MB',
            'file.mimes' => 'File must be pdf, doc, docx, or txt'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Upload image
            $imagePath = $request->hasFile('image')
                ? file_storage()->upload($request->file('image'), 'announcement_image')->path
                : null;

            // Upload file (optional)
            $filePath = $request->hasFile('file')
                ? file_storage()->upload($request->file('file'), 'announcement_file')->path
                : null;

            // Create Announcement
            $announcement = Announcement::create([
                'user_id' => auth()->id(),
                'title' => $request->title,
                'image' => $imagePath,
                'file' => $filePath,
                'status' => 1, // Default to active
                'acknowledge' => $request->has('acknowledge') ? 1 : 0,
                'description' => $request->description
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Announcement created successfully',
                'data' => $announcement
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Announcement store error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create announcement. Please try again.'
            ], 500);
        }
    }

    /**
     * Display the specified announcement.
     */
    public function show($id)
    {
        try {
            $announcement = Announcement::with('user')->findOrFail($id);
            
            // Check if user has acknowledged
            $isAcknowledged = DB::table('announcement_acknowledgments')
                ->where('announcement_id', $announcement->id)
                ->where('user_id', Auth::id())
                ->exists();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'announcement' => $announcement,
                    'is_acknowledged' => $isAcknowledged
                ]
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Announcement show error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Announcement not found'
            ], 404);
        }
    }

    /**
     * Update the specified announcement.
     */
    public function update(Request $request, $id)
    {
        // Validation
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'file' => 'nullable|file|mimes:pdf,doc,docx,txt|max:5120',
            'acknowledge' => 'nullable|in:0,1',
            'description' => 'nullable|string',
            'status' => 'required|in:0,1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $announcement = Announcement::findOrFail($id);

            // Upload new image if provided
            if ($request->hasFile('image')) {
                $announcement->image = file_storage()
                    ->replace($announcement->image, $request->file('image'), 'announcement_image')->path;
            }

            // Upload new file if provided
            if ($request->hasFile('file')) {
                $announcement->file = file_storage()
                    ->replace($announcement->file, $request->file('file'), 'announcement_file')->path;
            }

            // Update announcement
            $announcement->title = $request->title;
            $announcement->description = $request->description;
            $announcement->acknowledge = $request->has('acknowledge') ? 1 : 0;
            $announcement->status = $request->status;
            $announcement->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Announcement updated successfully',
                'data' => $announcement
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Announcement update error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update announcement. Please try again.'
            ], 500);
        }
    }

    /**
     * Remove the specified announcement.
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $announcement = Announcement::findOrFail($id);
            
            // Stored image + attachment are removed once the delete commits.
            file_storage()->deleteAfterCommit($announcement->image, 'announcement_image');
            file_storage()->deleteAfterCommit($announcement->file, 'announcement_file');
            
            // Delete acknowledgments
            DB::table('announcement_acknowledgments')
                ->where('announcement_id', $announcement->id)
                ->delete();
            
            // Delete announcement
            $announcement->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Announcement deleted successfully'
            ], 200);

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Announcement delete error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete announcement. Please try again.'
            ], 500);
        }
    }

    /**
     * Toggle announcement status.
     */
    public function toggleStatus($id)
    {
        try {
            $announcement = Announcement::findOrFail($id);
            $announcement->status = $announcement->status == 1 ? 0 : 1;
            $announcement->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'new_status' => $announcement->status
            ], 200);

        } catch (Exception $e) {
            Log::error('Announcement toggle status error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update status'
            ], 500);
        }
    }

    /**
     * Acknowledge announcement.
     */
    public function acknowledge($id)
    {
        try {
            $userId = Auth::id();
            
            // Check if already acknowledged
            $exists = DB::table('announcement_acknowledgments')
                ->where('announcement_id', $id)
                ->where('user_id', $userId)
                ->exists();
            
            if (!$exists) {
                DB::table('announcement_acknowledgments')->insert([
                    'announcement_id' => $id,
                    'user_id' => $userId,
                    'acknowledged_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Announcement acknowledged successfully'
            ], 200);

        } catch (Exception $e) {
            Log::error('Announcement acknowledge error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to acknowledge announcement'
            ], 500);
        }
    }

    /**
     * Get acknowledgment list for an announcement.
     */
    public function acknowledgments($id)
    {
        try {
            $acknowledgments = DB::table('announcement_acknowledgments')
                ->join('users', 'announcement_acknowledgments.user_id', '=', 'users.id')
                ->where('announcement_acknowledgments.announcement_id', $id)
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.employee_id',
                    'announcement_acknowledgments.acknowledged_at'
                )
                ->orderBy('announcement_acknowledgments.acknowledged_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $acknowledgments,
                'total' => $acknowledgments->count()
            ], 200);

        } catch (Exception $e) {
            Log::error('Announcement acknowledgments error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch acknowledgments'
            ], 500);
        }
    }
}