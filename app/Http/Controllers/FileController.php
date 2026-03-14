<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\FileShare;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class FileController extends Controller
{
    /**
     * Upload a file to LocalStack S3 and save metadata
     */
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|max:10240', // 10MB max
            ]);

            if (!$request->hasFile('file')) {
                return response()->json(['error' => 'No file uploaded'], 400);
            }

            // TODO: Get Firebase user ID from token (replace with actual)
            $firebaseUid = $request->input('firebase_uid', 'test-user-123');
            
            $file = $request->file('file');
            
            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
            
            // Organize by user and date
            $s3Path = 'uploads/' . $firebaseUid . '/' . date('Y/m/d') . '/' . $filename;
            
            // Upload to S3 (LocalStack)
            Storage::disk('s3')->put($s3Path, file_get_contents($file));
            
            // Save to database
            $fileRecord = File::create([
                'firebase_uid' => $firebaseUid,
                'filename' => $filename,
                'original_filename' => $originalName,
                's3_path' => $s3Path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'extension' => $extension,
                'is_public' => $request->boolean('is_public', false),
                'metadata' => [
                    'upload_ip' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ]
            ]);
            
            Log::info('File uploaded successfully', [
                'file_id' => $fileRecord->id,
                'path' => $s3Path
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'id' => $fileRecord->id,
                    'filename' => $originalName,
                    'size' => $fileRecord->formatted_size,
                    'url' => $fileRecord->url,
                    'mime_type' => $fileRecord->mime_type
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Upload failed: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all files for current user
     */
    public function index(Request $request)
    {
        try {
            $firebaseUid = $request->input('firebase_uid', 'test-user-123');
            
            $files = File::forUser($firebaseUid)
                ->orderBy('created_at', 'desc')
                ->get();
            
            $fileList = [];
            foreach ($files as $file) {
                $fileList[] = [
                    'id' => $file->id,
                    'name' => $file->original_filename,
                    'size' => $file->formatted_size,
                    'url' => $file->url,
                    'uploaded_at' => $file->created_at->diffForHumans(),
                    'is_public' => $file->is_public,
                    'extension' => $file->extension
                ];
            }
            
            return response()->json([
                'success' => true,
                'count' => count($fileList),
                'files' => $fileList
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single file details
     */
    public function show($id)
    {
        try {
            $file = File::with('shares')->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $file->id,
                    'name' => $file->original_filename,
                    'size' => $file->formatted_size,
                    'url' => $file->url,
                    'mime_type' => $file->mime_type,
                    'uploaded_at' => $file->created_at->toDateTimeString(),
                    'is_public' => $file->is_public,
                    'shares' => $file->shares
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'File not found'
            ], 404);
        }
    }

    /**
     * Update file properties
     */
    public function update(Request $request, $id)
    {
        try {
            $file = File::findOrFail($id);
            
            if ($request->has('is_public')) {
                $file->is_public = $request->boolean('is_public');
            }
            
            if ($request->has('original_filename')) {
                $file->original_filename = $request->original_filename;
            }
            
            $file->save();
            
            return response()->json([
                'success' => true,
                'message' => 'File updated successfully',
                'data' => $file
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a file
     */
    public function destroy($id)
    {
        try {
            $file = File::findOrFail($id);
            
            // Delete from S3
            Storage::disk('s3')->delete($file->s3_path);
            
            // Delete from database (soft delete)
            $file->delete();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Delete failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get storage stats
     */
    public function stats(Request $request)
    {
        try {
            $firebaseUid = $request->input('firebase_uid', 'test-user-123');
            
            $files = File::forUser($firebaseUid)->get();
            $totalFiles = $files->count();
            $totalSize = $files->sum('size');
            
            // Group by extension
            $byType = $files->groupBy('extension')->map(function ($group, $ext) {
                return [
                    'extension' => $ext ?: 'unknown',
                    'count' => $group->count(),
                    'total_size' => $this->formatBytes($group->sum('size'))
                ];
            })->values();
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_files' => $totalFiles,
                    'total_size' => $totalSize,
                    'formatted_total_size' => $this->formatBytes($totalSize),
                    'by_type' => $byType
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create share link for file
     */
    public function createShare(Request $request, $id)
    {
        try {
            $request->validate([
                'password' => 'nullable|string|min:4',
                'expires_at' => 'nullable|date|after:now',
                'max_downloads' => 'nullable|integer|min:1'
            ]);

            $file = File::findOrFail($id);
            
            // Create share record
            $share = FileShare::create([
                'file_id' => $file->id,
                'share_token' => FileShare::generateToken(),
                'password' => $request->password ? Hash::make($request->password) : null,
                'expires_at' => $request->expires_at,
                'max_downloads' => $request->max_downloads,
                'shared_with' => $request->shared_with ?? []
            ]);

            // Generate shareable URL
            $shareUrl = url('/api/shared/' . $share->share_token);

            return response()->json([
                'success' => true,
                'message' => 'Share link created',
                'data' => [
                    'share_token' => $share->share_token,
                    'share_url' => $shareUrl,
                    'expires_at' => $share->expires_at,
                    'password_protected' => !is_null($share->password)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List all shares for a file
     */
    public function listShares($id)
    {
        try {
            $file = File::findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $file->shares
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a share link
     */
    public function deleteShare($id, $shareId)
    {
        try {
            $share = FileShare::where('file_id', $id)
                ->where('id', $shareId)
                ->firstOrFail();
            
            $share->delete();

            return response()->json([
                'success' => true,
                'message' => 'Share link deleted'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Access shared file (public endpoint)
     */
 /**
 * Access shared file (public endpoint)
 */
public function accessShared(Request $request, $token)
{
    try {
        $share = FileShare::with('file')
            ->where('share_token', $token)
            ->firstOrFail();

        // Check if share is valid
        if (!$share->isValid()) {
            return response()->json([
                'success' => false,
                'error' => 'Share link has expired or reached maximum downloads'
            ], 403);
        }

        // Check password if set
        if ($share->password) {
            // If it's a browser request with no password, show password form
            if (!$request->has('password') && !$request->isJson()) {
                return $this->showPasswordForm($token);
            }
            
            $request->validate(['password' => 'required|string']);
            
            if (!Hash::check($request->password, $share->password)) {
                if ($request->isJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Invalid password'
                    ], 403);
                }
                return back()->withErrors(['password' => 'Invalid password']);
            }
        }

        // Record download
        $share->recordDownload();

        // Generate temporary URL
        $url = Storage::disk('s3')->temporaryUrl(
            $share->file->s3_path, 
            now()->addHours(1)
        );

        // If it's a browser request, redirect to the file
        if (!$request->isJson()) {
            return redirect()->away($url);
        }

        // If it's an API request, return JSON
        return response()->json([
            'success' => true,
            'data' => [
                'filename' => $share->file->original_filename,
                'url' => $url,
                'downloads' => $share->download_count,
                'expires_at' => $share->expires_at
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => 'Invalid share link'
        ], 404);
    }
}

/**
 * Show password form for protected shares
 */
private function showPasswordForm($token)
{
    return <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <title>Password Protected File</title>
        <style>
            body { font-family: Arial; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #f5f5f5; }
            .container { background: white; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); width: 300px; }
            h2 { margin-top: 0; color: #333; }
            input { width: 100%; padding: 8px; margin: 10px 0; border: 1px solid #ddd; border-radius: 4px; }
            button { width: 100%; padding: 10px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer; }
            button:hover { background: #45a049; }
        </style>
    </head>
    <body>
        <div class="container">
            <h2>Password Protected File</h2>
            <p>This file is password protected. Please enter the password to access it.</p>
            <form method="GET" action="/api/shared/{$token}">
                <input type="password" name="password" placeholder="Enter password" required>
                <button type="submit">Access File</button>
            </form>
        </div>
    </body>
    </html>
    HTML;
}

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}