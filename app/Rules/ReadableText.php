<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ReadableText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)
            || ! preg_match('/[\\p{L}]/u', $value)
            || preg_match('/[\\x00-\\x1F\\x7F<>]/u', $value)) {
            $fail('Le :attribute doit contenir un texte lisible, sans balise ni caractère de contrôle.');
        }
    }
}
