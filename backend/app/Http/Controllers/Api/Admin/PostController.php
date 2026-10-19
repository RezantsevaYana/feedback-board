<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PostPriority;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\AdminPostResource;
use App\Models\Post;
use App\Models\Project;
use App\Support\VoterToken;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Team mode of the board. Every action checks that the admin manages the post's project.
 */
class PostController extends Controller
{
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        Gate::authorize('manage-project', $project);

        $filters = $request->validate([
            'category' => ['nullable', 'regex:/^(\d+|none)$/'],
            'status' => ['nullable', Rule::enum(PostStatus::class)],
            'priority' => ['nullable', Rule::in([...array_column(PostPriority::cases(), 'value'), 'none'])],
            'hidden' => ['nullable', Rule::in(['include', 'exclude'])],
            'sort' => ['nullable', Rule::in(['top', 'new', 'priority'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $posts = $project->posts()
            ->when(($filters['hidden'] ?? 'include') === 'exclude', fn ($q) => $q->where('is_hidden', false))
            ->inCategory($filters['category'] ?? null)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($q, $priority) => $priority === 'none'
                ? $q->whereNull('priority')
                : $q->where('priority', $priority))
            ->with('category')
            ->withCount('attachments')
            ->sorted($filters['sort'] ?? 'new')
            ->paginate($filters['per_page'] ?? 20);

        VoterToken::markVoted($request, $posts->getCollection());

        return AdminPostResource::collection($posts);
    }

    public function show(Request $request, Post $post): AdminPostResource
    {
        Gate::authorize('manage-project', $post->project);

        VoterToken::markVoted($request, collect([$post]));

        return AdminPostResource::make($post->load('attachments', 'category'));
    }

    public function update(UpdatePostRequest $request, Post $post): AdminPostResource
    {
        Gate::authorize('manage-project', $post->project);

        $post->fill($request->validated())->save();

        return AdminPostResource::make($post->load('attachments', 'category'));
    }

    public function destroy(Post $post): Response
    {
        Gate::authorize('manage-project', $post->project);

        Storage::disk('local')->deleteDirectory("attachments/{$post->id}");
        $post->delete();

        return response()->noContent();
    }
}
