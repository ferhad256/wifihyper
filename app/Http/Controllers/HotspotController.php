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
            \Log::error('Unauthorized package creation attempt', [
                'tenant_id' => $tenant ? $tenant->id : 'null',
                'hotspot_tenant_id' => $hotspot->tenant_id,
                'request_data' => $request->all()
            ]);
            return back()->with('error', 'Unauthorized action.');
        }

        // Log the incoming request
        \Log::info('Package creation request received', [
            'tenant_id' => $tenant->id,
            'hotspot_id' => $hotspot->id,
            'request_data' => $request->all(),
            'user_agent' => $request->userAgent(),
            'ip' => $request->ip()
        ]);

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
            \Log::warning('Package creation validation failed', [
                'tenant_id' => $tenant->id,
                'hotspot_id' => $hotspot->id,
                'validation_errors' => $validator->errors()->toArray()
            ]);
            return back()->withErrors($validator)->withInput();
        }

        try {
            // Log the attempt
            \Log::info('Attempting to create package', [
                'tenant_id' => $tenant->id,
                'hotspot_id' => $hotspot->id,
                'package_data' => $request->all()
            ]);

            // Check if hotspot exists and belongs to tenant
            if (!$hotspot->exists) {
                \Log::error('Hotspot not found', ['hotspot_id' => $hotspot->id]);
                return back()->with('error', 'Hotspot not found.')->withInput();
            }

            // Validate hotspot ownership
            if ($hotspot->tenant_id !== $tenant->id) {
                \Log::error('Unauthorized package creation attempt', [
                    'tenant_id' => $tenant->id,
                    'hotspot_tenant_id' => $hotspot->tenant_id
                ]);
                return back()->with('error', 'Unauthorized action.')->withInput();
            }

            // Check if package with same name already exists for this hotspot
            $existingPackage = Package::where('hotspot_id', $hotspot->id)
                ->where('name', $request->name)
                ->first();
            
            if ($existingPackage) {
                \Log::warning('Package with same name already exists', [
                    'tenant_id' => $tenant->id,
                    'hotspot_id' => $hotspot->id,
                    'package_name' => $request->name,
                    'existing_package_id' => $existingPackage->id
                ]);
                return back()->with('error', 'A package with this name already exists for this hotspot.')->withInput();
            }

            // Prepare package data
            $packageData = [
                'hotspot_id' => $hotspot->id,
                'name' => $request->name,
                'description' => $request->description ?? '',
                'price' => $request->price,
                'duration_hours' => $request->duration_hours ? (int)$request->duration_hours : null,
                'data_limit_mb' => $request->data_limit_mb ? (int)$request->data_limit_mb : null,
                'sort_order' => $request->sort_order ?? 0,
                'is_active' => $request->has('is_active'),
            ];

            // Ensure sort_order is never null
            if ($packageData['sort_order'] === null) {
                $packageData['sort_order'] = 0;
            }

            \Log::info('Package data prepared', [
                'tenant_id' => $tenant->id,
                'hotspot_id' => $hotspot->id,
                'package_data' => $packageData
            ]);

            // Create the package
            $package = Package::create($packageData);

            \Log::info('Package created successfully', [
                'package_id' => $package->id,
                'hotspot_id' => $hotspot->id,
                'tenant_id' => $tenant->id,
                'package_name' => $package->name,
                'package_price' => $package->price
            ]);

            return back()->with('success', 'Package created successfully!');

        } catch (\Illuminate\Database\QueryException $e) {
            \Log::error('Database error creating package', [
                'error' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'tenant_id' => $tenant->id,
                'hotspot_id' => $hotspot->id,
                'request_data' => $request->all(),
                'previous_error' => $e->getPrevious() ? $e->getPrevious()->getMessage() : null
            ]);

            // Check for specific database errors
            if (str_contains($e->getMessage(), 'foreign key constraint')) {
                return back()->with('error', 'Database constraint error. Please check if the hotspot exists.')->withInput();
            }

            if (str_contains($e->getMessage(), 'duplicate entry')) {
                return back()->with('error', 'A package with this name already exists.')->withInput();
            }

            if (str_contains($e->getMessage(), 'column cannot be null')) {
                return back()->with('error', 'Required fields are missing. Please fill all required fields.')->withInput();
            }

            return back()->with('error', 'Database error occurred while creating package. Please try again.')->withInput();

        } catch (\Exception $e) {
            \Log::error('Unexpected error creating package', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $tenant->id,
                'hotspot_id' => $hotspot->id,
                'request_data' => $request->all()
            ]);

            return back()->with('error', 'An unexpected error occurred while creating package. Please try again.')->withInput();
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
