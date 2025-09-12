<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;

class ProjectController extends Controller
{
    // Crear un proyecto
    public function store(Request $request)
    {
        $data = $request->validate([
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);


        $project = Project::create([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'estado' => 1
        ]);

        if (!empty($data['user_ids'])) {
            $project->users()->attach($data['user_ids']);
        }

        return response()->json($project->load('users'), 201);
    }

    //Listar proyectos
    public function index()
    {
        $projects = Project::with('users')
            ->where('estado', 1)
            ->get();

        return response()->json($projects, 200);
    }

    //Listar 1 proyecto
    public function show($id)
    {
        $project = Project::with('users')->find($id);

        if (!$project) {
            return response()->json([
                'message' => 'Proyecto no encontrado'
            ], 404);
        }

        return response()->json($project, 200);
    }
    //Eliminar proyecto
    public function disable($id)
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'message' => 'Proyecto no encontrado'
            ], 404);
        }

        $project->estado = 0;
        $project->save();

        return response()->json([
            'message' => 'Proyecto deshabilitado correctamente',
            'project' => $project
        ], 200);
    }

    // Actualizar proyecto
    public function update(Request $request, $id)
    {
        $project = Project::find($id);

        if (!$project) {
            return response()->json([
                'message' => 'Proyecto no encontrado'
            ], 404);
        }

        $data = $request->validate([
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'exists:users,id',
        ]);

        
        if (isset($data['nombre'])) {
            $project->nombre = $data['nombre'];
        }
        if (array_key_exists('descripcion', $data)) {
            $project->descripcion = $data['descripcion'];
        }
        $project->save();

        // Actualizar usuarios 
        if (isset($data['user_ids'])) {
            $project->users()->sync($data['user_ids']); 
        }

        return response()->json($project->load('users'), 200);
    }
}
