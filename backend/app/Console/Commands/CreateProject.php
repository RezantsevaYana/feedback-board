<?php

namespace App\Console\Commands;

use App\Services\ProjectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CreateProject extends Command
{
    protected $signature = 'project:create
        {name : Project name shown on the board}
        {--slug= : Board address, /<slug>; derived from the name if omitted}
        {--origin=* : Site allowed to submit from the widget, e.g. https://app.example.com or http://localhost:*}';

    protected $description = 'Create a project: its own board and widget';

    public function handle(ProjectService $projects): int
    {
        $name = $this->argument('name');
        $slug = $this->option('slug') ?: Str::slug($name);
        $origins = $this->option('origin');

        $validator = Validator::make(compact('slug', 'origins'), [
            'slug' => ['required', 'alpha_dash:ascii', 'max:64', 'unique:projects,slug'],
            'origins' => ['required', 'array', 'min:1'],
            'origins.*' => ['string', 'regex:#^https?://[^/\s]+$#'],
        ], [
            'origins.required' => 'Укажите хотя бы один --origin: сайт, где будет стоять виджет.',
            'origins.*.regex' => 'Origin — это схема и домен без пути, например https://app.example.com',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $project = $projects->create($name, $slug, $origins, null);

        $this->info("Проект «{$project->name}» создан. Доска: /{$project->slug}");
        $this->line('Категории: '.implode(', ', $project->categories()->pluck('name')->all()).' — команда меняет их на доске.');
        $this->line('Столбцы: '.implode(' → ', $project->statuses()->pluck('name')->all()).' — свои добавляются в разделе «Столбцы».');
        $this->line('Виджет: <script src="https://<сервис>/widget.js" data-project="'.$project->slug.'" defer></script>');

        return self::SUCCESS;
    }
}
