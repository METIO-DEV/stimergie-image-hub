<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Password;

class UserInvitationMailer
{
    public function __construct(private readonly TransactionalMailer $mailer) {}

    public function send(User $user): bool
    {
        $token = Password::broker()->createToken($user);

        return $this->mailer->send('user_invitation', [
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
