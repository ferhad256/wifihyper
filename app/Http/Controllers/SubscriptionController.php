<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\SubscriptionPlan;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    protected $planLimitService;

    public function __construct(PlanLimitService $planLimitService)
    {
        $this->planLimitService = $planLimitService;
    }

    /**
     * Show subscription plans page
     */
    public function index()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $currentPlan = $tenant->getCurrentPlan();
        $planUsage = $this->planLimitService->getPlanUsage($tenant);
        $availablePlans = $this->planLimitService->getAvailablePlans();

        return view('dashboard.subscription.index', compact('tenant', 'currentPlan', 'planUsage', 'availablePlans'));
    }

    /**
     * Show plan usage details
     */
    public function usage()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $planUsage = $this->planLimitService->getPlanUsage($tenant);

        return view('dashboard.subscription.usage', compact('tenant', 'planUsage'));
    }

    /**
     * Show available plans for upgrade
     */
    public function plans()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $currentPlan = $tenant->getCurrentPlan();
        $availablePlans = $this->planLimitService->getAvailablePlans();

        return view('dashboard.subscription.plans', compact('tenant', 'currentPlan', 'availablePlans'));
    }

    /**
     * Upgrade to a new plan
     */
    public function upgrade(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $request->validate([
            'plan_id' => 'required|exists:subscription_plans,id',
        ]);

        $newPlan = SubscriptionPlan::find($request->plan_id);
        $currentPlan = $tenant->getCurrentPlan();

        if ($newPlan->id === $currentPlan->id) {
            return back()->with('error', 'You are already on this plan.');
        }

        try {
            // Update tenant's subscription plan
            $tenant->update([
                'subscription_plan_id' => $newPlan->id,
                'subscription_expires_at' => now()->addDays(30), // Extend subscription
            ]);

            Log::info('Plan upgrade successful', [
                'tenant_id' => $tenant->id,
                'old_plan' => $currentPlan->name,
                'new_plan' => $newPlan->name,
            ]);

            return redirect()->route('subscription.index')
                ->with('success', "Successfully upgraded to {$newPlan->name} plan!");

        } catch (\Exception $e) {
            Log::error('Plan upgrade failed', [
                'tenant_id' => $tenant->id,
                'error' => $e->getMessage(),
            ]);

            return back()->with('error', 'Failed to upgrade plan. Please try again.');
        }
    }

    /**
     * Get plan usage data via AJAX
     */
    public function getUsageData()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $planUsage = $this->planLimitService->getPlanUsage($tenant);

        return response()->json($planUsage);
    }

    /**
     * Check if tenant can perform an action
     */
    public function checkLimit(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $action = $request->get('action');
        $response = [];

        switch ($action) {
            case 'create_hotspot':
                $response = $this->planLimitService->canCreateHotspot($tenant);
                break;
            case 'upload_vouchers':
                $response = $this->planLimitService->canUploadVouchers($tenant);
                break;
            case 'create_transaction':
                $response = $this->planLimitService->canCreateTransaction($tenant);
                break;
            default:
                return response()->json(['error' => 'Invalid action'], 400);
        }

        return response()->json($response);
    }

    /**
     * Calculate transaction fee
     */
    public function calculateTransactionFee(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $amount = $request->amount;
        $feeData = $this->planLimitService->getTransactionFee($tenant, $amount);

        return response()->json($feeData);
    }

    /**
     * Check feature access
     */
    public function checkFeature(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $feature = $request->get('feature');
        
        if (!$feature) {
            return response()->json(['error' => 'Feature parameter required'], 400);
        }

        $hasFeature = $this->planLimitService->hasFeature($tenant, $feature);

        return response()->json([
            'feature' => $feature,
            'has_access' => $hasFeature,
            'plan_name' => $tenant->getCurrentPlan()->name,
        ]);
    }
}
