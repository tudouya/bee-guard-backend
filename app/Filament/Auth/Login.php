<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Schemas\Components\Component;
use Filament\Forms\Components\TextInput;

class Login extends BaseLogin
{
    protected function getEmailFormComponent(): Component
    {
        // Override: allow email OR username, so do not apply ->email() validation.
        return TextInput::make('email')
            ->label('邮箱或用户名')
            ->required()
            ->autocomplete()
            ->autofocus()
            ->extraInputAttributes(['tabindex' => 1]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        $login = (string) ($data['email'] ?? '');
        $password = (string) ($data['password'] ?? '');

        $isEmail = filter_var($login, FILTER_VALIDATE_EMAIL) !== false;

        return [
            $isEmail ? 'email' : 'username' => $login,
            'password' => $password,
        ];
    }
}

