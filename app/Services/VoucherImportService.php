<?php

namespace App\Services;

use App\Models\Package;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Support\Facades\Log;

/**
 * Shared voucher import.
 *
 * The pasted-codes path and the CSV path used to be two near-identical
 * copies of this logic, and they had drifted: the paste path enforced the
 * code format while the CSV path accepted anything in the first column. Both
 * now go through here, so a CSV cannot smuggle in a malformed code.
 */
class VoucherImportService
{
    /**
     * Codes are alphanumeric, 3-20 characters. Kept as it was, since existing
     * stock was uploaded under this rule.
     */
    public const CODE_PATTERN = '/^[a-zA-Z0-9]{3,20}$/';

    /**
     * Split pasted input into codes: one per line, or comma-separated,
     * or any mixture of the two.
     *
     * @return list<string>
     */
    public function parsePastedCodes(string $raw): array
    {
        $raw = str_replace(["\r\n", "\r"], "\n", $raw);

        $codes = [];

        foreach (explode("\n", $raw) as $line) {
            foreach (explode(',', $line) as $part) {
                $part = trim($part);

                if ($part !== '') {
                    $codes[] = $part;
                }
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Read codes from the first column of a CSV, skipping a header row if one
     * is present.
     *
     * @return list<string>
     */
    public function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [];
        }

        $codes = [];
        $first = fgetcsv($handle);

        $isHeader = $first !== false
            && isset($first[0])
            && in_array(strtolower(trim((string) $first[0])), ['voucher', 'code', 'voucher_code'], true);

        if (! $isHeader) {
            rewind($handle);
        }

        while (($row = fgetcsv($handle)) !== false) {
            $code = trim((string) ($row[0] ?? ''));

            if ($code !== '') {
                $codes[] = $code;
            }
        }

        fclose($handle);

        return array_values(array_unique($codes));
    }

    /**
     * Create vouchers for a package.
     *
     * @param  list<string>  $codes
     * @return array{imported:int, skipped:int, invalid:list<string>, errors:list<string>}
     */
    public function import(Tenant $tenant, Package $package, array $codes, ?string $expiresAt = null): array
    {
        $imported = 0;
        $skipped = 0;
        $invalid = [];
        $errors = [];

        foreach ($codes as $code) {
            if (! preg_match(self::CODE_PATTERN, $code)) {
                $invalid[] = $code;
                continue;
            }

            // Codes are unique across the whole table, not per tenant, so this
            // also skips a code another operator already holds.
            if (Voucher::where('code', $code)->exists()) {
                $skipped++;
                continue;
            }

            try {
                Voucher::create([
                    'tenant_id' => $tenant->id,
                    'hotspot_id' => $package->hotspot_id,
                    'package_id' => $package->id,
                    'code' => $code,
                    'expires_at' => $expiresAt ?: null,
                    'status' => 'unused',
                ]);

                $imported++;
            } catch (\Throwable $e) {
                $errors[] = $code;

                Log::error('Voucher import failed for a code', [
                    'tenant_id' => $tenant->id,
                    'package_id' => $package->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return compact('imported', 'skipped', 'invalid', 'errors');
    }

    /**
     * A human summary of an import result.
     *
     * @param  array{imported:int, skipped:int, invalid:list<string>, errors:list<string>}  $result
     */
    public function summarise(array $result): string
    {
        $parts = ["Imported {$result['imported']} vouchers."];

        if ($result['skipped'] > 0) {
            $parts[] = "Skipped {$result['skipped']} already in use.";
        }

        if ($result['invalid'] !== []) {
            $shown = implode(', ', array_slice($result['invalid'], 0, 5));
            $more = count($result['invalid']) - 5;
            $parts[] = 'Rejected ' . count($result['invalid']) . " with an invalid format: {$shown}"
                . ($more > 0 ? " and {$more} more." : '.');
        }

        if ($result['errors'] !== []) {
            $parts[] = count($result['errors']) . ' could not be saved.';
        }

        return implode(' ', $parts);
    }
}
