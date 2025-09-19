<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketThread;
use Illuminate\Support\Facades\DB;
use App\Models\TicketThreadAttachment;
use Illuminate\Support\Facades\Storage;

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
            'archivos.*' => 'nullable|file|mimes:jpg,jpeg,png,pdf,doc,docx|max:10240',
        ]);

        DB::beginTransaction();
        try {
            $ticket = Ticket::create([
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'] ?? null,
                'project_id' => $data['project_id'],
                'created_by' => $data['created_by'],
                'assigned_to' => null,
                'closed_by' => null,
                'status_id' => 1,
            ]);

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
}
