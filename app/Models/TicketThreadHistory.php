<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TicketThreadHistory extends Model
{
    protected $fillable = [
        'thread_id',
        'changes',
        'time',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public function thread()
    {
        return $this->belongsTo(TicketThread::class, 'thread_id');
    }
}
