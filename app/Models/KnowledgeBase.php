<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeBase extends Model
{
    protected $table = 'knowledge_base';

    protected $fillable = [
        'ticket_id',
        'ticket_number',
        'titulo',
        'descripcion'
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }
}
