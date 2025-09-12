<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'correo',
        'nombre',
        'apellido',
        'cedula',
        'puesto',
        'estado',
        'role_id',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_user');
    }
}
