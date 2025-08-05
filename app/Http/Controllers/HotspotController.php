<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\Hotspot;
use App\Models\Package;
use App\Services\PlanLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HotspotController extends Controller
{
    protected $planLimitService;

    public function __construct(PlanLimitService $planLimitService)
    {
        $this->planLimitService = $planLimitService;
    }

    /**
     * Display a listing of hotspots
     */
    public function index()
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        $hotspots = $tenant->hotspots()->with('packages')->get();

        return view('dashboard.hotspots', compact('tenant', 'hotspots'));
    }

    /**
     * Store a newly created hotspot
     */
    public function store(Request $request)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant) {
            return redirect()->route('login');
        }

        // Check plan limits
        $limitCheck = $this->planLimitService->canCreateHotspot($tenant);
        if (!$limitCheck['can_create']) {
            return back()->with('error', "You've reached your plan limit of {$limitCheck['max_allowed']} hotspots. Please upgrade your plan to create more hotspots.")->withInput();
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'ssid' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            Hotspot::create([
                'tenant_id' => $tenant->id,
                'name' => $request->name,
                'ssid' => $request->ssid,
                'location' => $request->location,
                'description' => $request->description,
                'is_active' => true,
            ]);

            return back()->with('success', 'Hotspot created successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create hotspot.')->withInput();
        }
    }

    /**
     * Display the specified hotspot
     */
    public function show(Hotspot $hotspot)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant || $hotspot->tenant_id !== $tenant->id) {
            return back()->with('error', 'Unauthorized action.');
        }

        $hotspot->load('packages');

        return view('dashboard.hotspots.show', compact('tenant', 'hotspot'));
    }

    /**
     * Show the form for editing the specified hotspot
     */
    public function edit(Hotspot $hotspot)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant || $hotspot->tenant_id !== $tenant->id) {
            return back()->with('error', 'Unauthorized action.');
        }

        return view('dashboard.hotspots.edit', compact('tenant', 'hotspot'));
    }

    /**
     * Update the specified hotspot
     */
    public function update(Request $request, Hotspot $hotspot)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant || $hotspot->tenant_id !== $tenant->id) {
            return back()->with('error', 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'ssid' => 'required|string|max:255',
            'location' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $hotspot->update([
                'name' => $request->name,
                'ssid' => $request->ssid,
                'location' => $request->location,
                'description' => $request->description,
                'is_active' => $request->has('is_active'),
            ]);

            return back()->with('success', 'Hotspot updated successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update hotspot.')->withInput();
        }
    }

    /**
     * Remove the specified hotspot
     */
    public function destroy(Hotspot $hotspot)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant || $hotspot->tenant_id !== $tenant->id) {
            return back()->with('error', 'Unauthorized action.');
        }

        try {
            $hotspot->delete();
            return back()->with('success', 'Hotspot deleted successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete hotspot.');
        }
    }

    /**
     * Show packages for a hotspot
     */
    public function packages(Hotspot $hotspot)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant || $hotspot->tenant_id !== $tenant->id) {
            return back()->with('error', 'Unauthorized action.');
        }

        $packages = $hotspot->packages()->orderBy('sort_order')->get();

        return view('dashboard.hotspots.packages', compact('tenant', 'hotspot', 'packages'));
    }

    /**
     * Store a package for a hotspot
     */
    public function storePackage(Request $request, Hotspot $hotspot)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant || $hotspot->tenant_id !== $tenant->id) {
            return back()->with('error', 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_hours' => 'nullable|integer|min:1',
            'data_limit_mb' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            Package::create([
                'hotspot_id' => $hotspot->id,
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'duration_hours' => $request->duration_hours,
                'data_limit_mb' => $request->data_limit_mb,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->has('is_active'),
            ]);

            return back()->with('success', 'Package created successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to create package.')->withInput();
        }
    }

    /**
     * Show the form for editing a package
     */
    public function editPackage(Hotspot $hotspot, Package $package)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant || $hotspot->tenant_id !== $tenant->id || $package->hotspot_id !== $hotspot->id) {
            return response()->json(['error' => 'Unauthorized action.'], 403);
        }

        return response()->json($package);
    }

    /**
     * Update a package
     */
    public function updatePackage(Request $request, Hotspot $hotspot, Package $package)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant || $hotspot->tenant_id !== $tenant->id || $package->hotspot_id !== $hotspot->id) {
            return back()->with('error', 'Unauthorized action.');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_hours' => 'nullable|integer|min:1',
            'data_limit_mb' => 'nullable|integer|min:1',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $package->update([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'duration_hours' => $request->duration_hours,
                'data_limit_mb' => $request->data_limit_mb,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->has('is_active'),
            ]);

            return back()->with('success', 'Package updated successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to update package.')->withInput();
        }
    }

    /**
     * Delete a package
     */
    public function deletePackage(Hotspot $hotspot, Package $package)
    {
        $tenant = Tenant::find(session('tenant_id'));
        
        if (!$tenant || $hotspot->tenant_id !== $tenant->id || $package->hotspot_id !== $hotspot->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized action.'], 403);
        }

        try {
            $package->delete();
            return response()->json(['success' => true, 'message' => 'Package deleted successfully!']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete package.'], 500);
        }
    }
}
