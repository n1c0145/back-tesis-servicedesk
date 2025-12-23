<?php

namespace App\Http\Controllers\Reporting;

use App\Models\Ticket;
use App\Models\Project;
use App\Models\TicketThread;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportingController extends Controller
{
    public function generateReport(Request $request)
    {
        try {
            // Validar los parámetros de entrada
            $validated = $request->validate([
                'project_id' => 'nullable|integer|exists:projects,id',
                'date_from' => 'nullable|date_format:Y-m-d|required_with:date_to',
                'date_to' => 'nullable|date_format:Y-m-d|required_with:date_from',
            ]);

            // Construir la consulta base para tickets
            $ticketQuery = Ticket::query();

            // Aplicar filtro por proyecto si se envía
            if (!empty($validated['project_id'])) {
                $ticketQuery->where('project_id', $validated['project_id']);
            }

            // Aplicar filtro por rango de fechas si se envía (siempre vienen juntos o no vienen)
            if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
                $dateTo = Carbon::parse($validated['date_to'])->endOfDay();

                $ticketQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }

            // Obtener los IDs de tickets filtrados para usar en otras consultas
            $filteredTicketIds = $ticketQuery->pluck('id')->toArray();

            // 1. Total de tickets
            $totalTickets = $ticketQuery->count();

            // 2. Tickets con SLA = 1
            $ticketsSLA = $ticketQuery->clone()->where('sla', 1)->count();

            // 3. Tickets resueltos (status = 7)
            $ticketsResolved = $ticketQuery->clone()->where('status_id', 7)->count();

            // 4. Tickets abiertos (status != 7)
            $ticketsOpen = $ticketQuery->clone()->where('status_id', '!=', 7)->count();

            // 5. Tiempo de resolución promedio (solo tickets resueltos)
            $resolutionTimeQuery = Ticket::where('status_id', 7);

            // Aplicar los mismos filtros a la consulta de tiempo de resolución
            if (!empty($validated['project_id'])) {
                $resolutionTimeQuery->where('project_id', $validated['project_id']);
            }

            if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
                $dateTo = Carbon::parse($validated['date_to'])->endOfDay();
                $resolutionTimeQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }

            $resolvedTickets = $resolutionTimeQuery->get();

            $avgResolutionTime = 0;
            $totalSeconds = 0;
            $countResolved = 0;

            foreach ($resolvedTickets as $ticket) {
                if ($ticket->created_at && $ticket->updated_at) {
                    $seconds = $ticket->created_at->diffInSeconds($ticket->updated_at);
                    $totalSeconds += $seconds;
                    $countResolved++;
                }
            }

            if ($countResolved > 0) {
                $avgResolutionTime = $totalSeconds / $countResolved;
                // Convertir a horas, minutos, segundos para mejor legibilidad
                $avgHours = floor($avgResolutionTime / 3600);
                $avgMinutes = floor(($avgResolutionTime % 3600) / 60);
                $avgSeconds = $avgResolutionTime % 60;
                $avgResolutionFormatted = sprintf("%02d:%02d:%02d", $avgHours, $avgMinutes, $avgSeconds);
            } else {
                $avgResolutionFormatted = "00:00:00";
            }
            // 6. Promedio de minutos del campo 'time' (solo tickets resueltos status 7)
            $timeMinutesQuery = Ticket::where('status_id', 7)
                ->whereNotNull('time') // Solo tickets que tienen valor en el campo time
                ->where('time', '>', 0); // Solo valores positivos

            // Aplicar los mismos filtros
            if (!empty($validated['project_id'])) {
                $timeMinutesQuery->where('project_id', $validated['project_id']);
            }

            if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
                $dateTo = Carbon::parse($validated['date_to'])->endOfDay();
                $timeMinutesQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }

            // Calcular promedio
            $ticketsWithTime = $timeMinutesQuery->get();
            $totalTimeMinutes = 0;
            $countTimeTickets = 0;

            foreach ($ticketsWithTime as $ticket) {
                if ($ticket->time !== null) {
                    $totalTimeMinutes += $ticket->time;
                    $countTimeTickets++;
                }
            }

            $averageTimeMinutes = $countTimeTickets > 0 ? ($totalTimeMinutes / $countTimeTickets) : 0;

            // 6. Cantidad de actualizaciones generales (ticket threads)
            $threadsQuery = TicketThread::query();

            // Filtrar por tickets si hay filtros aplicados
            if (!empty($filteredTicketIds)) {
                $threadsQuery->whereIn('ticket_id', $filteredTicketIds);
            } else if (!empty($validated['project_id']) || (!empty($validated['date_from']) && !empty($validated['date_to']))) {
                // Si hay filtros pero no hay tickets (el filtro devolvió 0 tickets), retornar 0
                $totalThreads = 0;
                $publicThreads = 0;
                $privateThreads = 0;
            } else {
                // Contar todos los threads sin filtro
                $totalThreads = $threadsQuery->count();
                $publicThreads = $threadsQuery->clone()->where('private', 0)->count();
                $privateThreads = $threadsQuery->clone()->where('private', 1)->count();
            }

            if (!isset($totalThreads)) {
                $totalThreads = $threadsQuery->count();
                $publicThreads = $threadsQuery->clone()->where('private', 0)->count();
                $privateThreads = $threadsQuery->clone()->where('private', 1)->count();
            }

            // Construir la respuesta
            $report = [
                'report_parameters' => [
                    'project_id' => $validated['project_id'] ?? null,
                    'date_from' => $validated['date_from'] ?? null,
                    'date_to' => $validated['date_to'] ?? null,
                    'generated_at' => now()->toDateTimeString(),
                ],
                'tickets_summary' => [
                    'total_tickets' => $totalTickets,
                    'tickets_with_sla' => $ticketsSLA,
                    'tickets_resolved' => $ticketsResolved,
                    'tickets_open' => $ticketsOpen,
                ],
                'resolution_metrics' => [
                    'average_resolution_seconds' => $avgResolutionTime,
                    'average_resolution_formatted' => $avgResolutionFormatted,
                    'tickets_used_for_calculation' => $countResolved,
                    'average_time_minutes' => $averageTimeMinutes,
                    'tickets_with_time_count' => $countTimeTickets,
                ],
                'updates_summary' => [
                    'total_updates' => $totalThreads,
                    'public_updates' => $publicThreads,
                    'private_updates' => $privateThreads,
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Reporte generado exitosamente',
                'data' => $report
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
