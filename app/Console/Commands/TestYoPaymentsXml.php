<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\YoPaymentsService;

class TestYoPaymentsXml extends Command
{
    protected $signature = 'payment:test-xml {method=actransactioncheckstatus}';
    protected $description = 'Test Yo Payments XML request format for compliance with API specification';

    public function handle()
    {
        $method = $this->argument('method');
        
        $this->info("=== Testing Yo Payments XML Request Format ===");
        $this->line("Method: {$method}");
        $this->newLine();
        
        $yoPayments = new YoPaymentsService();
        
        // Test different parameter combinations
        $testCases = [
            'Basic Transaction Check' => [
                'TransactionReference' => 'TEST_REF_123',
                'DepositTransactionType' => 'PULL'
            ],
            'With Private Reference' => [
                'TransactionReference' => 'TEST_REF_123',
                'DepositTransactionType' => 'PULL',
                'PrivateTransactionReference' => 'TXN_123'
            ],
            'PUSH Type' => [
                'TransactionReference' => 'TEST_REF_123',
                'DepositTransactionType' => 'PUSH'
            ],
            'Minimal Parameters' => [
                'TransactionReference' => 'TEST_REF_123'
            ]
        ];
        
        foreach ($testCases as $testName => $parameters) {
            $this->info("--- {$testName} ---");
            $this->line("Parameters: " . json_encode($parameters, JSON_PRETTY_PRINT));
            $this->newLine();
            
            try {
                // Use reflection to access the protected method
                $reflection = new \ReflectionClass($yoPayments);
                $buildXmlMethod = $reflection->getMethod('buildXmlRequest');
                $buildXmlMethod->setAccessible(true);
                
                $xmlRequest = $buildXmlMethod->invoke($yoPayments, $method, $parameters);
                
                $this->line("Generated XML:");
                $this->line($xmlRequest);
                
                // Validate XML structure
                $xml = simplexml_load_string($xmlRequest);
                if ($xml) {
                    $this->info("✅ XML is valid");
                    
                    // Check required elements
                    $requiredElements = ['APIUsername', 'APIPassword', 'Method'];
                    $missingElements = [];
                    
                    foreach ($requiredElements as $element) {
                        if (!isset($xml->Request->$element)) {
                            $missingElements[] = $element;
                        }
                    }
                    
                    if (empty($missingElements)) {
                        $this->info("✅ All required elements present");
                    } else {
                        $this->warn("⚠️  Missing required elements: " . implode(', ', $missingElements));
                    }
                    
                    // Check method
                    if ((string)$xml->Request->Method === $method) {
                        $this->info("✅ Method correctly set to: {$method}");
                    } else {
                        $this->error("❌ Method mismatch. Expected: {$method}, Got: " . $xml->Request->Method);
                    }
                    
                } else {
                    $this->error("❌ XML is invalid");
                }
                
            } catch (\Exception $e) {
                $this->error("Error generating XML: " . $e->getMessage());
            }
            
            $this->newLine();
            $this->line("=" . str_repeat("=", 50));
            $this->newLine();
        }
        
        $this->info("XML Format Testing Completed!");
        $this->newLine();
        $this->info("Expected Format (from Yo Payments API spec):");
        $this->line("<?xml version=\"1.0\" encoding=\"UTF-8\"?>");
        $this->line("<AutoCreate>");
        $this->line(" <Request>");
        $this->line("  <APIUsername></APIUsername>");
        $this->line("  <APIPassword></APIPassword>");
        $this->line("  <Method>actransactioncheckstatus</Method>");
        $this->line("  <TransactionReference></TransactionReference>");
        $this->line("  <PrivateTransactionReference></PrivateTransactionReference>");
        $this->line("  <DepositTransactionType></DepositTransactionType>");
        $this->line(" </Request>");
        $this->line("</AutoCreate>");
        
        return 0;
    }
} 