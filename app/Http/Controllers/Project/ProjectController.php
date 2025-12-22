<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\User;
use App\Notifications\ProjectAssigned;


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
            'created_by' => 'required|exists:users,id',
            'firstresponse' => 'nullable|integer|min:0',
            'maxresolution' => 'nullable|integer|min:0',
            'effectivetime' => 'nullable|integer|min:0',
            'hoursbank' => 'nullable|integer|min:0',
        ]);


        $project = Project::create([
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'] ?? null,
            'estado' => 1,
            'created_by' => $data['created_by'],
            'firstresponse' => $data['firstresponse'] ?? null,
            'maxresolution' => $data['maxresolution'] ?? null,
            'effectivetime' => $data['effectivetime'] ?? null,
            'hoursbank' => $data['hoursbank'] ?? null,
        ]);

        if (!empty($data['user_ids'])) {
            $project->users()->attach($data['user_ids']);

            // Disparar notificación para cada usuario asignado
            foreach ($data['user_ids'] as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->notify(new ProjectAssigned($project->nombre));
                }
            }
        }


        return response()->json($project->load('users'), 201);
    }

    //Listar proyectos
    public function index()
    {
        $projects = Project::with(['users', 'creator:id,nombre,apellido'])
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
            'firstresponse' => 'nullable|integer|min:0',
            'maxresolution' => 'nullable|integer|min:0',
            'effectivetime' => 'nullable|integer|min:0',
            'hoursbank' => 'nullable|integer|min:0',
        ]);

        if (isset($data['nombre'])) {
            $project->nombre = $data['nombre'];
        }
        if (array_key_exists('descripcion', $data)) {
            $project->descripcion = $data['descripcion'];
        }
        if (array_key_exists('firstresponse', $data)) {
            $project->firstresponse = $data['firstresponse'];
        }
        if (array_key_exists('maxresolution', $data)) {
            $project->maxresolution = $data['maxresolution'];
        }
        if (array_key_exists('effectivetime', $data)) {
            $project->effectivetime = $data['effectivetime'];
        }
        if (array_key_exists('hoursbank', $data)) {
            $project->hoursbank = $data['hoursbank'];
        }
        $project->save();

        // Actualizar usuarios
        if (isset($data['user_ids'])) {
            $currentUserIds = $project->users()->pluck('users.id')->toArray();
            $project->users()->sync($data['user_ids']);
            $newUserIds = array_diff($data['user_ids'], $currentUserIds);

            foreach ($newUserIds as $userId) {
                $user = User::find($userId);
                if ($user) {
                    $user->notify(new ProjectAssigned($project->nombre));
                }
            }
        }

        return response()->json($project->load('users'), 200);
    }
    public function projectsByUser(Request $request)
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::find($data['user_id']);

        $projects = $user->projects()
            ->where('estado', 1)
            ->get()
            ->map(function ($project) {
                return [
                    'project_id' => $project->id,
                    'nombre' => $project->nombre,
                ];
            });

        return response()->json($projects, 200);
    }
    public function usersByProject(Request $request)
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
        ]);

        $project = Project::find($data['project_id']);

        $users = $project->users()
            ->select('users.id', 'users.nombre', 'users.apellido', 'users.puesto')
            ->get()
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'user_name' => trim($user->nombre . ' ' . $user->apellido . ' - ' . $user->puesto),
                ];
            });

        return response()->json($users, 200);
    }
}
