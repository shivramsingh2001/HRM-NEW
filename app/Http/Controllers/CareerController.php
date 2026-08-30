<?php

namespace App\Http\Controllers;

use App\Models\Career;
use App\Mail\CareerUserConfirmation;
use App\Mail\CareerAdminNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class CareerController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'mobile_no' => 'required|string|max:20|min:10',
            'position' => 'required|string|max:255',
            'cv_file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:2048',
            'about' => 'required|string|max:555',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Handle file upload
            $filePath = null;
            if ($request->hasFile('cv_file')) {
                $file = $request->file('cv_file');
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $directory = public_path('uploads/career');

                if (!file_exists($directory)) {
                    mkdir($directory, 0755, true);
                }

                $file->move($directory, $filename);
                $filePath = 'uploads/career/' . $filename;
            }

            // Create career record
            $career = Career::create([
                'name' => $request->name,
                'email' => $request->email,
                'mobile_no' => $request->mobile_no,
                'position' => $request->position,
                'cv_file' => $filePath,
                'about' => $request->about,
            ]);

            // Send emails using Mailable classes
            try {
                $this->sendEmails($career);
            } catch (\Exception $e) {
                Log::error('Failed to send emails', [
                    'error' => $e->getMessage(),
                    'career_id' => $career->id
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Application submitted successfully!',
                'data' => $career
            ], 200);
            
        } catch (\Exception $e) {
            Log::error('Failed to create career application', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to submit application. Please try again.'
            ], 500);
        }
    }

    /**
     * Send emails to multiple recipients using Mailable classes
     */
    private function sendEmails($career)
    {
        // Define admin emails (you can modify these as needed)
        $adminEmails = [
            'shurttechsol@gmail.com',
            'shivram45singh@gmail.com',
        ];
        
        // Send email to user (applicant)
        Mail::to($career->email, $career->name)
            ->send(new CareerUserConfirmation($career));
        
        // Send email to multiple admins
        Mail::to($adminEmails)
            ->send(new CareerAdminNotification($career));
    }
}