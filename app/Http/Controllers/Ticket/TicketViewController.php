<?php

namespace App\Http\Controllers\Ticket;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;

class TicketViewController extends Controller
{
    public function showTicket($id)
    {
        $ticket = Ticket::with(['threads.user'])->find($id);

        if (!$ticket) {
            return response()->json(['error' => 'Ticket no encontrado'], 404);
        }

        return response()->json($ticket, 200);
    }
}
