<?php

namespace App\Domain\Pricing\Support;

final class Rounding
{
    /**
     * Upward “25-step” rounding per spec:
     * - 1–24 => 25
     * - 26–49 => 50
     * - 51–74 => 75
     * - >75 => next 100
     *
     * Works for any non-negative number. Returns integer-like float.
     */
    public static function step25Up(float $amount): float
    {
        if ($amount <= 0) return 0.0;

        $int = (int) floor($amount);
        $hundreds = (int) floor($int / 100);
        $remainder = $int % 100;

        if ($remainder === 0) {
            // Exact hundred stays as-is (already “round up”)
            return (float) $int;
        }

        if ($remainder <= 25)  return (float) ($hundreds * 100 + 25);
        if ($remainder <= 50)  return (float) ($hundreds * 100 + 50);
        if ($remainder <= 75)  return (float) ($hundreds * 100 + 75);
        // > 75 => bump to next hundred
        return (float) (($hundreds + 1) * 100);
    }
}
