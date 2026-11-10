<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('username'))) {
            $this->merge(['username' => strtolower(trim($this->input('username')))]);
        }
    }

    public function rules(): array
    {
        return ['username' => ['required', 'string', 'max:40'], 'password' => ['required', 'string', 'max:128']];
    }

    public function authenticate(): void
    {
        $key = 'login:'.hash('sha256', $this->string('username').'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['username' => 'Muitas tentativas. Aguarde '.RateLimiter::availableIn($key).' segundos.']);
        }

        if (! Auth::attempt([...$this->only('username', 'password'), fn ($query) => $query->whereNotNull('box_id')])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['username' => 'Username ou senha inválidos.']);
        }

        RateLimiter::clear($key);
    }
}
