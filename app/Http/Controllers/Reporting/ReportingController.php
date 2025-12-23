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
            // Validar los parámetros 
            $validated = $request->validate([
                'project_id' => 'nullable|integer|exists:projects,id',
                'date_from' => 'nullable|date_format:Y-m-d|required_with:date_to',
                'date_to' => 'nullable|date_format:Y-m-d|required_with:date_from',
            ]);

            //consulta con filtros
            $baseQuery = function () use ($validated) {
                $query = Ticket::query();

                if (!empty($validated['project_id'])) {
                    $query->where('project_id', $validated['project_id']);
                }

                if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                    $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
                    $dateTo = Carbon::parse($validated['date_to'])->endOfDay();
                    $query->whereBetween('created_at', [$dateFrom, $dateTo]);
                }

                return $query;
            };

            $ticketQuery = $baseQuery();

            $filteredTicketIds = $ticketQuery->pluck('id')->toArray();

            $totalTickets = $ticketQuery->count();

            $ticketsSLA = (clone $ticketQuery)->where('sla', 1)->count();

            $ticketsSinSLA = (clone $ticketQuery)->where('sla', 0)->count();

            $ticketsResolved = (clone $ticketQuery)->where('status_id', 7)->count();

            $ticketsOpen = (clone $ticketQuery)->where('status_id', '!=', 7)->count();

            $resolutionTimeQuery = $baseQuery()->where('status_id', 7);
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
                $avgHours = floor($avgResolutionTime / 3600);
                $avgMinutes = floor(($avgResolutionTime % 3600) / 60);
                $avgSeconds = $avgResolutionTime % 60;
                $avgResolutionFormatted = sprintf("%02d:%02d:%02d", $avgHours, $avgMinutes, $avgSeconds);
            } else {
                $avgResolutionFormatted = "00:00:00";
            }

            $timeMinutesQuery = $baseQuery()
                ->where('status_id', 7)
                ->whereNotNull('time')
                ->where('time', '>', 0);

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

            $threadsQuery = TicketThread::query();

            if (!empty($filteredTicketIds)) {
                $threadsQuery->whereIn('ticket_id', $filteredTicketIds);
            } else if (!empty($validated['project_id']) || (!empty($validated['date_from']) && !empty($validated['date_to']))) {
                $totalThreads = 0;
                $publicThreads = 0;
                $privateThreads = 0;
            } else {
                $totalThreads = $threadsQuery->count();
                $publicThreads = (clone $threadsQuery)->where('private', 0)->count();
                $privateThreads = (clone $threadsQuery)->where('private', 1)->count();
            }

            if (!isset($totalThreads)) {
                $totalThreads = $threadsQuery->count();
                $publicThreads = (clone $threadsQuery)->where('private', 0)->count();
                $privateThreads = (clone $threadsQuery)->where('private', 1)->count();
            }

            $estadosCount = [];
            for ($i = 1; $i <= 7; $i++) {
                $estadosCount[$i] = (clone $ticketQuery)->where('status_id', $i)->count();
            }

            $prioridadesCount = [];
            for ($i = 1; $i <= 4; $i++) {
                $prioridadesCount[$i] = (clone $ticketQuery)->where('priority_id', $i)->count();
            }

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
                ],
                'charts' => [
                    'open_vs_close' => [
                        [
                            'name' => 'Abiertos',
                            'value' => $ticketsOpen
                        ],
                        [
                            'name' => 'Cerrados',
                            'value' => $ticketsResolved
                        ]
                    ],
                    'sla_vs_no_sla' => [
                        [
                            'name' => 'Con SLA',
                            'value' => $ticketsSLA
                        ],
                        [
                            'name' => 'Sin SLA',
                            'value' => $ticketsSinSLA
                        ]
                    ],
                    'status_distribution' => [
                        [
                            'name' => 'Abierto',
                            'value' => $estadosCount[1] ?? 0
                        ],
                        [
                            'name' => 'Primera Respuesta',
                            'value' => $estadosCount[2] ?? 0
                        ],
                        [
                            'name' => 'Se necesita más Información',
                            'value' => $estadosCount[3] ?? 0
                        ],
                        [
                            'name' => 'En Progreso',
                            'value' => $estadosCount[4] ?? 0
                        ],
                        [
                            'name' => 'En Espera',
                            'value' => $estadosCount[5] ?? 0
                        ],
                        [
                            'name' => 'Resuelto',
                            'value' => $estadosCount[6] ?? 0
                        ],
                        [
                            'name' => 'Cerrado',
                            'value' => $estadosCount[7] ?? 0
                        ]
                    ],
                    'priority_distribution' => [
                        [
                            'name' => 'Baja',
                            'value' => $prioridadesCount[1] ?? 0
                        ],
                        [
                            'name' => 'Media',
                            'value' => $prioridadesCount[2] ?? 0
                        ],
                        [
                            'name' => 'Alta',
                            'value' => $prioridadesCount[3] ?? 0
                        ],
                        [
                            'name' => 'Sin asignar',
                            'value' => $prioridadesCount[4] ?? 0
                        ]
                    ]
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
