<?php

namespace App\Http\Controllers;

use App\Models\Hotspot;
use App\Models\Package;
use App\Models\Transaction;
use App\Services\VoucherAvailabilityService;
use Illuminate\Http\Request;

class PortalController extends Controller
{
    protected $voucherAvailabilityService;

    public function __construct()
    {
        $this->voucherAvailabilityService = new VoucherAvailabilityService();
    }

    /**
     * Show captive portal for a hotspot
     */
    public function index($hotspotName)
    {
        $hotspot = Hotspot::where('name', $hotspotName)
            ->orWhereRaw("LOWER(REPLACE(REPLACE(REPLACE(name, ' ', '-'), '.', ''), '_', '')) = ?", [strtolower($hotspotName)])
            ->firstOrFail();
        
        if (!$hotspot->is_active) {
            return redirect()->route('portal.inactive', $hotspot->name);
        }

        $packages = $hotspot->packages()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Enhanced voucher availability check using the service
        $packagesWithVoucherStatus = $packages->map(function($package) use ($hotspot) {
            $availability = $this->voucherAvailabilityService->checkVoucherAvailability($hotspot->tenant, $package);
            
            $package->available_vouchers = $availability['available'];
            $package->has_vouchers = $availability['has_vouchers'];
            $package->is_low_stock = $availability['is_low'];
            $package->is_out_of_stock = $availability['is_out'];
            
            return $package;
        });

        return view('portal.index', compact('hotspot', 'packages'));
    }

    /**
     * Show payment form for a specific package
     */
    public function payment($hotspotName, Request $request)
    {
        $hotspot = Hotspot::where('name', $hotspotName)->firstOrFail();
        $package = Package::findOrFail($request->package_id);
        
        if (!$hotspot->is_active || !$package->is_active) {
            return redirect()->route('portal.inactive', $hotspot->name);
        }

        return view('portal.payment', compact('hotspot', 'package'));
    }

    /**
     * Show inactive hotspot page
     */
    public function inactive($hotspotName)
    {
        $hotspot = Hotspot::where('name', $hotspotName)->firstOrFail();
        
        return view('portal.inactive', compact('hotspot'));
    }

    /**
     * Show payment pending page
     */
    public function pending($transactionId)
    {
        $transaction = Transaction::where('transaction_id', $transactionId)->first();
        
        if (!$transaction) {
            abort(404, 'Transaction not found');
        }
        
        return view('portal.pending', compact('transaction'));
    }

    /**
     * Show payment success page
     */
    public function success(Request $request)
    {
        return view('portal.success');
    }

    /**
     * Show payment failed page
     */
    public function failed(Request $request)
    {
        return view('portal.failed');
    }

    /**
     * Test portal functionality
     */
    public function test($hotspotName)
    {
        $hotspot = Hotspot::where('name', $hotspotName)->firstOrFail();
        $packages = $hotspot->packages()->where('is_active', true)->get();
        
        return view('portal.test', compact('hotspot', 'packages'));
    }

    /**
     * API endpoint to check voucher availability in real-time
     */
    public function checkAvailability($hotspotName, Request $request)
    {
        $hotspot = Hotspot::where('name', $hotspotName)->firstOrFail();
        $packageId = $request->input('package_id');
        
        if (!$packageId) {
            return response()->json(['error' => 'Package ID is required'], 400);
        }

        $package = Package::findOrFail($packageId);
        
        if ($package->hotspot_id != $hotspot->id) {
            return response()->json(['error' => 'Package does not belong to this hotspot'], 400);
        }

        $availability = $this->voucherAvailabilityService->checkVoucherAvailability($hotspot->tenant, $package);
        
        return response()->json([
            'available' => $availability['available'],
            'has_vouchers' => $availability['has_vouchers'],
            'is_low_stock' => $availability['is_low'],
            'is_out_of_stock' => $availability['is_out'],
            'timestamp' => now()->toISOString(),
        ]);
    }
}
