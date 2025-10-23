<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\Project;
use App\Models\User;

class TicketViewController extends Controller
{
    public function showTicket($id)
    {
        $ticket = Ticket::with(['threads.user', 'threads.attachments'])->find($id);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket no encontrado'], 404);
        }

        // Generar URLs temporales para cada attachment
        foreach ($ticket->threads as $thread) {
            foreach ($thread->attachments as $attachment) {
                $attachment->temp_url = $attachment->getTemporaryUrl(1440);
            }
        }

        return response()->json($ticket, 200);
    }

    public function listTickets(Request $request)
    {
        // Iniciamos la query con las relaciones correctas
        $query = Ticket::with(['status', 'creator', 'assignee', 'closedBy', 'project', 'priority']);

        // Filtros opcionales
        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->filled('assigned_to')) {
            $query->where('assigned_to', $request->assigned_to);
        }

        if ($request->filled('closed_by')) {
            $query->where('closed_by', $request->closed_by);
        }

        if ($request->filled('status_id')) {
            $query->where('status_id', $request->status_id);
        }

        if ($request->filled('priority_id')) {
            $query->where('priority_id', $request->priority_id);
        }

        // Filtro por rango de fechas
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        } elseif ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        } elseif ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Ordenar del más reciente al más antiguo
        $tickets = $query->orderBy('created_at', 'desc')->get();

        // Estructura compatible con tu frontend
        $ticketsData = $tickets->map(function ($ticket) {
            return [
                'ticket_number' => $ticket->ticket_number,
                'titulo' => $ticket->titulo,
                'prioridad' => $ticket->priority->nombre ?? 'Sin prioridad',
                'estado' => $ticket->status->nombre ?? 'Sin estado',
                'proyecto' => $ticket->project->nombre ?? 'Sin proyecto',
                'created_by_id' => $ticket->creator->id ?? null,
                'created_by' => trim(($ticket->creator->nombre ?? '') . ' ' . ($ticket->creator->apellido ?? '')),
                'closed_by ' => $ticket->closedBy->nombre ?? null,
                'created_at' => $ticket->created_at->format('m/d/y, h:i A'),
            ];
        });

        return response()->json($ticketsData);
    }
    public function listUsers()
    {
        $users = User::select('id', 'nombre', 'apellido', 'puesto')->get();

        $usersData = $users->map(function ($user) {
            $nombreCompletoPuesto = trim($user->nombre . ' ' . $user->apellido) . ' - ' . $user->puesto;

            return [
                'id' => $user->id,
                'user' => $nombreCompletoPuesto,
            ];
        });

        return response()->json($usersData);
    }

    public function listProjects()
    {
        $projects = Project::select('id', 'nombre')->get();

        return response()->json($projects);
    }
}
