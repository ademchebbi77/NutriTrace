<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class Ean implements ValidationRule
{
    /**
     * Accept EAN-8 and EAN-13 barcodes with a valid check digit.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^(\d{8}|\d{13})$/', $value) || ! self::hasValidCheckDigit($value)) {
            $fail(__('products.validation.barcode'));
        }
    }

    public static function hasValidCheckDigit(string $code): bool
    {
        return self::checkDigit(substr($code, 0, -1)) === (int) substr($code, -1);
    }

    /**
     * Check digit for the given payload (7 or 12 digits).
     */
    public static function checkDigit(string $payload): int
    {
        $sum = 0;

        // Weights alternate 3, 1, 3... starting from the rightmost payload digit.
        foreach (array_reverse(str_split($payload)) as $index => $digit) {
            $sum += (int) $digit * ($index % 2 === 0 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10;
    }
}
