<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('default_status_id')->nullable()->constrained('statuses')->nullOnDelete();
        });

        DB::table('projects')->select('id')->orderBy('id')->chunkById(200, function ($projects) {
            foreach ($projects as $project) {
                $statuses = DB::table('statuses')->where('project_id', $project->id);
                $id = (clone $statuses)->where('slug', 'new')->value('id')
                    ?? $statuses->orderBy('position')->orderBy('id')->value('id');
                DB::table('projects')->where('id', $project->id)->update(['default_status_id' => $id]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('default_status_id');
        });
    }
};
