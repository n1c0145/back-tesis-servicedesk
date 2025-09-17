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
}
