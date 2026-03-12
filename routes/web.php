<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/simple-test', function() {
    return 'SIMPLE TEST WORKS!';
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-localstack', function () {
    try {
        Storage::disk('s3')->put('test-from-laravel.txt', 'Hello from Laravel at ' . now());
        $url = Storage::disk('s3')->temporaryUrl('test-from-laravel.txt', now()->addMinutes(5));
        return response()->json([
            'success' => true,
            'message' => '✅ LocalStack S3 is working!',
            'file_url' => $url,
            'disk' => config('filesystems.default')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'line' => $e->getLine()
        ]);
    }
});