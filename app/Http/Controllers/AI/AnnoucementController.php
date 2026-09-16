<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AnnoucementController extends Controller
{
    public function view_ai_all(Request $request)
    {
        try {
            $authUser = Auth::user();
            $baseUrl = config('app.url');

            // Base query with JOIN to users table
            $query = Announcement::select(
                'announcements.id',
                'announcements.title',
                'announcements.description',
                'announcements.acknowledge',
                'announcements.user_id as created_by',
                'announcements.created_at',
                'users.id as user_id',
                'users.name as user_name',
                'users.employee_id as user_employee_id',
                'users.role as user_role',
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
            ->leftJoin('users', 'announcements.user_id', '=', 'users.id'); // JOIN with users table

            // Role-based filtering
            switch ($authUser->role) {
                case 'admin':
                case 'hr':
                    // Admin/HR: See all announcements
                    // No additional filters
                    break;

                case 'manager':
                    // Manager: See all announcements
                    // No additional filters needed
                    break;

                case 'employee':
                    // Employee: See all active announcements
                    // No additional filters needed
                    break;

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized access. Invalid role.'
                    ], 200);
            }

            // Apply status filter if needed (only show active announcements)
            $query->where('announcements.status', 1);

            // Order by
            $query->orderBy('announcements.id', 'DESC');

            $announcements = $query->get();

            // Enhance announcements with additional data
            $enhancedAnnouncements = $announcements->map(function ($announcement) use ($authUser) {

                // Check if current user has acknowledged this announcement
                $hasAcknowledged = false;
                // if ($announcement->acknowledge) {
                //     You can implement acknowledgment tracking if you have a pivot table
                // }

                return [
                    'id' => $announcement->id,
                    'title' => $announcement->title,
                    'description' => $announcement->description,
                    'file_url' => $announcement->file_url,
                    'image_url' => $announcement->image_url,
                    'created_by' => [
                        'id' => $announcement->user_id,
                        'name' => $announcement->user_name,
                        'employee_id' => $announcement->user_employee_id,
                        'role' => $announcement->user_role
                    ],
                    'requires_acknowledgment' => (bool) $announcement->acknowledge,
                    'user_acknowledged' => $hasAcknowledged,
                    'published_at' => $announcement->created_at ? date('Y-m-d H:i:s', strtotime($announcement->created_at)) : null,
                ];
            });

            // Summary statistics
            $summary = [
                'total' => $announcements->count(),
                'requires_acknowledgment' => $announcements->where('acknowledge', 1)->count(),
                'recent' => $announcements->filter(function($item) {
                    return $item->created_at && now()->subDays(7)->lte($item->created_at);
                })->count()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Announcements fetched successfully',
                'data' => $enhancedAnnouncements,
                'summary' => $summary,
                
            ], 200);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later. ' . $e->getMessage()
            ], 500);
        }
    }
}