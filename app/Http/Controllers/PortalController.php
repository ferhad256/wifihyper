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
        $hotspot = Hotspot::findByUrlName($hotspotName);
        
        if (!$hotspot) {
            abort(404, 'Hotspot not found');
        }
        
        if (!$hotspot->is_active) {
            return redirect()->route('portal.inactive', $hotspot->url_name);
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
        $hotspot = Hotspot::findByUrlName($hotspotName);
        
        if (!$hotspot) {
            abort(404, 'Hotspot not found');
        }
        
        $package = Package::findOrFail($request->package_id);
        
        if (!$hotspot->is_active || !$package->is_active) {
            return redirect()->route('portal.inactive', $hotspot->url_name);
        }

        return view('portal.payment', compact('hotspot', 'package'));
    }

    /**
     * Show inactive hotspot page
     */
    public function inactive($hotspotName)
    {
        $hotspot = Hotspot::findByUrlName($hotspotName);
        
        if (!$hotspot) {
            abort(404, 'Hotspot not found');
        }
        
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
        // Get the latest completed transaction for this session
        $transactionId = session('last_transaction_id');
        $transaction = null;
        $hotspotName = 'default';
        
        if ($transactionId) {
            $transaction = Transaction::where('transaction_id', $transactionId)
                ->where('status', 'completed')
                ->with(['voucher', 'package.hotspot'])
                ->first();
                
            if ($transaction && $transaction->package && $transaction->package->hotspot) {
                $hotspotName = $transaction->package->hotspot->name;
                
                // Voucher is already marked as used during payment completion
            }
        }
        
        return view('portal.success', compact('transaction', 'hotspotName'));
    }

    /**
     * Show payment failed page
     */
    public function failed(Request $request)
    {
        return view('portal.failed');
    }

    /**
     * API endpoint to check voucher availability in real-time
     */
    public function checkAvailability($hotspotName, Request $request)
    {
        $hotspot = Hotspot::findByUrlName($hotspotName);
        
        if (!$hotspot) {
            return response()->json(['error' => 'Hotspot not found'], 404);
        }
        
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

    /**
     * API endpoint to check transaction status in real-time
     */
    public function checkTransactionStatus(Request $request)
    {
        $transactionId = $request->input('transaction_id');
        
        if (!$transactionId) {
            return response()->json(['error' => 'Transaction ID is required'], 400);
        }

        $transaction = Transaction::where('transaction_id', $transactionId)
            ->with(['voucher', 'package.hotspot'])
            ->first();
        
        if (!$transaction) {
            return response()->json(['error' => 'Transaction not found'], 404);
        }

        return response()->json([
            'transaction_id' => $transaction->transaction_id,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'phone_number' => $transaction->phone_number,
            'created_at' => $transaction->created_at,
            'updated_at' => $transaction->updated_at,
            'voucher' => $transaction->voucher ? [
                'code' => $transaction->voucher->code,
                'status' => $transaction->voucher->status,
                'used_at' => $transaction->voucher->used_at,
            ] : null,
            'package' => $transaction->package ? [
                'name' => $transaction->package->name,
                'duration_hours' => $transaction->package->duration_hours,
                'data_limit_mb' => $transaction->package->data_limit_mb,
            ] : null,
            'hotspot' => $transaction->package && $transaction->package->hotspot ? [
                'name' => $transaction->package->hotspot->name,
                'url_name' => $transaction->package->hotspot->url_name,
            ] : null,
            'timestamp' => now()->toISOString(),
        ]);
    }
}
