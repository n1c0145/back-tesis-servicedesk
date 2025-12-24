<?php

namespace App\Http\Controllers\Reporting;

use App\Models\Ticket;
use App\Models\Project;
use App\Models\TicketThread;
use App\Models\TicketThreadHistory;
use App\Models\User;
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

            $allTickets = $ticketQuery->get(['created_at']);

            if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                $dateFrom = Carbon::parse($validated['date_from']);
                $dateTo = Carbon::parse($validated['date_to']);
                $diasRango = $dateFrom->diffInDays($dateTo) + 1;
                $fechaReferencia = $dateTo->endOfDay();
            } else {
                $dateFrom = now()->subDays(29)->startOfDay();
                $dateTo = now()->endOfDay();
                $diasRango = 30;
                $fechaReferencia = $dateTo;
            }

            $contadores = [
                '24h' => 0,
                '24a3d' => 0,
                '3a7d' => 0,
                '7a15d' => 0,
                '15a30d' => 0
            ];

            foreach ($allTickets as $ticket) {
                if ($ticket->created_at && $ticket->created_at <= $fechaReferencia) {
                    $diasAntiguedad = ceil($ticket->created_at->floatDiffInDays($fechaReferencia));

                    if ($diasAntiguedad < 1) {
                        $contadores['24h']++;
                    } elseif ($diasAntiguedad <= 3) {
                        $contadores['24a3d']++;
                    } elseif ($diasAntiguedad <= 7) {
                        $contadores['3a7d']++;
                    } elseif ($diasAntiguedad <= 15) {
                        $contadores['7a15d']++;
                    } elseif ($diasAntiguedad <= 30) {
                        $contadores['15a30d']++;
                    }
                }
            }

            $backlogAgingDistribution = [
                ['name' => '< 24h', 'value' => $contadores['24h']]
            ];

            if ($diasRango >= 3) {
                $backlogAgingDistribution[] = ['name' => '24h - 3d', 'value' => $contadores['24a3d']];
            }
            if ($diasRango >= 7) {
                $backlogAgingDistribution[] = ['name' => '3d - 7d', 'value' => $contadores['3a7d']];
            }
            if ($diasRango >= 15) {
                $backlogAgingDistribution[] = ['name' => '7d - 15d', 'value' => $contadores['7a15d']];
            }
            if ($diasRango >= 30) {
                $backlogAgingDistribution[] = ['name' => '15d - 30d', 'value' => $contadores['15a30d']];
            }

            $historyTimeQuery = TicketThreadHistory::query()
                ->leftJoin('ticket_threads', 'ticket_thread_histories.thread_id', '=', 'ticket_threads.id')
                ->leftJoin('tickets', 'ticket_threads.ticket_id', '=', 'tickets.id');

            if (!empty($validated['project_id'])) {
                $historyTimeQuery->where('tickets.project_id', $validated['project_id']);
            }

            if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                $historyTimeQuery->whereBetween('ticket_thread_histories.created_at', [
                    Carbon::parse($validated['date_from'])->startOfDay(),
                    Carbon::parse($validated['date_to'])->endOfDay()
                ]);
            }

            $totalTimeHistory = $historyTimeQuery->sum('ticket_thread_histories.time');

            $projectsTimeQuery = Project::query()
                ->leftJoin('tickets', 'projects.id', '=', 'tickets.project_id')
                ->leftJoin('ticket_threads', 'tickets.id', '=', 'ticket_threads.ticket_id')
                ->leftJoin('ticket_thread_histories', 'ticket_threads.id', '=', 'ticket_thread_histories.thread_id')
                ->selectRaw('projects.id, projects.nombre, COALESCE(SUM(ticket_thread_histories.time), 0) as total_time')
                ->groupBy('projects.id', 'projects.nombre');

            if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                $projectsTimeQuery->whereBetween('ticket_thread_histories.created_at', [
                    Carbon::parse($validated['date_from'])->startOfDay(),
                    Carbon::parse($validated['date_to'])->endOfDay()
                ]);
            }

            if (!empty($validated['project_id'])) {
                $projectsTimeQuery->where('projects.id', $validated['project_id']);
            }

            $topProjects = $projectsTimeQuery
                ->orderByDesc('total_time')
                ->limit(5)
                ->get()
                ->map(function ($project) {
                    return [
                        'name' => $project->nombre,
                        'value' => (int)$project->total_time
                    ];
                })
                ->toArray();

            $usersTimeQuery = User::query()
                ->leftJoin('ticket_threads', 'users.id', '=', 'ticket_threads.user_id')
                ->leftJoin('ticket_thread_histories', 'ticket_threads.id', '=', 'ticket_thread_histories.thread_id')
                ->selectRaw('users.id, users.nombre || \' \' || users.apellido as nombre_completo, COALESCE(SUM(ticket_thread_histories.time), 0) as total_time')
                ->groupBy('users.id', 'users.nombre', 'users.apellido');

            if (!empty($filteredTicketIds)) {
                $usersTimeQuery->whereIn('ticket_threads.ticket_id', $filteredTicketIds);
            }

            if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                $usersTimeQuery->whereBetween('ticket_thread_histories.created_at', [
                    Carbon::parse($validated['date_from'])->startOfDay(),
                    Carbon::parse($validated['date_to'])->endOfDay()
                ]);
            }

            if (!empty($validated['project_id'])) {
                $usersTimeQuery->whereExists(function ($query) use ($validated) {
                    $query->select(DB::raw(1))
                        ->from('tickets')
                        ->whereColumn('tickets.id', 'ticket_threads.ticket_id')
                        ->where('tickets.project_id', $validated['project_id']);
                });
            }

            $topUsers = $usersTimeQuery
                ->orderByDesc('total_time')
                ->limit(5)
                ->get()
                ->map(function ($user) {
                    return [
                        'name' => $user->nombre_completo,
                        'value' => (int)$user->total_time
                    ];
                })
                ->toArray();

            $ticketsTimeQuery = Ticket::query()
                ->leftJoin('ticket_threads', 'tickets.id', '=', 'ticket_threads.ticket_id')
                ->leftJoin('ticket_thread_histories', 'ticket_threads.id', '=', 'ticket_thread_histories.thread_id')
                ->selectRaw('tickets.id, tickets.ticket_number, tickets.titulo, COALESCE(SUM(ticket_thread_histories.time), 0) as total_time')
                ->groupBy('tickets.id', 'tickets.ticket_number', 'tickets.titulo');

            if (!empty($validated['project_id'])) {
                $ticketsTimeQuery->where('tickets.project_id', $validated['project_id']);
            }

            if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                $ticketsTimeQuery->whereBetween('ticket_thread_histories.created_at', [
                    Carbon::parse($validated['date_from'])->startOfDay(),
                    Carbon::parse($validated['date_to'])->endOfDay()
                ]);
            }

            $topTickets = $ticketsTimeQuery
                ->orderByDesc('total_time')
                ->limit(5)
                ->get()
                ->map(function ($ticket) {
                    return [
                        'name' => "#{$ticket->ticket_number} - {$ticket->titulo}",
                        'value' => (int)$ticket->total_time
                    ];
                })
                ->toArray();

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
                'time_metrics' => [
                    'total_history_time' => $totalTimeHistory,
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
                    ],
                    'ticket_aging' => $backlogAgingDistribution,
                    'top_projects_time' => $topProjects,
                    'top_users_time' => $topUsers,
                    'top_tickets_time' => $topTickets
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

    public function generateSlaReport(Request $request)
    {
        try {
            // Validar los parámetros de entrada
            $validated = $request->validate([
                'project_id' => 'required|integer|exists:projects,id',
                'date_from' => 'nullable|date_format:Y-m-d|required_with:date_to',
                'date_to' => 'nullable|date_format:Y-m-d|required_with:date_from',
            ]);

            $project = Project::findOrFail($validated['project_id']);

            if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                $dateFrom = Carbon::parse($validated['date_from'])->startOfDay();
                $dateTo = Carbon::parse($validated['date_to'])->endOfDay();
            } else {
                $now = Carbon::now();
                $dateFrom = Carbon::create($now->year, $now->month, 1)->startOfDay();
                $dateTo = $now->endOfDay();
            }

            $baseQuery = function () use ($validated, $dateFrom, $dateTo) {
                $query = Ticket::query();

                $query->where('project_id', $validated['project_id']);

                $query->whereBetween('created_at', [$dateFrom, $dateTo]);

                return $query;
            };

            $ticketQuery = $baseQuery();

            $totalTickets = $ticketQuery->count();
            $ticketsWithSLA = (clone $ticketQuery)->where('sla', 1)->count();
            $ticketsWithoutSLA = (clone $ticketQuery)->where('sla', 0)->count();

            $firstResponseQuery = (clone $ticketQuery)
                ->where('sla', 1)
                ->whereNotNull('firstupdate');

            $firstResponseTickets = $firstResponseQuery->get();

            $firstResponseCumplidos = 0;
            $firstResponseIncumplidos = 0;

            foreach ($firstResponseTickets as $ticket) {
                if ($ticket->created_at && $ticket->firstupdate) {
                    $tiempoPrimeraRespuesta = $ticket->created_at->diffInMinutes($ticket->firstupdate);

                    if ($tiempoPrimeraRespuesta <= $project->firstresponse) {
                        $firstResponseCumplidos++;
                    } else {
                        $firstResponseIncumplidos++;
                    }
                }
            }

            $totalFirstResponse = $firstResponseCumplidos + $firstResponseIncumplidos;
            $porcentajeFirstResponse = $totalFirstResponse > 0
                ? round(($firstResponseCumplidos / $totalFirstResponse) * 100, 2)
                : 0;

            $maxResolutionQuery = (clone $ticketQuery)
                ->where('status_id', 7);

            $maxResolutionTickets = $maxResolutionQuery->get();

            $maxResolutionCumplidos = 0;
            $maxResolutionIncumplidos = 0;

            foreach ($maxResolutionTickets as $ticket) {
                if ($ticket->created_at && $ticket->updated_at) {
                    $tiempoResolucionDias = $ticket->created_at->diffInDays($ticket->updated_at);

                    if ($tiempoResolucionDias <= $project->maxresolution) {
                        $maxResolutionCumplidos++;
                    } else {
                        $maxResolutionIncumplidos++;
                    }
                }
            }

            $totalMaxResolution = $maxResolutionCumplidos + $maxResolutionIncumplidos;
            $porcentajeMaxResolution = $totalMaxResolution > 0
                ? round(($maxResolutionCumplidos / $totalMaxResolution) * 100, 2)
                : 0;

            $effectiveTimeQuery = (clone $ticketQuery)
                ->where('sla', 1)
                ->whereNotNull('time')
                ->where('time', '>', 0);

            $effectiveTimeTickets = $effectiveTimeQuery->get();

            $effectiveTimeCumplidos = 0;
            $effectiveTimeIncumplidos = 0;

            foreach ($effectiveTimeTickets as $ticket) {
                $tiempoHoras = $ticket->time / 60;

                if ($tiempoHoras <= $project->effectivetime) {
                    $effectiveTimeCumplidos++;
                } else {
                    $effectiveTimeIncumplidos++;
                }
            }

            $totalEffectiveTime = $effectiveTimeCumplidos + $effectiveTimeIncumplidos;
            $porcentajeEffectiveTime = $totalEffectiveTime > 0
                ? round(($effectiveTimeCumplidos / $totalEffectiveTime) * 100, 2)
                : 0;

            $hoursBankQuery = (clone $ticketQuery)
                ->whereNotNull('time')
                ->where('time', '>', 0);

            $hoursBankTickets = $hoursBankQuery->get();

            $totalHorasUtilizadas = 0;
            foreach ($hoursBankTickets as $ticket) {
                $totalHorasUtilizadas += ($ticket->time / 60);
            }

            $hoursBank = $project->hoursbank;
            $horasRestantes = max(0, $hoursBank - $totalHorasUtilizadas);
            $porcentajeHorasUtilizadas = $hoursBank > 0
                ? round(($totalHorasUtilizadas / $hoursBank) * 100, 2)
                : ($totalHorasUtilizadas > 0 ? 100 : 0);

            if ($totalHorasUtilizadas > $hoursBank) {
                $porcentajeHorasUtilizadas = round(($totalHorasUtilizadas / $hoursBank) * 100, 2);
            }

            $report = [
                'report_parameters' => [
                    'project_id' => $validated['project_id'],
                    'project_name' => $project->nombre,
                    'date_from' => $dateFrom->format('Y-m-d'),
                    'date_to' => $dateTo->format('Y-m-d'),
                    'generated_at' => now()->toDateTimeString(),
                    'sla_rules' => [
                        'firstresponse_minutes' => $project->firstresponse,
                        'maxresolution_days' => $project->maxresolution,
                        'effectivetime_hours' => $project->effectivetime,
                        'hoursbank_hours' => $project->hoursbank,
                    ]
                ],
                'tickets_summary' => [
                    'total_tickets' => $totalTickets,
                    'tickets_with_sla' => $ticketsWithSLA,
                    'tickets_without_sla' => $ticketsWithoutSLA,
                ],
                'sla_metrics' => [
                    'first_response' => [
                        'compliant' => $firstResponseCumplidos,
                        'non_compliant' => $firstResponseIncumplidos,
                        'total_analyzed' => $totalFirstResponse,
                        'compliance_percentage' => $porcentajeFirstResponse, 
                        'target_minutes' => $project->firstresponse
                    ],
                    'max_resolution' => [
                        'compliant' => $maxResolutionCumplidos,
                        'non_compliant' => $maxResolutionIncumplidos,
                        'total_analyzed' => $totalMaxResolution,
                        'compliance_percentage' => $porcentajeMaxResolution, 
                        'target_days' => $project->maxresolution
                    ],
                    'effective_time' => [
                        'compliant' => $effectiveTimeCumplidos,
                        'non_compliant' => $effectiveTimeIncumplidos,
                        'total_analyzed' => $totalEffectiveTime,
                        'compliance_percentage' => $porcentajeEffectiveTime, 
                        'target_hours' => $project->effectivetime
                    ],
                    'hours_bank' => [
                        'total_hours_used' => round($totalHorasUtilizadas, 2),
                        'hours_contracted' => $project->hoursbank,
                        'hours_remaining' => round($horasRestantes, 2),
                        'utilization_percentage' => $porcentajeHorasUtilizadas, 
                        'status' => $horasRestantes > 0 ? 'available' : 'exceeded'
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'message' => 'Reporte SLA generado exitosamente',
                'data' => $report
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el reporte SLA',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
