<?php

namespace App\Http\Controllers\Api\Public;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Project;
use App\Support\VoterToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    /**
     * Board list: everything except hidden posts.
     */
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'category' => ['nullable', 'regex:/^(\d+|none)$/'],
            'status' => ['nullable', Rule::enum(PostStatus::class)],
            'sort' => ['nullable', Rule::in(['top', 'new'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $posts = $project->posts()
            ->listedOnBoard()
            ->inCategory($filters['category'] ?? null)
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->with('category')
            ->withCount('attachments')
            ->sorted($filters['sort'] ?? 'top')
            ->paginate($filters['per_page'] ?? 20);

        VoterToken::markVoted($request, $posts->getCollection());

        return PostResource::collection($posts);
    }

    public function store(StorePostRequest $request, Project $project): JsonResponse
    {
        $post = DB::transaction(function () use ($request, $project) {
            $post = $project->posts()->create($request->safe()->only(['category_id', 'title', 'description', 'author_name']));

            foreach ($request->file('files', []) as $file) {
                $post->attachments()->create([
                    // random file name on disk; the original name is kept only for display
                    'path' => $file->store("attachments/{$post->id}", 'local'),
                    'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            return $post;
        });

        return PostResource::make($post->load('attachments', 'category'))->response()->setStatusCode(201);
    }

    /**
     * Post card by direct link; hidden posts do not open.
     */
    public function show(Request $request, Project $project, Post $post): PostResource
    {
        abort_if($post->is_hidden, 404);

        VoterToken::markVoted($request, collect([$post]));

        return PostResource::make($post->load('attachments', 'category'));
    }
}
