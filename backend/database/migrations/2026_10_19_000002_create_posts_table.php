<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('title', 120);
            $table->text('description');
            $table->string('author_name', 80)->nullable();
            $table->string('status', 20)->default('new');
            $table->string('priority', 16)->nullable();
            $table->text('team_comment')->nullable();
            $table->boolean('is_hidden')->default(false);
            // Denormalized for sorting by popularity
            $table->unsignedInteger('votes_count')->default(0);
            $table->timestamp('status_changed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status']);
        });

        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 64);
            $table->unsignedInteger('size');
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->uuid('voter_token');
            // sha256(ip + app key): for rate limits and investigating vote stuffing, never the raw IP
            $table->string('ip_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['post_id', 'voter_token']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('votes');
        Schema::dropIfExists('attachments');
        Schema::dropIfExists('posts');
    }
};
