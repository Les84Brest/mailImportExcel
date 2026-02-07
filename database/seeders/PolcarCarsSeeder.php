<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PolcarCar;

class PolcarCarsSeeder extends Seeder
{
    public function run(): void
    {
        // создаём 1000 записей через фабрику
        PolcarCar::factory()->count(1000)->create();
    }
}
