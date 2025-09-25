<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketThread;
use Illuminate\Support\Facades\DB;
use App\Models\TicketThreadAttachment;
use App\Notifications\TicketStatusChanged;
use App\Notifications\NewTicketNormal;
use App\Notifications\NewTicketSla;
use App\Notifications\TicketAssigned;

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
            'mensaje_inicial' => 'required|string',
            'sla' => 'required|in:0,1',
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

            // Crear ticket
            $ticket = Ticket::create([
                'ticket_number' => $ticketNumber,
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'] ?? null,
                'project_id' => $data['project_id'],
                'created_by' => $data['created_by'],
                'assigned_to' => null,
                'closed_by' => null,
                'status_id' => 1,
                'sla' => $data['sla'],
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
    // Crear nuevo hilo
    public function addThread(Request $request)
    {
        $data = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
            'user_id' => 'required|exists:users,id',
            'mensaje' => 'required|string',
            'private' => 'required|integer|in:0,1',
            'tiempo' => 'nullable|integer|min:0',
            'archivos.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
        ]);

        DB::beginTransaction();
        try {
            $thread = TicketThread::create([
                'ticket_id' => $data['ticket_id'],
                'user_id' => $data['user_id'],
                'mensaje' => $data['mensaje'],
                'private' => $data['private'],
            ]);

            if (isset($data['tiempo']) && $data['tiempo'] > 0) {
                DB::table('tickets')
                    ->where('id', $data['ticket_id'])
                    ->increment('time', $data['tiempo']);
            }

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
                'ticket' => $data['ticket_id'],
                'nuevo_hilo' => $thread,
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
    public function updateStatus(Request $request, $id)
    {
        $ticket = Ticket::with(['project', 'project.users', 'status'])->find($id);

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket no encontrado'
            ], 404);
        }

        $data = $request->validate([
            'status_id' => 'required|integer|exists:ticket_statuses,id'
        ]);

        $ticket->update([
            'status_id' => $data['status_id']
        ]);

        $ticket->load('status');

        $newStatus = $ticket->status->nombre ?? "ID {$data['status_id']}";
        $projectName = $ticket->project->nombre ?? 'Proyecto desconocido';

        foreach ($ticket->project->users as $user) {
            $user->notify(new TicketStatusChanged($ticket->ticket_number, $projectName, $newStatus));
        }

        return response()->json([
            'message' => 'Estado del ticket actualizado correctamente',
            'ticket' => $ticket
        ]);
    }
    //asiganr ticket a user
    public function updateAssignedTo(Request $request, $id)
    {
        $ticket = Ticket::with('project')->find($id);

        if (!$ticket) {
            return response()->json([
                'message' => 'Ticket no encontrado'
            ], 404);
        }

        $data = $request->validate([
            'assigned_to' => 'required|integer|exists:users,id'
        ]);

        $ticket->update([
            'assigned_to' => $data['assigned_to']
        ]);

        // Notificar al usuario asignado usando la relación assignee
        $user = $ticket->assignee;
        if ($user) {
            $user->notify(new TicketAssigned($ticket->ticket_number, $ticket->project->nombre ?? 'Proyecto desconocido'));
        }

        return response()->json([
            'message' => 'Ticket asignado correctamente',
            'ticket' => $ticket
        ]);
    }
}
