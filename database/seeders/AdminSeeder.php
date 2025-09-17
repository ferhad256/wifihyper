<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create default super admin accounts
        $admins = [
            [
                'name' => 'Fahd Walusimbi',
                'email' => 'walusimbifahd@gmail.com',
                'password' => Hash::make('admin123456'),
                'role' => 'super_admin',
                'is_active' => true,
            ],
            [
                'name' => 'Tom Kironde',
                'email' => 'kirondetom1@gmail.com',
                'password' => Hash::make('admin123456'),
                'role' => 'super_admin',
                'is_active' => true,
            ],
        ];

        foreach ($admins as $adminData) {
            Admin::firstOrCreate(
                ['email' => $adminData['email']],
                $adminData
            );
        }

        $this->command->info('Default admin accounts created successfully!');
        $this->command->info('Emails: walusimbifahd@gmail.com, kirondetom1@gmail.com');
        $this->command->info('Default password: admin123456');
    }
}