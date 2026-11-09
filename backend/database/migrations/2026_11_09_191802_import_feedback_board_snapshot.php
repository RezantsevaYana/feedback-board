<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Post;
use App\Models\Project;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $board = Project::where('slug', 'feedback-board')->firstOrFail();
        if ($board->posts()->exists()) {
            return;
        }

        $snapshot = json_decode(file_get_contents(database_path('snapshots/feedback-board.json')), true, 512, JSON_THROW_ON_ERROR);
        $categories = [];
        foreach ($snapshot['categories'] as $category) {
            $model = $board->categories()->updateOrCreate(['position' => $category['position']], $category);
            $categories[$category['name']] = $model->id;
        }
        $statuses = [];
        foreach ($snapshot['statuses'] as $status) {
            $model = $board->statuses()->updateOrCreate(['slug' => $status['slug']], $status);
            $statuses[$status['slug']] = $model->id;
        }
        $board->update([
            'name' => $snapshot['name'],
            'default_status_id' => $statuses[$snapshot['default_status']],
        ]);

        foreach ($snapshot['posts'] as $attributes) {
            $category = $attributes['category'];
            $status = $attributes['status'];
            unset($attributes['category'], $attributes['status']);
            $post = new Post;
            $post->forceFill([
                ...$attributes,
                'project_id' => $board->id,
                'category_id' => $category === null ? null : $categories[$category],
                'status_id' => $statuses[$status],
                'author_user_id' => $board->owner_id,
            ]);
            $post->saveQuietly();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Keep the imported backlog and user edits when rolling back application code.
    }
};
