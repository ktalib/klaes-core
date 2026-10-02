<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserTypesAndLevelsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data
        DB::table('user_levels')->delete();
        DB::table('user_types')->delete();

        // Insert User Types
        $userTypes = [
            [
                'id' => 1,
                'name' => 'Management',
                'code' => 'MGT',
                'description' => 'Management level with highest access',
                'level_priority' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 2,
                'name' => 'Operations',
                'code' => 'OPS',
                'description' => 'Operational staff with high access level',
                'level_priority' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 3,
                'name' => 'ALL',
                'code' => 'ALL',
                'description' => 'Universal access for all users',
                'level_priority' => 2,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 4,
                'name' => 'User',
                'code' => 'USER',
                'description' => 'Basic user with lowest access level',
                'level_priority' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => 5,
                'name' => 'System',
                'code' => 'SYS',
                'description' => 'System administrator with highest privileges',
                'level_priority' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('user_types')->insert($userTypes);

        // Insert User Levels
        $userLevels = [
            // Management levels
            [
                'name' => 'Highest',
                'code' => 'HIGHEST',
                'description' => 'Highest level access for management',
                'user_type_id' => 1, // Management
                'priority' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // Operations levels
            [
                'name' => 'Administrative',
                'code' => 'ADMIN',
                'description' => 'Administrative operations level',
                'user_type_id' => 2, // Operations
                'priority' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Technical',
                'code' => 'TECH',
                'description' => 'Technical operations level',
                'user_type_id' => 2, // Operations
                'priority' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Finance',
                'code' => 'FIN',
                'description' => 'Finance operations level',
                'user_type_id' => 2, // Operations
                'priority' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'High',
                'code' => 'HIGH',
                'description' => 'High level operations access',
                'user_type_id' => 2, // Operations
                'priority' => 3,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // ALL levels
            [
                'name' => 'Lowest',
                'code' => 'LOWEST',
                'description' => 'Lowest level access for all users',
                'user_type_id' => 3, // ALL
                'priority' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // User levels
            [
                'name' => 'Lowest',
                'code' => 'LOWEST',
                'description' => 'Lowest level access for basic users',
                'user_type_id' => 4, // User
                'priority' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
            // System levels
            [
                'name' => 'Highest',
                'code' => 'HIGHEST',
                'description' => 'Highest system access',
                'user_type_id' => 5, // System
                'priority' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'High',
                'code' => 'HIGH',
                'description' => 'High system access',
                'user_type_id' => 5, // System
                'priority' => 4,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('user_levels')->insert($userLevels);
    }
}