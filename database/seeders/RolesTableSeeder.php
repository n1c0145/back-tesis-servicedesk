<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RolesTableSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['nombre' => 'Admin'],
            ['nombre' => 'Project Manager'], 
            ['nombre' => 'Usuario'], 
            ['nombre' => 'Cliente'], 
        ];

        foreach ($roles as $role) {
            Role::create($role);
        }
    }
}
