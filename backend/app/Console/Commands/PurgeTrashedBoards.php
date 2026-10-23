<?php

namespace App\Console\Commands;

use App\Models\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PurgeTrashedBoards extends Command
{
    protected $signature = 'boards:purge-trashed
        {--dry-run : Only list what would be removed}';

    protected $description = 'Delete boards that sat in the trash longer than feedback.trash_retention_days';

    /**
     * Runs daily (routes/console.php). Posts, categories, statuses and votes go with the
     * board via cascading foreign keys; attachment files live on disk and are removed here.
     */
    public function handle(): int
    {
        $days = (int) config('feedback.trash_retention_days');
        $boards = Project::onlyTrashed()->where('deleted_at', '<', now()->subDays($days))->get();

        foreach ($boards as $board) {
            if ($this->option('dry-run')) {
                $this->line("/{$board->slug} — «{$board->name}», в корзине с {$board->deleted_at}");

                continue;
            }

            foreach ($board->posts()->pluck('id') as $postId) {
                Storage::disk('local')->deleteDirectory("attachments/{$postId}");
            }
            $board->forceDelete();
        }

        $this->info(($this->option('dry-run') ? 'К удалению: ' : 'Удалено досок: ').$boards->count());

        return self::SUCCESS;
    }
}