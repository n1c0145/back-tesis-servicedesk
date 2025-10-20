<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TicketPriority;

class TicketPrioritySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $priorities = [
            ['nombre' => 'Baja'],
            ['nombre' => 'Media'],
            ['nombre' => 'Alta'],
            ['nombre' => 'Sin asignar'],
        ];

        TicketPriority::insert($priorities);
    }
}
