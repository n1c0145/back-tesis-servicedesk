<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\Project;
use App\Models\User;
use App\Models\KnowledgeBase;

class TicketViewController extends Controller
{
    public function showTicket($id)
    {
        $ticket = Ticket::with([
            'threads' => function ($query) {
                $query->orderBy('created_at', 'asc');
                $query->with(['user', 'attachments', 'histories']);
            },
            'project',
            'creator',
            'assignee',
            'closedBy',
            'status',
            'priority'
        ])->find($id);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket no encontrado'], 404);
        }

        foreach ($ticket->threads as $thread) {
            foreach ($thread->attachments as $attachment) {
                $attachment->temp_url = $attachment->getTemporaryUrl(1440);
            }

            if ($thread->user) {
                $thread->user = [
                    'id' => $thread->user->id,
                    'nombre_completo' => trim($thread->user->nombre . ' ' . $thread->user->apellido),
                ];
            }
        }

        $response = [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'titulo' => $ticket->titulo,
            'descripcion' => $ticket->descripcion,
            'time' => $ticket->time,
            'sla' => $ticket->sla,
            'project_id' => $ticket->project_id,
            'project_name' => $ticket->project->nombre ?? null,
            'created_by' => $ticket->created_by,
            'created_by_nombre' => $ticket->creator
                ? trim($ticket->creator->nombre . ' ' . $ticket->creator->apellido)
                : null,
            'assigned_to' => $ticket->assigned_to,
            'assigned_to_nombre' => $ticket->assignee
                ? trim($ticket->assignee->nombre . ' ' . $ticket->assignee->apellido)
                : null,
            'closed_by' => $ticket->closed_by,
            'closed_by_nombre' => $ticket->closedBy
                ? trim($ticket->closedBy->nombre . ' ' . $ticket->closedBy->apellido)
                : null,
            'status_id' => $ticket->status_id,
            'status_name' => $ticket->status->nombre ?? null,
            'priority_id' => $ticket->priority_id,
            'priority_name' => $ticket->priority->nombre ?? null,
            'fecha_creacion' => $ticket->created_at,
            'fecha_actualizacion' => $ticket->updated_at,
            'threads' => $ticket->threads->map(function ($thread) {
                return [
                    'id' => $thread->id,
                    'mensaje' => $thread->mensaje,
                    'private' => $thread->private,
                    'created_at' => $thread->created_at,
                    'user' => $thread->user,
                    'attachments' => $thread->attachments->map(function ($a) {
                        return [
                            'id' => $a->id,
                            'nombre_archivo' => $a->file_name,
                            'url' => $a->temp_url,
                        ];
                    }),
                    'histories' => $thread->histories->map(function ($h) {
                        return [
                            'time' => $h->time,
                            'changes' => $h->changes
                        ];
                    }),
                ];
            }),
        ];

        return response()->json($response);
    }


    public function listTickets(Request $request)
    {
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

        // Filtro de fechas
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

        $tickets = $query->orderBy('created_at', 'desc')->get();

        $ticketsData = $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
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

    public function similarTickets(Request $request)
    {
        $data = $request->validate([
            'titulo' => 'required|string',
            'descripcion' => 'nullable|string',
        ]);

        $texto = strtolower($data['titulo'] . ' ' . ($data['descripcion'] ?? ''));

        // Sacar palabras clave 
        $palabras = collect(preg_split('/\s+/', $texto))
            ->filter(fn($p) => strlen($p) > 3)
            ->unique()
            ->values();

        if ($palabras->count() == 0) {
            return response()->json([
                'success' => true,
                'matches' => [],
            ]);
        }

        $query = KnowledgeBase::query();
        $query->select('*');

        $query->where(function ($q) use ($palabras) {
            foreach ($palabras as $p) {
                $q->orWhere('titulo', 'LIKE', "%$p%")
                    ->orWhere('descripcion', 'LIKE', "%$p%");
            }
        });

        $candidatos = $query->get();

        // Calcular ranking
        $resultados = $candidatos->map(function ($item) use ($palabras) {

            $textoKB = strtolower($item->titulo . ' ' . $item->descripcion);

            $score = 0;

            foreach ($palabras as $p) {

                // Coincidencia exacta
                if (str_contains($textoKB, $p)) {
                    $score += 2;
                    continue;
                }

                //  Coincidencia aproximada 
                $distancia = levenshtein($p, $textoKB);

                if ($distancia <= 3) {

                    $score += 1;
                }
            }

            $item->similarity_score = $score;

            return $item;
        })
            ->filter(fn($x) => $x->similarity_score >= 3)
            ->sortByDesc('similarity_score')
            ->take(3)
            ->values();

        return response()->json([
            'success' => true,
            'matches' => $resultados,
        ]);
    }
}
