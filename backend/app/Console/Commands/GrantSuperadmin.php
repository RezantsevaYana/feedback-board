<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Orchid\Support\Facades\Dashboard;

class GrantSuperadmin extends Command
{
    protected $signature = 'superadmin:grant
        {email : Email of an existing account}
        {--revoke : Take the rights away instead}';

    protected $description = 'Give an account the service admin rights («Админка сервиса») or take them away';

    public function handle(): int
    {
        $user = User::where('email', Str::lower($this->argument('email')))->first();
        if ($user === null) {
            $this->error('Аккаунт с таким email не найден. Сначала зарегистрируйтесь или создайте его через admin:create.');

            return self::FAILURE;
        }

        $grant = ! $this->option('revoke');
        // Dashboard::getAllowAllPermission() собирает все права, зарегистрированные в PlatformProvider::registerPermissions().
        // Collection → массив, чтобы 'array' каст на permissions положил его в jsonb как есть.
        $user->permissions = $grant ? Dashboard::getAllowAllPermission()->all() : null;
        $user->save();

        $this->info($grant
            ? "{$user->email} теперь админ сервиса: /admin"
            : "{$user->email} больше не админ сервиса");

        return self::SUCCESS;
    }
}
