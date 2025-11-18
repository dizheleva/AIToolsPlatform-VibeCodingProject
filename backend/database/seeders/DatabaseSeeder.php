<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user (owner) - use updateOrCreate to avoid duplicates
        // Password will be automatically hashed by the User model's setPasswordAttribute mutator
        User::updateOrCreate(
            ['email' => 'ivan@admin.local'],
            [
                'name' => 'Иван Иванов',
                'password' => 'password', // Will be hashed automatically
                'role' => 'owner',
                'status' => 'approved',
                'email_verified_at' => now(),
            ]
        );

        // Create example users with different roles (pending approval)
        $users = [
            [
                'name' => 'Елена Петрова',
                'email' => 'elena@frontend.local',
                'role' => 'frontend',
            ],
            [
                'name' => 'Петър Георгиев',
                'email' => 'petar@backend.local',
                'role' => 'backend',
            ],
            [
                'name' => 'Мария Димитрова',
                'email' => 'maria@qa.local',
                'role' => 'qa',
            ],
            [
                'name' => 'Георги Стоянов',
                'email' => 'georgi@pm.local',
                'role' => 'pm',
            ],
            [
                'name' => 'Анна Николова',
                'email' => 'anna@designer.local',
                'role' => 'designer',
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => 'password', // Will be hashed automatically
                    'role' => $userData['role'],
                    'status' => 'pending',
                    'email_verified_at' => now(),
                ]
            );
        }

        // Seed AI Tools and Categories
        $this->call([
            AiToolsSeeder::class,
        ]);
    }
}
