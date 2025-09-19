<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;

class TicketViewController extends Controller
{
    public function showTicket($id)
    {
        $ticket = Ticket::with(['threads.user','threads.attachments'])->find($id);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket no encontrado'], 404);
        }

        // Generar URLs temporales para cada attachment
        foreach ($ticket->threads as $thread) {
            foreach ($thread->attachments as $attachment) {
                $attachment->temp_url = $attachment->getTemporaryUrl(1440); // 1440 minutos = 1 día
            }
        }

        return response()->json($ticket, 200);
    }
}