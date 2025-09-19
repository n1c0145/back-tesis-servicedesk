<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;
use App\Models\TicketThread;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller
{
    public function store(Request $request)
    {
        // Validación básica
        $data = $request->validate([
            'titulo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'project_id' => 'required|exists:projects,id',
            'created_by' => 'required|exists:users,id',
            'mensaje_inicial' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            // Crear ticket
            $ticket = Ticket::create([
                'titulo' => $data['titulo'],
                'descripcion' => $data['descripcion'] ?? null,
                'project_id' => $data['project_id'],
                'created_by' => $data['created_by'],
                'assigned_to' => null,
                'closed_by' => null,
                'status_id' => 1,
            ]);

            // Crear primer hilo
            $thread = TicketThread::create([
                'ticket_id' => $ticket->id,
                'user_id' => $data['created_by'],
                'mensaje' => $data['mensaje_inicial'],
                'private' => 0,
            ]);

            DB::commit();

            return response()->json([
                'ticket' => $ticket,
                'primer_hilo' => $thread,
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
        ]);

        DB::beginTransaction();
        try {
            $thread = TicketThread::create([
                'ticket_id' => $data['ticket_id'],
                'user_id' => $data['user_id'],
                'mensaje' => $data['mensaje'],
                'private' => $data['private'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'ticket' => $data['ticket_id'],
                'nuevo_hilo' => $thread,
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
