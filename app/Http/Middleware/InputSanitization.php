<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InputSanitization
{
    /**
     * Paths whose request bodies must reach the application untouched.
     *
     * Livewire posts a JSON payload containing a `snapshot` string and a
     * checksum over it. Request::replace() writes back through
     * getInputSource(), which IS the JSON bag on a JSON request, so
     * htmlspecialchars() would rewrite the quotes inside that snapshot and
     * fail the checksum on every round trip. The Filament panel paths are
     * listed for the same reason.
     *
     * Matched as paths rather than route names because global middleware runs
     * before the route is resolved.
     *
     * @var list<string>
     */
    protected array $except = [
        'livewire/*',
        'dashboard/*',
        'admin/*',
    ];

    /**
     * Input keys whose values must reach the application byte-for-byte.
     *
     * HTML-encoding a password protects nothing - it is hashed, never
     * rendered - but it does change it. A password containing & " ' < or >
     * was stored as the hash of its escaped form, while the panel login
     * posts JSON and is exempt above, so the password the operator actually
     * typed could never match that hash again: an account locked out at the
     * moment it was created, with no error to show for it.
     *
     * Trimming is skipped for the same keys, mirroring Laravel's own
     * TrimStrings exception list - leading and trailing spaces are legal in
     * a password and silently removing them has the same effect.
     *
     * @var list<string>
     */
    protected array $exceptKeys = [
        'password',
        'password_confirmation',
        'current_password',
        'new_password',
        'new_password_confirmation',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is(...$this->except)) {
            return $next($request);
        }

        // Sanitize all input data
        $input = $request->all();
        $sanitized = $this->sanitizeArray($input);

        // Replace request input with sanitized data
        $request->replace($sanitized);

        return $next($request);
    }

    /**
     * Recursively sanitize array data
     */
    protected function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
            } elseif (in_array($key, $this->exceptKeys, true)) {
                $sanitized[$key] = $value;
            } else {
                $sanitized[$key] = $this->sanitizeValue($value);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize an individual value.
     *
     * Non-strings are returned as they arrived. Casting them to string here
     * turned null into "", true into "1" and integers into numeric strings,
     * which silently changed the shape of typed payloads (JSON webhooks in
     * particular) and defeated any "nullable" validation downstream.
     */
    protected function sanitizeValue(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        // Remove null bytes
        $value = str_replace("\0", '', $value);

        // Remove control characters
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        // Trim whitespace
        $value = trim($value);

        // HTML encode to prevent XSS
        $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        return $value;
    }
}
