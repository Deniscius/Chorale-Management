<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class PersonName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'’\x{2010}-\x{2015}-]*$/u", trim($value))) {
            $fail('Le :attribute doit contenir un nom valide, composé de lettres, espaces, apostrophes ou tirets.');
        }
    }
}
