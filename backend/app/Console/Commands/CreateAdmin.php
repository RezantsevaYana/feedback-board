<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create
        {email : Admin email, used to log in}
        {--project=* : Slug of a project this admin manages}
        {--name= : Display name; the part of the email before @ if omitted}';

    protected $description = 'Create a team account and print its generated password once';

    public function handle(): int
    {
        $email = Str::lower($this->argument('email'));
        $slugs = $this->option('project');

        $validator = Validator::make(compact('email', 'slugs'), [
            'email' => ['required', 'email', 'unique:users,email'],
            'slugs' => ['required', 'array', 'min:1'],
            'slugs.*' => ['exists:projects,slug'],
        ], [
            'email.unique' => 'Админ с таким email уже есть. Добавить проект или сменить пароль — admin:password.',
            'slugs.required' => 'Укажите хотя бы один --project.',
            'slugs.*.exists' => 'Проект :input не найден.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = Str::password(16, symbols: false);
        $user = User::create([
            'name' => $this->option('name') ?: Str::before($email, '@'),
            'email' => $email,
            'password' => $password,
        ]);
        // the address comes from whoever runs the command, not from a stranger: nothing to confirm
        $user->markEmailAsVerified();
        $user->projects()->sync(Project::whereIn('slug', $slugs)->pluck('id'));

        $this->info("Админ {$user->email} создан, проекты: ".implode(', ', $slugs));
        $this->line("Пароль: <comment>{$password}</comment>");
        $this->line('Он показывается один раз — передайте его админу лично.');

        return self::SUCCESS;
    }
}
