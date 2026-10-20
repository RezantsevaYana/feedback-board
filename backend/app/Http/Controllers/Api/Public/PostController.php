<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Project;
use App\Models\Voter;
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
            'status_id' => ['nullable', 'integer', Rule::exists('statuses', 'id')->where('project_id', $project->id)],
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['top', 'new'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $posts = $project->posts()
            ->listedOnBoard()
            ->inCategory($filters['category'] ?? null)
            ->when($filters['status_id'] ?? null, fn ($q, $statusId) => $q->where('status_id', $statusId))
            ->search($filters['q'] ?? null)
            ->with(['category', 'status'])
            ->withCount('attachments')
            ->sorted($filters['sort'] ?? 'top')
            ->paginate($filters['per_page'] ?? 20);

        VoterToken::markVoted($request, $posts->getCollection());

        return PostResource::collection($posts);
    }

    public function store(StorePostRequest $request, Project $project): JsonResponse
    {
        // На доске токен в X-Voter-Token приходит всегда; из виджета (cross-origin) — нет,
        // чтобы не форсить CORS-preflight. У «безтокенного» поста author_token останется null —
        // в лидерборде такой автор не светится.
        $authorToken = VoterToken::from($request);
        $authorName = $request->validated('author_name');

        $post = DB::transaction(function () use ($request, $project, $authorToken, $authorName) {
            $attributes = $request->safe()->only(['category_id', 'title', 'description', 'author_name']);
            $attributes['author_token'] = $authorToken;
            $post = $project->posts()->create($attributes);

            // Имя для лидерборда: держим актуальным последнее имя, которым подписался автор.
            // Пустое имя не трогает справочник — автор остаётся в анонимусах.
            if ($authorToken !== null && $authorName !== null && $authorName !== '') {
                Voter::updateOrCreate(['token' => $authorToken], ['display_name' => $authorName]);
            }

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

        return PostResource::make($post->load('attachments', 'category', 'status'))->response()->setStatusCode(201);
    }

    /**
     * Post card by direct link; hidden posts do not open.
     */
    public function show(Request $request, Project $project, Post $post): PostResource
    {
        abort_if($post->is_hidden, 404);

        VoterToken::markVoted($request, collect([$post]));

        return PostResource::make($post->load('attachments', 'category', 'status'));
    }
}
