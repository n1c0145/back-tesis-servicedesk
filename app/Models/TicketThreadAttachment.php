<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketThreadAttachment extends Model
{
    protected $fillable = ['thread_id', 'file_name', 'file_path'];

    public function thread()
    {
        return $this->belongsTo(\App\Models\TicketThread::class, 'thread_id');
    }
}
