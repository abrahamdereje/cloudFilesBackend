<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class FileShare extends Model
{
    use HasFactory;

    protected $table = 'file_shares';

    protected $fillable = [
        'file_id',
        'share_token',
        'password',
        'expires_at',
        'max_downloads',
        'download_count',
        'shared_with'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'shared_with' => 'array'
    ];

    /**
     * Get the file that owns this share
     */
    public function file()
    {
        return $this->belongsTo(File::class);
    }

    /**
     * Generate unique share token
     */
    public static function generateToken()
    {
        return Str::random(32);
    }

    /**
     * Check if share is valid
     */
    public function isValid()
    {
        // Check expiry
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        // Check download limit
        if ($this->max_downloads && $this->download_count >= $this->max_downloads) {
            return false;
        }

        return true;
    }

    /**
     * Increment download count
     */
    public function recordDownload()
    {
        $this->increment('download_count');
    }
}