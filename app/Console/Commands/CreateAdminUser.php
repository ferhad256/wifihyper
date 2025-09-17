<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create 
                            {--name= : The name of the admin user}
                            {--email= : The email of the admin user}
                            {--password= : The password of the admin user}
                            {--role=admin : The role of the admin user (admin|super_admin)}
                            {--force : Force creation without confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin user for the application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Creating new admin user...');

        // Get input data
        $name = $this->option('name') ?: $this->ask('Enter admin name');
        $email = $this->option('email') ?: $this->ask('Enter admin email');
        $password = $this->option('password') ?: $this->secret('Enter admin password');
        $role = $this->option('role') ?: $this->choice('Select admin role', ['admin', 'super_admin'], 'admin');

        // Validate input
        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ], [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,super_admin',
        ]);

        if ($validator->fails()) {
            $this->error('Validation failed:');
            foreach ($validator->errors()->all() as $error) {
                $this->error("  - $error");
            }
            return Command::FAILURE;
        }

        // Check if admin already exists
        if (Admin::where('email', $email)->exists()) {
            $this->error("Admin with email '$email' already exists!");
            return Command::FAILURE;
        }

        // Show confirmation unless force flag is used
        if (!$this->option('force')) {
            $this->table(['Field', 'Value'], [
                ['Name', $name],
                ['Email', $email],
                ['Role', $role],
                ['Password', str_repeat('*', strlen($password))],
            ]);

            if (!$this->confirm('Do you want to create this admin user?')) {
                $this->info('Admin creation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Create admin user
        try {
            $admin = Admin::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'role' => $role,
                'is_active' => true,
            ]);

            $this->info("✅ Admin user created successfully!");
            $this->info("   Name: {$admin->name}");
            $this->info("   Email: {$admin->email}");
            $this->info("   Role: {$admin->role}");
            
            if (app()->environment('production')) {
                $this->warn('🔒 Remember to store the password securely!');
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create admin user: {$e->getMessage()}");
            return Command::FAILURE;
        }
    }
}
