<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PasswordChangedMail;
use App\Models\User;
use App\Services\LoginLockout;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * «Сменить пароль» in the profile: the current password, then the new one.
 * A forgotten current password goes the reset way (PasswordResetController), the profile only sends the letter.
 */
class PasswordController extends Controller
{
    public function update(Request $request, LoginLockout $lockout): Response
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'different:current_password'],
        ], [
            'current_password.required' => 'Введите текущий пароль.',
            'current_password.current_password' => 'Текущий пароль не подходит.',
            'password.required' => 'Придумайте новый пароль.',
            'password.min' => 'Пароль — хотя бы 8 символов.',
            'password.max' => 'Пароль — не длиннее 72 символов.',
            'password.different' => 'Новый пароль совпадает с текущим.',
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['password' => $request->password, 'remember_token' => Str::random(60)])->save();
        $lockout->clear($user->email);

        // this device stays logged in under a fresh session id
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // if it wasn't them, the letter is how they find out
        rescue(fn () => Mail::send(new PasswordChangedMail($user)));

        return response()->noContent();
    }
}