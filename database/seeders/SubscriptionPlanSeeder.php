<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SubscriptionPlan;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Starter Plan (Free)
        SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'description' => 'Perfect for small businesses and startups. Get started with WiFi management for free.',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'is_active' => true,
            'is_featured' => true,
            'sort_order' => 1,
            
            // Plan Limits
            'max_hotspots' => 3,
            'max_vouchers_per_month' => 5000,
            'max_users' => 1,
            'max_transactions_per_month' => 1000,
            
            // Transaction Fees (UGX)
            'transaction_fees' => [
                [
                    'min' => 0,
                    'max' => 1000,
                    'percentage' => 15,
                    'description' => 'UGX 1000 and below - 15%'
                ],
                [
                    'min' => 1001,
                    'max' => 5000,
                    'percentage' => 10,
                    'description' => 'UGX 1000 to 5000 - 10%'
                ],
                [
                    'min' => 5001,
                    'max' => 999999999,
                    'percentage' => 5,
                    'description' => 'UGX 5000 and above - 5%'
                ]
            ],
            
            // Features
            'features' => [
                'basic_wifi_management',
                'voucher_system',
                'payment_processing',
                'sms_notifications',
                'email_notifications',
                'basic_analytics',
                'default_captive_portal',
                'mobile_responsive',
                'multi_tenant_support'
            ],
            
            // Restrictions
            'restrictions' => [
                'no_source_code_access',
                'no_custom_portal',
                'no_api_access',
                'no_priority_support',
                'limited_analytics',
                'standard_support'
            ],
            
            // Portal Settings
            'custom_portal' => false,
            'source_code_access' => false,
            'api_access' => false,
            'priority_support' => false,
        ]);

        // Create Pro Plan
        SubscriptionPlan::create([
            'name' => 'Pro',
            'slug' => 'pro',
            'description' => 'Advanced features for growing businesses. More hotspots, vouchers, and custom portal.',
            'monthly_price' => 30000, // UGX 30,000
            'yearly_price' => 300000, // UGX 300,000 (2 months free)
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 2,
            
            // Plan Limits
            'max_hotspots' => 10,
            'max_vouchers_per_month' => 10000,
            'max_users' => 3,
            'max_transactions_per_month' => 5000,
            
            // Transaction Fees
            'transaction_fees' => [
                [
                    'min' => 0,
                    'max' => 1000,
                    'percentage' => 15,
                    'description' => 'UGX 1000 and below - 15%'
                ],
                [
                    'min' => 1001,
                    'max' => 5000,
                    'percentage' => 10,
                    'description' => 'UGX 1000 to 5000 - 10%'
                ],
                [
                    'min' => 5001,
                    'max' => 999999999,
                    'percentage' => 5,
                    'description' => 'UGX 5000 and above - 5%'
                ]
            ],
            
            // Features
            'features' => [
                'basic_wifi_management',
                'voucher_system',
                'payment_processing',
                'sms_notifications',
                'email_notifications',
                'advanced_analytics',
                'custom_portal',
                'api_access',
                'priority_support',
                'multi_user_support',
                'advanced_reporting',
                'custom_branding'
            ],
            
            // Restrictions
            'restrictions' => [
                'no_source_code_access',
                'limited_api_calls',
                'standard_priority_support'
            ],
            
            // Portal Settings
            'custom_portal' => true,
            'source_code_access' => false,
            'api_access' => true,
            'priority_support' => true,
        ]);

        // Create Enterprise Plan
        SubscriptionPlan::create([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'description' => 'Full-featured solution for large businesses. Unlimited everything with source code access and dedicated support.',
            'monthly_price' => 0, // Custom pricing - contact sales
            'yearly_price' => 0, // Custom pricing - contact sales
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 3,
            
            // Plan Limits
            'max_hotspots' => -1, // Unlimited
            'max_vouchers_per_month' => -1, // Unlimited
            'max_users' => -1, // Unlimited
            'max_transactions_per_month' => -1, // Unlimited
            
            // Transaction Fees - No charges
            'transaction_fees' => [
                [
                    'min' => 0,
                    'max' => 999999999,
                    'percentage' => 0,
                    'description' => 'No transaction charges'
                ]
            ],
            
            // Features
            'features' => [
                'basic_wifi_management',
                'voucher_system',
                'payment_processing',
                'sms_notifications',
                'email_notifications',
                'advanced_analytics',
                'custom_portal',
                'api_access',
                'source_code_access',
                'priority_support',
                'dedicated_support',
                'multi_user_support',
                'advanced_reporting',
                'custom_branding',
                'custom_integrations',
                'white_label_solution',
                'on_premise_deployment',
                'sales_contact_button'
            ],
            
            // Restrictions
            'restrictions' => [
                'none'
            ],
            
            // Portal Settings
            'custom_portal' => true,
            'source_code_access' => true,
            'api_access' => true,
            'priority_support' => true,
        ]);
    }
}
