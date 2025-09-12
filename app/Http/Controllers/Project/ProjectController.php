<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;

class ProjectController extends Controller
{

    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        // Crear un proyecto
        $project = Project::create([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'estado' => 1
        ]);

        // Asignar usuarios al proyecto 
        if (!empty($data['user_ids'])) {
            $project->users()->attach($data['user_ids']);
        }

        return response()->json($project->load('users'), 201);
    }
}
