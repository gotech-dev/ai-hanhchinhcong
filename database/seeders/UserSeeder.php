<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Admin user
        User::updateOrCreate(
            ['email' => 'admin@gotechjsc.com'],
            [
                'name' => 'Admin',
                'email' => 'admin@gotechjsc.com',
                'password' => Hash::make('123456'),
                'is_admin' => true,
            ]
        );

        // Create Regular user
        User::updateOrCreate(
            ['email' => 'gotechjsc@gmail.com'],
            [
                'name' => 'User',
                'email' => 'gotechjsc@gmail.com',
                'password' => Hash::make('123456'),
                'is_admin' => false,
            ]
        );

        $this->command->info('Users created successfully!');
        $this->command->info('Admin: admin@gotechjsc.com / 123456');
        $this->command->info('User: gotechjsc@gmail.com / 123456');
    }
}
