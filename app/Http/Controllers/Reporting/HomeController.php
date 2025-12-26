<?php

namespace App\Http\Controllers\Reporting;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\Project;
use App\Models\TicketThread;
use App\Models\TicketThreadHistory;
use App\Models\User;

class HomeController extends Controller
{
    public function homeView(Request $request)
    {
        try {

            $validated = $request->validate([
                'user_id' => 'required|integer|exists:users,id'
            ]);

            $userId = $validated['user_id'];

            $user = User::with('role:id,nombre')->find($userId);
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no encontrado'
                ], 404);
            }

            $assignedTickets = Ticket::where('assigned_to', $userId)
                ->select('id', 'ticket_number', 'titulo', 'status_id', 'priority_id')
                ->with(['status:id,nombre', 'priority:id,nombre'])
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($ticket) {
                    return [
                        'id' => $ticket->id,
                        'ticket_number' => $ticket->ticket_number,
                        'titulo' => $ticket->titulo,
                        'status' => $ticket->status->nombre ?? 'Sin estado',
                        'priority' => $ticket->priority->nombre ?? 'Sin prioridad'
                    ];
                });

            $userProjects = $user->projects()
                ->select('projects.id', 'projects.nombre')
                ->get()
                ->map(function ($project) {
                    return [
                        'id' => $project->id,
                        'nombre' => $project->nombre
                    ];
                });

            $ticketsCreated = Ticket::where('created_by', $userId)->count();

            $ticketsAssigned = Ticket::where('assigned_to', $userId)->count();

            $ticketsHighPriority = Ticket::where('assigned_to', $userId)
                ->where('priority_id', 3)
                ->count();

            $userUpdates = TicketThreadHistory::query()
                ->leftJoin('ticket_threads', 'ticket_thread_histories.thread_id', '=', 'ticket_threads.id')
                ->where('ticket_threads.user_id', $userId)
                ->count();

            $userTotalTime = TicketThreadHistory::query()
                ->leftJoin('ticket_threads', 'ticket_thread_histories.thread_id', '=', 'ticket_threads.id')
                ->where('ticket_threads.user_id', $userId)
                ->sum('ticket_thread_histories.time');

            $topProjects = Project::query()
                ->select('projects.id', 'projects.nombre')
                ->selectRaw('COALESCE(SUM(ticket_thread_histories.time), 0) as total_time')
                ->leftJoin('tickets', 'projects.id', '=', 'tickets.project_id')
                ->leftJoin('ticket_threads', 'tickets.id', '=', 'ticket_threads.ticket_id')
                ->leftJoin('ticket_thread_histories', function ($join) use ($userId) {
                    $join->on('ticket_threads.id', '=', 'ticket_thread_histories.thread_id')
                        ->where('ticket_threads.user_id', $userId);
                })
                ->whereIn('projects.id', $userProjects->pluck('id')->toArray()) // Solo proyectos del usuario
                ->groupBy('projects.id', 'projects.nombre')
                ->orderByDesc('total_time')
                ->limit(5)
                ->get()
                ->map(function ($project) {
                    return [
                        'name' => $project->nombre,
                        'value' => (int)$project->total_time
                    ];
                });

            $topTickets = Ticket::query()
                ->select('tickets.id', 'tickets.ticket_number', 'tickets.titulo')
                ->selectRaw('COALESCE(SUM(ticket_thread_histories.time), 0) as total_time')
                ->leftJoin('ticket_threads', function ($join) use ($userId) {
                    $join->on('tickets.id', '=', 'ticket_threads.ticket_id')
                        ->where('ticket_threads.user_id', $userId);
                })
                ->leftJoin('ticket_thread_histories', 'ticket_threads.id', '=', 'ticket_thread_histories.thread_id')
                ->whereIn('tickets.project_id', $userProjects->pluck('id')->toArray())
                ->groupBy('tickets.id', 'tickets.ticket_number', 'tickets.titulo')
                ->orderByDesc('total_time')
                ->limit(5)
                ->get()
                ->map(function ($ticket) {
                    return [
                        'name' => "#{$ticket->ticket_number} - {$ticket->titulo}",
                        'value' => (int)$ticket->total_time
                    ];
                });

            return response()->json([
                'success' => true,
                'message' => 'Datos del dashboard obtenidos exitosamente',
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'nombre_completo' => $user->nombre . ' ' . $user->apellido,
                        'correo' => $user->correo,
                        'puesto' => $user->puesto,
                        'role_id' => $user->role_id,
                        'role_name' => $user->role ? $user->role->nombre : 'Sin rol'
                    ],
                    'bandeja' => $assignedTickets,
                    'proyectos' => $userProjects,
                    'kpis' => [
                        'tickets_creados' => $ticketsCreated,
                        'tickets_asignados' => $ticketsAssigned,
                        'tickets_alta_prioridad' => $ticketsHighPriority,
                        'actualizaciones_realizadas' => $userUpdates,
                        'tiempo_total_minutos' => $userTotalTime
                    ],
                    'charts' => [
                        'top_projects' => $topProjects,
                        'top_tickets' => $topTickets
                    ]
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener los datos del dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
