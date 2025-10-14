<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'estado',
        'created_by',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'project_user');
    }
        public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
