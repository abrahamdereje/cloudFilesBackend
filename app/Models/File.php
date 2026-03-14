<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'files';

    protected $fillable = [
        'firebase_uid',
        'filename',
        'original_filename',
        's3_path',
        'mime_type',
        'size',
        'extension',
        'is_public',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_public' => 'boolean',
        'size' => 'integer'
    ];

    /**
     * Get the shares for this file
     */
    public function shares()
    {
        return $this->hasMany(FileShare::class);
    }

    /**
     * Generate access URL
     */
    public function getUrlAttribute()
    {
        if ($this->is_public) {
            return Storage::disk('s3')->url($this->s3_path);
        }
        
        return Storage::disk('s3')->temporaryUrl(
            $this->s3_path, 
            now()->addHours(1)
        );
    }

    /**
     * Format file size for display
     */
    public function getFormattedSizeAttribute()
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Scope for user files
     */
    public function scopeForUser($query, $firebaseUid)
    {
        return $query->where('firebase_uid', $firebaseUid);
    }
}