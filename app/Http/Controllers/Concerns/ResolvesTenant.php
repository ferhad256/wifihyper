<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

/**
 * Resolves the tenant making the current request.
 *
 * Replaces the Tenant::find(session('tenant_id')) lookup that used to open
 * every tenant-facing controller action. Routes behind the auth.tenant
 * middleware are guaranteed a tenant, so the null branches that follow each
 * call are now defensive only.
 */
trait ResolvesTenant
{
    protected function tenant(): ?Tenant
    {
        return Auth::guard('tenant')->user();
    }
}
