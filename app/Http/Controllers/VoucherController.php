<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Voucher;
use App\Models\Package;
use App\Services\EgoSmsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Concerns\ResolvesTenant;

class VoucherController extends Controller
{
    use ResolvesTenant;


    /**
     * Display a listing of vouchers
     */
    public function index()
    {
        $tenant = $this->tenant();
        
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

        // Group only UNUSED vouchers by hotspot
        $vouchersByHotspot = $tenant->vouchers()
            ->where('status', 'unused')
            ->with(['package.hotspot'])
            ->get()
            ->groupBy(function ($voucher) {
                return $voucher->package ? $voucher->package->hotspot_id : 'no_hotspot';
            });

        // Get overall statistics for UNUSED vouchers only
        $totalVouchers = $tenant->vouchers()->where('status', 'unused')->count();
        $unusedVouchers = $totalVouchers; // All vouchers shown are unused
        $usedVouchers = $tenant->vouchers()->where('status', 'used')->count();
        $expiredVouchers = $tenant->vouchers()->where('status', 'expired')->count();

        return view('dashboard.vouchers.index', compact(
            'tenant', 
            'vouchersByHotspot', 
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
        $tenant = $this->tenant();
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        // Plan limits removed - all tenants can upload vouchers

        $validator = Validator::make($request->all(), [
            'code' => 'required|string|unique:vouchers,code',
            'package_id' => 'nullable|exists:packages,id',
            'expires_at' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Get hotspot_id from package
            $hotspotId = null;
            if ($request->package_id) {
                $package = Package::find($request->package_id);
                if ($package) {
                    $hotspotId = $package->hotspot_id;
                }
            }

            // Handle empty expiry date
            $expiresAt = $request->expires_at ? $request->expires_at : null;

            Voucher::create([
                'tenant_id' => $tenant->id,
                'hotspot_id' => $hotspotId,
                'code' => $request->code,
                'package_id' => $request->package_id,
                'expires_at' => $expiresAt,
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
        $tenant = $this->tenant();
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'package_id' => 'required|exists:packages,id',
            'voucher_codes' => 'required|string|min:1',
            'expires_at' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Get hotspot_id from package
            $package = Package::find($request->package_id);
            if (!$package) {
                return back()->with('error', 'Package not found.')->withInput();
            }

            // Plan limits removed - all tenants can upload vouchers

            // Handle empty expiry date
            $expiresAt = $request->expires_at ? $request->expires_at : null;

            // SIMPLE PARSING: Split by any whitespace or comma
            $rawInput = $request->voucher_codes;
            
            // Remove any carriage returns and normalize line endings
            $rawInput = str_replace(["\r\n", "\r"], "\n", $rawInput);
            
            // Split by newlines first
            $lines = explode("\n", $rawInput);
            $codes = [];
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) continue;
                
                // If line contains commas, split by commas too
                if (strpos($line, ',') !== false) {
                    $commaParts = explode(',', $line);
                    foreach ($commaParts as $part) {
                        $part = trim($part);
                        if (!empty($part)) {
                            $codes[] = $part;
                        }
                    }
                } else {
                    $codes[] = $line;
                }
            }
            
            // Remove duplicates while preserving order
            $codes = array_unique($codes);
            
            // Validate that we have at least one code
            if (empty($codes)) {
                return back()->with('error', 'No valid voucher codes provided.')->withInput();
            }

            // Plan limits removed - all tenants can upload unlimited vouchers
            
            $imported = 0;
            $skipped = 0;
            $errors = [];
            $invalidCodes = [];

            foreach ($codes as $code) {
                // Validate voucher code format (alphanumeric, 3-20 characters)
                if (!preg_match('/^[a-zA-Z0-9]{3,20}$/', $code)) {
                    $invalidCodes[] = $code;
                    continue;
                }

                // Check if voucher already exists
                $existingVoucher = Voucher::where('code', $code)->first();
                if ($existingVoucher) {
                    $skipped++;
                    continue;
                }

                try {
                    Voucher::create([
                        'tenant_id' => $tenant->id,
                        'hotspot_id' => $package->hotspot_id,
                        'code' => $code,
                        'package_id' => $request->package_id,
                        'expires_at' => $expiresAt,
                        'status' => 'unused',
                    ]);
                    $imported++;
                } catch (\Exception $e) {
                    $errors[] = "Failed to create voucher '{$code}': " . $e->getMessage();
                }
            }

            // Build success message
            $message = "Successfully imported {$imported} vouchers.";
            if ($skipped > 0) {
                $message .= " Skipped {$skipped} existing vouchers.";
            }
            if (!empty($invalidCodes)) {
                $message .= " Skipped " . count($invalidCodes) . " invalid voucher codes: " . implode(', ', array_slice($invalidCodes, 0, 5));
                if (count($invalidCodes) > 5) {
                    $message .= " and " . (count($invalidCodes) - 5) . " more";
                }
            }
            if (!empty($errors)) {
                $message .= " Errors: " . implode(', ', array_slice($errors, 0, 3));
                if (count($errors) > 3) {
                    $message .= " and " . (count($errors) - 3) . " more errors";
                }
            }

            // Determine message type
            if ($imported > 0) {
                return back()->with('success', $message);
            } else {
                return back()->with('warning', $message)->withInput();
            }

        } catch (\Exception $e) {
            \Log::error('Multiple voucher upload failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
                'package_id' => $request->package_id,
            ]);
            return back()->with('error', 'Failed to upload vouchers: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Export vouchers
     */
    public function export()
    {
        $tenant = $this->tenant();
        
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
        $tenant = $this->tenant();
        
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
        $tenant = $this->tenant();
        
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
     * Delete all vouchers for a specific hotspot (all packages under that hotspot)
     */
    public function deleteAllForHotspot(Request $request)
    {
        $tenant = $this->tenant();
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $validator = Validator::make($request->all(), [
            'hotspot_id' => 'required|exists:hotspots,id',
        ]);

        if ($validator->fails()) {
            return back()->with('error', 'Invalid hotspot selected.');
        }

        // Verify the hotspot belongs to the tenant
        $hotspot = $tenant->hotspots()->where('id', $request->hotspot_id)->first();
        if (!$hotspot) {
            return back()->with('error', 'Unauthorized action.');
        }

        try {
            $deletedCount = $tenant->vouchers()
                ->where('hotspot_id', $request->hotspot_id)
                ->where('status', 'unused')
                ->delete();

            return back()->with('success', "Successfully deleted {$deletedCount} vouchers for hotspot '{$hotspot->name}'.");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete vouchers: ' . $e->getMessage());
        }
    }

    /**
     * Upload vouchers from CSV file
     */
    public function uploadCsv(Request $request)
    {
        $tenant = $this->tenant();
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        // Plan limits removed - all tenants can upload vouchers

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
            
            // Handle empty expiry date
            $expiresAt = $request->expires_at ? $request->expires_at : null;

            // Get hotspot_id from package
            $package = Package::find($packageId);
            if (!$package) {
                return back()->with('error', 'Package not found.');
            }

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
                        'hotspot_id' => $package->hotspot_id,
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
