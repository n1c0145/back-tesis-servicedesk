<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketThread;
use Illuminate\Support\Facades\DB;
use App\Models\TicketThreadAttachment;
use App\Models\TicketThreadHistory;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use App\Notifications\NewTicketNormal;
use App\Notifications\NewTicketSla;
use App\Notifications\TicketAssigned;
use App\Notifications\TicketPriorityChanged;

class TicketController extends Controller
{
    // Crear ticket con primer hilo
    public function store(Request $request)
    {
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'created_by' => 'required|exists:users,id',
            'assigned_to' => 'nullable|exists:users,id',
            'mensaje_inicial' => 'required|string',
            'sla' => 'required|in:0,1',
            'priority_id' => 'nullable|exists:ticket_priorities,id',
            'archivos.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
        ]);

        DB::beginTransaction();
        try {
            // Generar ticket_number
            $yearMonth = now()->format('ym');
            $ultimoTicket = Ticket::where('ticket_number', 'like', $yearMonth . '%')
                ->orderBy('ticket_number', 'desc')
                ->first();
            $correlativo = intval($ultimoTicket?->ticket_number ? substr($ultimoTicket->ticket_number, 4) : 0) + 1;
            $ticketNumber = $yearMonth . str_pad($correlativo, 4, '0', STR_PAD_LEFT);
            $priorityId = null;

            if ($data['sla'] == 1) {

                $priorityId = 4;
            } else {

                $priorityId = $data['priority_id'];
            }
            // Crear ticket
            $ticket = Ticket::create([
                'ticket_number' => $ticketNumber,
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'] ?? null,
                'project_id' => $data['project_id'],
                'created_by' => $data['created_by'],
                'assigned_to' => $data['assigned_to'] ?? null,
                'closed_by' => null,
                'status_id' => 1,
                'sla' => $data['sla'],
                'priority_id' => $priorityId,
            ]);
            $ticket = $ticket->fresh();

            $thread = TicketThread::create([
                'ticket_id' => $ticket->id,
                'user_id' => $data['created_by'],
                'mensaje' => $data['mensaje_inicial'],
                'private' => 0,
            ]);

            $archivosSubidos = [];
            if ($request->hasFile('archivos')) {
                foreach ($request->file('archivos') as $file) {
                    $name = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs("ticket_threads/{$thread->id}", $name, 's3');

                    $attachment = TicketThreadAttachment::create([
                        'thread_id' => $thread->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                    ]);

                    $archivosSubidos[] = $attachment;
                }
            }

            // Notificaciones
            $projectName = $ticket->project->nombre ?? 'Proyecto desconocido';

            if ($data['sla'] == 0) {
                foreach ($ticket->project->users as $user) {
                    $user->notify(new NewTicketNormal(
                        $ticket->ticket_number,
                        $ticket->project->nombre ?? 'Proyecto desconocido'
                    ));
                }
            } else {
                foreach ($ticket->project->users as $user) {
                    $user->notify(new NewTicketSla(
                        $ticket->ticket_number,
                        $ticket->project->nombre ?? 'Proyecto desconocido'
                    ));
                }
            }
            if ($ticket->assigned_to) {
                $assignee = $ticket->assignee;
                $assignee->notify(new TicketAssigned($ticket->ticket_number, $projectName));
            }

            DB::commit();

            return response()->json([
                'ticket' => $ticket,
                'primer_hilo' => $thread,
                'archivos' => $archivosSubidos,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    public function addThread(Request $request)
    {
        $data = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'user_id' => 'required|exists:users,id',
            'mensaje' => 'required|string',
            'private' => 'required|integer|in:0,1',
            'tiempo' => 'nullable|integer|min:0',
            'archivos.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
            // Campos opcionales
            'status_id' => 'nullable|integer|exists:ticket_statuses,id',
            'priority_id' => 'nullable|integer|exists:ticket_priorities,id',
            'assigned_to' => 'nullable|integer|in:0,' . implode(',', User::pluck('id')->toArray()),

        ]);

        DB::beginTransaction();
        try {
            $thread = TicketThread::create([
                'ticket_id' => $data['ticket_id'],
                'user_id' => $data['user_id'],
                'mensaje' => $data['mensaje'],
                'private' => $data['private'],
            ]);
            $thread->refresh();

            $ticket = Ticket::with(['project', 'project.users', 'status', 'priority', 'assignee'])
                ->find($data['ticket_id']);
            $changes = [];

            // tiempo que se adiciona
            if (!empty($data['tiempo']) && $data['tiempo'] > 0) {
                $ticket->increment('time', $data['tiempo']);
            }

            // Cambio de estado
            if (!empty($data['status_id']) && $data['status_id'] != $ticket->status_id) {
                $oldStatus = $ticket->status->nombre ?? 'Desconocido';
                $ticket->update(['status_id' => $data['status_id']]);
                $ticket->load('status');
                $newStatus = $ticket->status->nombre ?? 'Desconocido';

                $changes['status'] = [
                    'from' => $oldStatus,
                    'to' => $newStatus
                ];

                if ($data['status_id'] == 7) {
                    $ticket->update(['closed_by' => $data['user_id']]);
                }

                foreach ($ticket->project->users as $user) {
                    $user->notify(new TicketStatusChanged($ticket->ticket_number, $ticket->project->nombre, $newStatus));
                }
            }

            // Cambio de prioridad
            if (!empty($data['priority_id']) && $data['priority_id'] != $ticket->priority_id) {
                $oldPriority = $ticket->priority->nombre ?? 'Desconocido';
                $ticket->update(['priority_id' => $data['priority_id']]);
                $ticket->load('priority');
                $newPriority = $ticket->priority->nombre ?? 'Desconocido';

                $changes['priority'] = [
                    'from' => $oldPriority,
                    'to' => $newPriority
                ];

                foreach ($ticket->project->users as $user) {
                    $user->notify(new TicketPriorityChanged($ticket->ticket_number, $ticket->project->nombre, $newPriority));
                }
            }

            // Cambio de asignado
            if (array_key_exists('assigned_to', $data) && $data['assigned_to'] !== $ticket->assigned_to) {
                $oldUser = $ticket->assignee
                    ? $ticket->assignee->nombre . ' ' . $ticket->assignee->apellido
                    : 'Bandeja General del Proyecto';
                $oldUserId = $ticket->assigned_to;

                $newAssignedTo = ($data['assigned_to'] == 0) ? null : $data['assigned_to'];

                $ticket->update(['assigned_to' => $newAssignedTo]);
                $ticket->load('assignee');

                $newUser = $ticket->assignee
                    ? $ticket->assignee->nombre . ' ' . $ticket->assignee->apellido
                    : 'Bandeja General del Proyecto';
                $newUserId = $ticket->assigned_to;

                $changes['assigned'] = [
                    'from' => ['id' => $oldUserId, 'name' => $oldUser],
                    'to' => ['id' => $newUserId, 'name' => $newUser],
                ];

                if ($ticket->assignee) {
                    $ticket->assignee->notify(new TicketAssigned($ticket->ticket_number, $ticket->project->nombre));
                }
            }
            if (!empty($changes)) {
                TicketThreadHistory::create([
                    'thread_id' => $thread->id,
                    'ticket_id' => $ticket->id,
                    'changes' => $changes
                ]);
            }

            // archivos Adjuntos
            $archivosSubidos = [];
            if ($request->hasFile('archivos')) {
                foreach ($request->file('archivos') as $file) {
                    $name = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs("ticket_threads/{$thread->id}", $name, 's3');

                    $attachment = TicketThreadAttachment::create([
                        'thread_id' => $thread->id,
                        'file_name' => $file->getClientOriginalName(),
                        'file_path' => $path,
                    ]);

                    $archivosSubidos[] = $attachment;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'ticket' => $ticket,
                'nuevo_hilo' => $thread,
                'changes' => $changes,
                'archivos' => $archivosSubidos,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => 'No se pudo crear el hilo',
                'detalles' => $e->getMessage(),
            ], 500);
        }
    }
}
