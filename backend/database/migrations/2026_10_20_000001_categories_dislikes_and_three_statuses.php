<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - post type (bug/feature) → categories the team configures per project;
 * - likes and dislikes instead of a single vote;
 * - statuses reduced to new / done / rejected.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'name']);
        });

        Schema::table('posts', function (Blueprint $table) {
            // deleting a category keeps its posts, they just become uncategorized
            $table->foreignId('category_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->renameColumn('votes_count', 'likes_count');
            $table->unsignedInteger('dislikes_count')->default(0);
        });

        // existing projects get the two old types as categories
        foreach (DB::table('projects')->pluck('id') as $projectId) {
            foreach (['bug' => 'Баг', 'feature' => 'Идея'] as $type => $name) {
                $categoryId = DB::table('categories')->insertGetId([
                    'project_id' => $projectId,
                    'name' => $name,
                    'position' => $type === 'bug' ? 0 : 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                DB::table('posts')->where('project_id', $projectId)->where('type', $type)->update(['category_id' => $categoryId]);
            }
        }

        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('type');
        });

        Schema::table('votes', function (Blueprint $table) {
            // +1 like, -1 dislike
            $table->smallInteger('value')->default(1);
        });

        DB::table('posts')->whereIn('status', ['under_review', 'planned', 'in_progress'])->update(['status' => 'new']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('type', 16)->default('feature');
        });
        DB::table('posts')
            ->whereIn('category_id', DB::table('categories')->where('name', 'Баг')->select('id'))
            ->update(['type' => 'bug']);

        Schema::table('votes', function (Blueprint $table) {
            $table->dropColumn('value');
        });
        Schema::table('posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('dislikes_count');
            $table->renameColumn('likes_count', 'votes_count');
        });
        Schema::dropIfExists('categories');
    }
};
