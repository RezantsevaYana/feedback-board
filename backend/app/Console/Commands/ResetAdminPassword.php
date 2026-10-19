<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ResetAdminPassword extends Command
{
    protected $signature = 'admin:password {email : Admin email}';

    protected $description = 'Generate a new password for a team account and sign it out everywhere';

    public function handle(): int
    {
        $user = User::where('email', Str::lower($this->argument('email')))->first();
        if ($user === null) {
            $this->error('Админ с таким email не найден.');

            return self::FAILURE;
        }

        $password = Str::password(16, symbols: false);
        $user->forceFill(['password' => $password, 'remember_token' => null])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();

        $this->info("Новый пароль для {$user->email}: <comment>{$password}</comment>");
        $this->line('Старые сессии завершены.');

        return self::SUCCESS;
    }
}
