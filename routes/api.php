<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FileController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::get('/test', function () {
    return response()->json([
        'message' => 'cloudFilesBackend API is working',
        'version' => '1.0.0'
    ]);
});

Route::get('/ping', function() {
    return response()->json([
        'message' => 'pong', 
        'time' => now()->toDateTimeString()
    ]);
});

// Simple test upload 
Route::post('/test-upload-simple', function (Illuminate\Http\Request $request) {
    try {
        if (!$request->hasFile('file')) {
            return response()->json(['error' => 'No file uploaded'], 400);
        }
        
        $file = $request->file('file');
        
        return response()->json([
            'success' => true,
            'message' => 'Simple test works!',
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize()
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
});

Route::prefix('files')->group(function () {
    Route::get('/', [FileController::class, 'index']);
    Route::post('/upload', [FileController::class, 'upload']);
    Route::get('/stats', [FileController::class, 'stats']);
    Route::get('/{id}', [FileController::class, 'show']);
    Route::put('/{id}', [FileController::class, 'update']);
    Route::delete('/{id}', [FileController::class, 'destroy']);
});