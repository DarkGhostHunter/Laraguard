<?php

namespace DarkGhostHunter\Laraguard\Eloquent;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

use function now;

trait HandlesRecoveryCodes
{
    /**
     * Returns if there are Recovery Codes available.
     *
     * @return bool
     */
    public function containsUnusedRecoveryCodes(): bool
    {
        return (bool) $this->recovery_codes?->contains('used_at', '===', null);
    }

    /**
     * Sets a Recovery Code as used.
     *
     * @param  string  $code
     * @return bool
     */
    public function setRecoveryCodeAsUsed(string $code): bool
    {
        // Find the index of the exact match: same code, and not used.
        $index = $this->recovery_codes?->search([
            'code' => $code, 'used_at' => null
        ]);

        // When it's found, an integer is always returned (null or false if not).
        if (is_int($index)) {
            $this->recovery_codes->put($index, [
                'code'    => $code,
                'used_at' => now(),
            ]);

            return true;
        }

        return false;
    }

    /**
     * Generates a new batch of Recovery Codes.
     *
     * @param  int  $amount
     * @param  int  $length
     * @return \Illuminate\Support\Collection<array<string, int|null>
     */
    public static function generateRecoveryCodes(int $amount, int $length): Collection
    {
        return Collection::times($amount, static function () use ($length): array {
            return [
                'code'    => strtoupper(Str::random($length)),
                'used_at' => null,
            ];
        });
    }
}
