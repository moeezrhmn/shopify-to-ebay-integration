<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        if(!User::where('name', 'max')->first()){

            User::factory()->create([
                'name' => 'max',
                'email' => 'usama@maxenius.agency',
                'password' => 'Maxenius123!@#'
            ]);
        }
        
        if(!User::where('name', 'jonathan')->first()){
            
            User::factory()->create([
                'name' => 'jonathan',
                'email' => 'jonathangreenvcuk@gmail.com',
                'password' => 'jonathan123###',
            ]);
        }
    }
}
