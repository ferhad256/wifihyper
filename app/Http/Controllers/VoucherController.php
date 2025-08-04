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

    /**
     * Delete all vouchers for a specific package
     */
    public function deleteAllForPackage(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
        ]);

        if ($validator->fails()) {
            return back()->with('error', 'Invalid package selected.');
        }

        // Verify the package belongs to the tenant
        $package = Package::where('id', $request->package_id)
            ->whereHas('hotspot', function ($query) use ($tenant) {
                $query->where('tenant_id', $tenant->id);
            })
            ->first();

        if (!$package) {
            return back()->with('error', 'Unauthorized action.');
        }

        try {
            $deletedCount = $tenant->vouchers()
                ->where('package_id', $request->package_id)
                ->where('status', 'unused')
                ->delete();

            return back()->with('success', "Successfully deleted {$deletedCount} vouchers for package '{$package->name}'.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete vouchers: ' . $e->getMessage());
        }
    }

    /**
     * Upload vouchers from CSV file
     */
    public function uploadCsv(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'csv_file' => 'required|file|mimes:csv,txt|max:2048',
            'expires_at' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator);
        }

        try {
            $file = $request->file('csv_file');
            $packageId = $request->package_id;
            $expiresAt = $request->expires_at;

            $imported = 0;
            $skipped = 0;
            $errors = [];

            // Read CSV file
            $handle = fopen($file->getPathname(), 'r');
            
            // Skip header row if it exists
            $firstRow = fgetcsv($handle);
            $hasHeader = false;
            
            // Check if first row contains headers
            if ($firstRow && (strtolower($firstRow[0]) === 'voucher' || strtolower($firstRow[0]) === 'code' || strtolower($firstRow[0]) === 'voucher_code')) {
                $hasHeader = true;
            } else {
                // Reset file pointer if no header
                rewind($handle);
            }

            while (($row = fgetcsv($handle)) !== false) {
                if (empty($row[0])) continue;

                $code = trim($row[0]);

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
                        'package_id' => $packageId,
                        'expires_at' => $expiresAt,
                        'status' => 'unused',
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to create voucher '{$code}': " . $e->getMessage();
                }
            }

            fclose($handle);

            $message = "Successfully imported {$imported} vouchers from CSV.";
            if ($skipped > 0) {
                $message .= " Skipped {$skipped} existing vouchers.";
            }
            if (!empty($errors)) {
                $message .= " Errors: " . implode(', ', $errors);
            }

            return back()->with('success', $message);
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to upload CSV: ' . $e->getMessage());
        }
    }
}
