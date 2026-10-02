<?php
namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call(DefaultDataUsersTableSeeder::class);
        $this->call(GenderSeeder::class);
        $this->call(LgaDistrictSeeder::class);
        $this->call(DuplicateFilenoSeeder::class);
        $this->call(PayrollPeriodsSeeder::class);
        $this->call(PayrollRatesSeeder::class);
    }
}
