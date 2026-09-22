<?php

namespace App\Filament\Tenant\Pages\Auth;

use App\Models\Tenant;
use App\Services\EmailVerificationService;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Validation\ValidationException;

/**
 * The tenant login, with one addition.
 *
 * Filament already gets the important part right: it verifies the password
 * before asking canAccessPanel(), and pads both failures with a Timebox, so
 * an account that exists but cannot sign in is indistinguishable from one
 * that does not.
 *
 * What it does not do is help a real operator whose email is simply not
 * verified yet - they would just be told their credentials do not match.
 * This sends them to the verification screen instead, but only once the
 * password has been accepted, so nothing is revealed to someone guessing.
 */
class Login extends BaseLogin
{
    private ?Tenant $unverifiedTenant = null;

    public function authenticate(): ?LoginResponse
    {
        try {
            return parent::authenticate();
        } catch (ValidationException $e) {
            if (! $this->unverifiedTenant) {
                throw $e;
            }

            $tenant = $this->unverifiedTenant;
            $this->unverifiedTenant = null;

            $verification = app(EmailVerificationService::class);

            if ($verification->canRequestVerification($tenant)) {
                $verification->sendVerificationCode($tenant);
            }

            session()->flash('error', 'Please verify your email address before signing in. A new code has been sent.');

            $this->redirect(route('verification.show', ['email' => $tenant->email]));

            return null;
        }
    }

    /**
     * Called only after the password has been verified, so anything recorded
     * here is already known to whoever typed it.
     */
    protected function isUserAllowedToAccessPanel(Authenticatable $user): bool
    {
        if ($user instanceof Tenant && ! $user->hasVerifiedEmail()) {
            $this->unverifiedTenant = $user;
        }

        return parent::isUserAllowedToAccessPanel($user);
    }
}
