<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TicketStatus;

class TicketStatusSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['nombre' => 'Abierto'], 
            ['nombre' => 'Primera Respuesta'], 
            ['nombre' => 'Se necesita mas Información'], 
            ['nombre' => 'En Progreso'], 
            ['nombre' => 'En Espera'], 
            ['nombre' => 'Resuelto'], 
            ['nombre' => 'Cerrado'], 
            ['nombre' => 'Cancelado'], 
        ];

        foreach ($statuses as $status) {
            TicketStatus::create($status);
        }
    }
}
