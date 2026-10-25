<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use App\Tenancy\TenantScope;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Nom du champ utilisé pour se connecter.
     */
    public const IDENTIFIER_FIELD = 'login';

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            self::IDENTIFIER_FIELD => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $user = $this->resolveUser();

        // Always use the guard's timebox, including for unknown identifiers.
        if (! Auth::attempt([
            'id' => $user?->id,
            'password' => $this->string('password'),
        ], $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                self::IDENTIFIER_FIELD => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Retrouve le compte à partir de l'identifiant saisi.
     *
     * L'e-mail reste accepté en secours, ce qui évite de bloquer les
     * utilisateurs dont l'identifiant n'a pas encore été communiqué.
     */
    protected function resolveUser(): ?User
    {
        $identifier = trim((string) $this->string(self::IDENTIFIER_FIELD));

        return User::withoutGlobalScope(TenantScope::class)
            ->where('username', $identifier)
            ->orWhere('email', $identifier)
            ->first();
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            self::IDENTIFIER_FIELD => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return hash('sha256', Str::lower(trim((string) $this->string(self::IDENTIFIER_FIELD))).'|'.$this->ip());
    }
}
