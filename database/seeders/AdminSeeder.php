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
        try {
            $this->command->info('Starting admin account setup...');

            // Get admin details from environment variables or use defaults
            $primaryAdminEmail = env('ADMIN_EMAIL', 'walusimbifahd@gmail.com');
            $primaryAdminName = env('ADMIN_NAME', 'Fahd Walusimbi');
            $primaryAdminPassword = env('ADMIN_PASSWORD', 'admin123456');
            
            $secondaryAdminEmail = env('ADMIN_EMAIL_2', 'kirondetom1@gmail.com');
            $secondaryAdminName = env('ADMIN_NAME_2', 'Tom Kironde');
            $secondaryAdminPassword = env('ADMIN_PASSWORD_2', 'admin123456');

            // Validate email addresses
            if (!filter_var($primaryAdminEmail, FILTER_VALIDATE_EMAIL)) {
                $this->command->error("Invalid primary admin email: {$primaryAdminEmail}");
                return;
            }
            
            if (!filter_var($secondaryAdminEmail, FILTER_VALIDATE_EMAIL)) {
                $this->command->error("Invalid secondary admin email: {$secondaryAdminEmail}");
                return;
            }

            // Validate password strength in production
            if (app()->environment('production')) {
                if (strlen($primaryAdminPassword) < 8) {
                    $this->command->error('Primary admin password must be at least 8 characters long');
                    return;
                }
                if (strlen($secondaryAdminPassword) < 8) {
                    $this->command->error('Secondary admin password must be at least 8 characters long');
                    return;
                }
            }

            // Create admin accounts with environment-based configuration
            $admins = [
                [
                    'name' => trim($primaryAdminName),
                    'email' => strtolower(trim($primaryAdminEmail)),
                    'password' => Hash::make($primaryAdminPassword),
                    'role' => 'super_admin',
                    'is_active' => true,
                ],
                [
                    'name' => trim($secondaryAdminName),
                    'email' => strtolower(trim($secondaryAdminEmail)),
                    'password' => Hash::make($secondaryAdminPassword),
                    'role' => 'super_admin',
                    'is_active' => true,
                ],
            ];

            $createdCount = 0;
            $existingCount = 0;

            foreach ($admins as $adminData) {
                try {
                    $admin = Admin::firstOrCreate(
                        ['email' => $adminData['email']],
                        $adminData
                    );

                    if ($admin->wasRecentlyCreated) {
                        $this->command->info("✓ Created admin account: {$adminData['email']}");
                        $createdCount++;
                    } else {
                        // Update password if it exists but ensure other fields are current
                        $admin->update([
                            'name' => $adminData['name'],
                            'password' => $adminData['password'],
                            'role' => $adminData['role'],
                            'is_active' => $adminData['is_active'],
                        ]);
                        $this->command->info("✓ Updated existing admin account: {$adminData['email']}");
                        $existingCount++;
                    }
                } catch (\Exception $e) {
                    $this->command->error("Failed to create/update admin {$adminData['email']}: " . $e->getMessage());
                }
            }

            $this->command->info("Admin accounts setup completed! (Created: {$createdCount}, Updated: {$existingCount})");
            
            // Show security warnings
            if (app()->environment('production')) {
                if ($primaryAdminPassword === 'admin123456' || $secondaryAdminPassword === 'admin123456') {
                    $this->command->warn('⚠️  WARNING: Default passwords detected in production!');
                    $this->command->warn('   Please set ADMIN_PASSWORD and ADMIN_PASSWORD_2 in your .env file');
                    $this->command->warn('   Using strong, unique passwords is critical for security');
                } else {
                    $this->command->info('✓ Custom passwords configured for production');
                }
                
                // Additional production security reminders
                $this->command->info('Production Security Checklist:');
                $this->command->info('- Change default admin passwords regularly');
                $this->command->info('- Enable two-factor authentication if available');
                $this->command->info('- Monitor admin login logs');
                $this->command->info('- Review admin permissions periodically');
            }

        } catch (\Exception $e) {
            $this->command->error('Admin seeder failed: ' . $e->getMessage());
            $this->command->error('Stack trace: ' . $e->getTraceAsString());
            throw $e; // Re-throw to ensure deployment fails if admin setup fails
        }
    }
}