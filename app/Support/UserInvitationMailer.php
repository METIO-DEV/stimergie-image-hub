<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Password;

class UserInvitationMailer
{
    public function __construct(private readonly BrevoTemplateMailer $brevo) {}

    public function send(User $user): bool
    {
        $token = Password::broker()->createToken($user);

        return $this->brevo->send('registration', [
            [
                'email' => $user->email,
                'name' => $user->name,
            ],
        ], [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'login_url' => route('login'),
            'reset_password_url' => route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]),
        ]);
    }
}
