<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TicketThreadAttachment extends Model
{
    protected $fillable = ['thread_id', 'file_name', 'file_path'];

    public function thread()
    {
        return $this->belongsTo(\App\Models\TicketThread::class, 'thread_id');
    }
    public function getTemporaryUrl($expirationMinutes = 60)
    {
        return Storage::disk('s3')->temporaryUrl(
            $this->file_path,
            now()->addMinutes($expirationMinutes)
        );
    }
}
