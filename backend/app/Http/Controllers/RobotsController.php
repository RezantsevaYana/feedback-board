<?php

namespace App\Http\Controllers;

use App\Enums\BoardVisibility;
use App\Models\Project;
use Illuminate\Http\Response;

/**
 * Динамический /robots.txt: пускает поисковики по корню, но запрещает обходить каждую открытую доску,
 * у которой владелец выключил «Показывать в поисковиках» (test.md п. 19). Закрытые доски и так отдают
 * 403 гостю — их слуги здесь не раскрываем, чтобы не светить внутренние адреса.
 */
class RobotsController extends Controller
{
    public function show(): Response
    {
        /** @var list<string> $hiddenSlugs */
        $hiddenSlugs = Project::query()
            ->where('visibility', BoardVisibility::Public->value)
            ->where('search_indexable', false)
            ->orderBy('slug')
            ->pluck('slug')
            ->all();

        $lines = ['User-agent: *', 'Allow: /'];
        foreach ($hiddenSlugs as $slug) {
            $lines[] = 'Disallow: /'.$slug;
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
