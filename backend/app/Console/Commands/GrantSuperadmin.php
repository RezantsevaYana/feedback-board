<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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
        $user->forceFill(['is_superadmin' => $grant])->save();

        $this->info($grant
            ? "{$user->email} теперь админ сервиса: /app/platform"
            : "{$user->email} больше не админ сервиса");

        return self::SUCCESS;
    }
}