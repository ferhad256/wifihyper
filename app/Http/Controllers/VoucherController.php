<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Voucher;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VoucherController extends Controller
{
    /**
     * Display a listing of vouchers
     */
    public function index()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        // Get all packages for the tenant
        $packages = $tenant->hotspots()
            ->with('packages')
            ->get()
            ->flatMap(function ($hotspot) {
                return $hotspot->packages;
            });

        // Group only UNUSED vouchers by package
        $vouchersByPackage = $tenant->vouchers()
            ->where('status', 'unused')
            ->with('package')
            ->get()
            ->groupBy('package_id');

        // Get overall statistics for UNUSED vouchers only
        $totalVouchers = $tenant->vouchers()->where('status', 'unused')->count();
        $unusedVouchers = $totalVouchers; // All vouchers shown are unused
        $usedVouchers = $tenant->vouchers()->where('status', 'used')->count();
        $expiredVouchers = $tenant->vouchers()->where('status', 'expired')->count();

        return view('dashboard.vouchers.index', compact(
            'tenant', 
            'vouchersByPackage', 
            'packages', 
            'totalVouchers',
            'unusedVouchers',
            'usedVouchers',
            'expiredVouchers'
        ));
    }

    /**
     * Store a newly created voucher
     */
    public function store(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:vouchers,code',
            'package_id' => 'nullable|exists:packages,id',
            'expires_at' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            Voucher::create([
                'tenant_id' => $tenant->id,
                'code' => $request->code,
                'package_id' => $request->package_id,
                'expires_at' => $request->expires_at,
                'status' => 'unused',
            ]);

            return back()->with('success', 'Voucher created successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create voucher.')->withInput();
        }
    }

    /**
     * Upload multiple vouchers for specific packages
     */
    public function uploadMultiple(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'voucher_codes' => 'required|string',
            'expires_at' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            // Parse voucher codes (comma-separated or newline-separated)
            $codes = array_filter(array_map('trim', explode("\n", str_replace(',', "\n", $request->voucher_codes))));
            
            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($codes as $code) {
                if (empty($code)) continue;

                // Check if voucher already exists
                $existingVoucher = Voucher::where('code', $code)->first();
                if ($existingVoucher) {
                    $skipped++;
                    continue;
                }

                try {
                    Voucher::create([
                        'tenant_id' => $tenant->id,
                        'code' => $code,
                        'package_id' => $request->package_id,
                        'expires_at' => $request->expires_at,
                        'status' => 'unused',
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to create voucher '{$code}': " . $e->getMessage();
                }
            }

            $message = "Successfully imported {$imported} vouchers.";
            if ($skipped > 0) {
                $message .= " Skipped {$skipped} existing vouchers.";
            }
            if (!empty($errors)) {
                $message .= " Errors: " . implode(', ', $errors);
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to upload vouchers: ' . $e->getMessage());
        }
    }

    /**
     * Export vouchers
     */
    public function export()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $vouchers = $tenant->vouchers()
            ->where('status', 'unused')
            ->with('package')
            ->get();

        $filename = 'available_vouchers_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function() use ($vouchers) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($file, ['Voucher Code', 'Package', 'Status', 'Expires At']);
            
            // Add data
            foreach ($vouchers as $voucher) {
                fputcsv($file, [
                    $voucher->code,
                    $voucher->package ? $voucher->package->name : 'No Package',
                    'Available',
                    $voucher->expires_at ? $voucher->expires_at->format('Y-m-d') : 'No Expiry',
                ]);
            }
            
            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Remove the specified voucher
     */
    public function destroy(Voucher $voucher)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant || $voucher->tenant_id !== $tenant->id) {
            return back()->with('error', 'Unauthorized action.');
        }

        try {
            $voucher->delete();
            return back()->with('success', 'Voucher deleted successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete voucher.');
        }
    }
}
