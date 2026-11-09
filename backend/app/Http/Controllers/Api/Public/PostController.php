<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Resources\PostResource;
use App\Models\Post;
use App\Models\Project;
use App\Models\Voter;
use App\Rules\StatusIdList;
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
            'category' => ['nullable', 'regex:/^(\d+|none)(,(\d+|none))*$/'],
            'status_id' => ['nullable', new StatusIdList($project)],
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['top', 'new'])],
            'new_first' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filtered = $project->posts()
            ->listedOnBoard()
            ->inCategory($filters['category'] ?? null)
            ->inStatuses($filters['status_id'] ?? null)
            ->search($filters['q'] ?? null);
        // the list view: «Новое» on top, and how many of them there are, so the page can draw the line after them
        $newStatusId = ($filters['new_first'] ?? false) ? $project->defaultStatus()?->id : null;

        $posts = (clone $filtered)
            ->with(['category', 'status'])
            ->withCount('attachments')
            ->newFirst($newStatusId)
            ->sorted($filters['sort'] ?? 'top')
            ->paginate($filters['per_page'] ?? 20);

        VoterToken::markVoted($request, $posts->getCollection());

        return PostResource::collection($posts)
            ->additional($newStatusId !== null ? ['new_count' => (clone $filtered)->where('status_id', $newStatusId)->count()] : []);
    }

    public function store(StorePostRequest $request, Project $project): JsonResponse
    {
        // На доске токен в X-Voter-Token приходит всегда; из виджета (cross-origin) — нет,
        // чтобы не форсить CORS-preflight. У «безтокенного» поста author_token останется null —
        // в лидерборде такой автор не светится.
        $authorToken = VoterToken::from($request);
        // Залогиненный пишет под именем аккаунта: author_name от клиента игнорируем, чтобы нельзя было
        // «подписаться» чужим именем. Аноним (виджет cross-origin) по-прежнему шлёт author_name полем.
        $user = $request->user('sanctum');
        // Читатель only reads on a closed board; on an open one they're a guest like anyone with the link.
        // Guests and outsiders follow the board's visibility
        abort_if($project->isPrivate() && $user?->roleOn($project)?->canSubmit() === false, 403, 'Читатель на этой доске не предлагает — только читает.');
        // open board + owner hid the form from guests (test.md п. 40). Members get through regardless of this flag.
        if (! $project->isPrivate() && $user === null && ! $project->guests_can_post) {
            return response()->json([
                'message' => 'Войдите в аккаунт, чтобы предложить идею.',
                'reason' => 'guest_post_disabled',
            ], 403);
        }
        $authorName = $user?->name ?? $request->validated('author_name');

        $post = DB::transaction(function () use ($request, $project, $authorToken, $authorName, $user) {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            $attributes = $request->safe()->only(['category_id', 'title', 'description']);
            $attributes['author_name'] = $authorName;
            $attributes['author_token'] = $authorToken;
            $attributes['author_user_id'] = $user?->id;
            $post = $project->posts()->create($attributes);

            // Имя для анонимного лидерборда: держим актуальным последнее имя, которым подписался автор.
            // Пустое имя не трогает справочник — автор остаётся в анонимусах. Залогиненные в этот
            // справочник не попадают: их имя всегда есть в users.name и подтягивается напрямую.
            if ($user === null && $authorToken !== null && $authorName !== null && $authorName !== '') {
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
