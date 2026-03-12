<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileController extends Controller
{
    /**
     * Upload a file to LocalStack S3
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

            $file = $request->file('file');
            
            // For now, use a test user ID replace with Firebase later
            $userId = 'test-user-123';
            
            // Generate unique filename
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $filename = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $extension;
            
            // Organize by user and date
            $r2Path = 'uploads/' . $userId . '/' . date('Y/m/d') . '/' . $filename;
            
            // Upload to S3 (LocalStack)
            Storage::disk('s3')->put($r2Path, file_get_contents($file));
            
            // Generate temporary URL (valid for 1 hour)
            $url = Storage::disk('s3')->temporaryUrl($r2Path, now()->addHours(1));
            
            Log::info('File uploaded successfully', [
                'path' => $r2Path,
                'original_name' => $originalName,
                'size' => $file->getSize()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully to LocalStack S3',
                'data' => [
                    'filename' => $originalName,
                    'path' => $r2Path,
                    'url' => $url,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType()
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
 * List all files
 */
public function index(Request $request)
{
    try {
        $userId = 'test-user-123';
        $directory = 'uploads/' . $userId;
        
        // Get all files from S3 (recursive)
        $files = Storage::disk('s3')->allFiles($directory);
        
        $fileList = [];
        foreach ($files as $file) {
            $fileList[] = [
                'path' => $file,
                'name' => basename($file),
                'size' => Storage::disk('s3')->size($file),
                'last_modified' => date('Y-m-d H:i:s', Storage::disk('s3')->lastModified($file)),
                'url' => Storage::disk('s3')->temporaryUrl($file, now()->addHours(1))
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
     * Delete a file
     */
    public function destroy($id)
    {
        try {
            $userId = 'test-user-123';
            $path = 'uploads/' . $userId . '/' . $id;
            
            if (Storage::disk('s3')->exists($path)) {
                Storage::disk('s3')->delete($path);
                return response()->json([
                    'success' => true,
                    'message' => 'File deleted successfully'
                ]);
            }
            
            return response()->json([
                'success' => false,
                'error' => 'File not found'
            ], 404);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }


    /**
 * Get storage stats for user
 */
public function stats(Request $request)
{
    try {
        $userId = 'test-user-123'; 
        $directory = 'uploads/' . $userId;
        
        // Get all files
        $files = Storage::disk('s3')->allFiles($directory);
        $totalFiles = count($files);
        $totalSize = 0;
        
        // Calculate total size
        foreach ($files as $file) {
            $totalSize += Storage::disk('s3')->size($file);
        }
        
        // Get files by type (extension)
        $byType = [];
        foreach ($files as $file) {
            $extension = pathinfo($file, PATHINFO_EXTENSION) ?: 'unknown';
            if (!isset($byType[$extension])) {
                $byType[$extension] = [
                    'count' => 0,
                    'total_size' => 0
                ];
            }
            $byType[$extension]['count']++;
            $byType[$extension]['total_size'] += Storage::disk('s3')->size($file);
        }
        
        // Format for response
        $formattedByType = [];
        foreach ($byType as $ext => $data) {
            $formattedByType[] = [
                'extension' => $ext,
                'count' => $data['count'],
                'total_size' => $this->formatBytes($data['total_size'])
            ];
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_files' => $totalFiles,
                'total_size' => $totalSize,
                'formatted_total_size' => $this->formatBytes($totalSize),
                'by_type' => $formattedByType
            ]
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
}

// Add this helper method if it's not already there
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