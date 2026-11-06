<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PostPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\AdminPostResource;
use App\Models\Post;
use App\Models\Project;
use App\Rules\StatusIdList;
use App\Support\VoterPreview;
use App\Support\VoterToken;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Team mode of the board. Every action needs board.triage (owner / admin / manager).
 */
class PostController extends Controller
{
    public function index(Request $request, Project $project): AnonymousResourceCollection
    {
        Gate::authorize('board.triage', $project);

        $priorities = implode('|', [...array_column(PostPriority::cases(), 'value'), 'none']);
        $filters = $request->validate([
            'category' => ['nullable', 'regex:/^(\d+|none)(,(\d+|none))*$/'],
            'status_id' => ['nullable', new StatusIdList($project)],
            // one priority or several comma-separated (priority=high,critical,none)
            'priority' => ['nullable', "regex:/^({$priorities})(,({$priorities}))*$/"],
            'hidden' => ['nullable', Rule::in(['include', 'exclude'])],
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['top', 'new', 'priority'])],
            'new_first' => ['nullable', 'boolean'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $filtered = $project->posts()
            ->when(($filters['hidden'] ?? 'include') === 'exclude', fn ($q) => $q->where('is_hidden', false))
            ->inCategory($filters['category'] ?? null)
            ->inStatuses($filters['status_id'] ?? null)
            ->withPriorities($filters['priority'] ?? null)
            ->search($filters['q'] ?? null);
        // the list view: «Новое» on top, and how many of them there are, so the page can draw the line after them
        $newStatusId = ($filters['new_first'] ?? false) ? $project->defaultStatus()?->id : null;

        $posts = (clone $filtered)
            ->with(['category', 'status'])
            ->withCount('attachments')
            ->newFirst($newStatusId)
            ->sorted($filters['sort'] ?? 'new')
            ->paginate($filters['per_page'] ?? 20);

        VoterToken::markVoted($request, $posts->getCollection());
        VoterPreview::attach($request, $posts->getCollection());

        return AdminPostResource::collection($posts)
            ->additional($newStatusId !== null ? ['new_count' => (clone $filtered)->where('status_id', $newStatusId)->count()] : []);
    }

    public function show(Request $request, Post $post): AdminPostResource
    {
        Gate::authorize('board.triage', $post->project);

        VoterToken::markVoted($request, collect([$post]));
        VoterPreview::attach($request, collect([$post]));

        return AdminPostResource::make($post->load('attachments', 'category', 'status'));
    }

    /**
     * Who voted for this post (team-only view). Signed-in voters appear by their account name;
     * anonymous widget voters appear by whatever they typed into «Ваше имя» last — if they left
     * it blank the row surfaces as «Аноним» so the list is countable at a glance. Dislikes mix
     * with likes — the client splits them by `kind`.
     */
    public function voters(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('board.triage', $post->project);

        $rows = $post->votes()
            ->leftJoin('users', 'users.id', '=', 'votes.user_id')
            ->leftJoin('voters', 'voters.token', '=', 'votes.voter_token')
            ->orderBy('votes.created_at', 'desc')
            ->get([
                'votes.id',
                'votes.value',
                'votes.created_at',
                'votes.user_id',
                'users.name as user_name',
                'voters.display_name as voter_name',
            ]);

        return response()->json([
            'data' => $rows->map(fn ($row) => [
                'name' => $row->user_name ?? $row->voter_name ?? 'Аноним',
                'kind' => (int) $row->value === 1 ? 'like' : 'dislike',
                'account' => $row->user_id !== null,
                'created_at' => $row->created_at,
            ])->values(),
        ]);
    }

    public function update(UpdatePostRequest $request, Post $post): AdminPostResource
    {
        Gate::authorize('board.triage', $post->project);

        $post->fill($request->validated())->save();

        return AdminPostResource::make($post->load('attachments', 'category', 'status'));
    }

    public function destroy(Post $post): Response
    {
        Gate::authorize('board.triage', $post->project);

        Storage::disk('local')->deleteDirectory("attachments/{$post->id}");
        $post->delete();

        return response()->noContent();
    }

    /** Пачкой перенести карточки в один столбец; для системных done/rejected admin пишет общий ответ. */
    public function bulkMove(Request $request): AnonymousResourceCollection
    {
        [$posts, $project] = $this->loadBulk($request);

        $data = $request->validate([
            'status_id' => ['required', 'integer', Rule::exists('statuses', 'id')->where('project_id', $project->id)],
            'team_comment' => ['nullable', 'string', 'max:2000'],
        ], [
            'status_id.exists' => 'Такого столбца нет в этом проекте.',
            'team_comment.max' => 'Ответ команды — не длиннее 2000 символов.',
        ]);

        // Пройти через модель (а не mass update), чтобы отработал saving-хук на status_changed_at.
        DB::transaction(function () use ($posts, $data) {
            foreach ($posts as $post) {
                $payload = ['status_id' => $data['status_id']];
                if (array_key_exists('team_comment', $data)) {
                    $payload['team_comment'] = $data['team_comment'];
                }
                $post->fill($payload)->save();
            }
        });

        return AdminPostResource::collection($this->refreshBulk($posts));
    }

    /** Пачкой назначить общую категорию (или снять). */
    public function bulkCategorize(Request $request): AnonymousResourceCollection
    {
        [$posts, $project] = $this->loadBulk($request);

        $data = $request->validate([
            'category_id' => ['present', 'nullable', 'integer', Rule::exists('categories', 'id')->where('project_id', $project->id)],
        ], [
            'category_id.exists' => 'Такой категории нет в проекте.',
        ]);

        Post::whereIn('id', $posts->pluck('id'))->update(['category_id' => $data['category_id']]);

        return AdminPostResource::collection($this->refreshBulk($posts));
    }

    /** Пачкой скрыть или вернуть на доску. */
    public function bulkHide(Request $request): AnonymousResourceCollection
    {
        [$posts] = $this->loadBulk($request);

        $data = $request->validate(['hidden' => ['required', 'boolean']]);

        Post::whereIn('id', $posts->pluck('id'))->update(['is_hidden' => $data['hidden']]);

        return AdminPostResource::collection($this->refreshBulk($posts));
    }

    /** Пачкой удалить карточки вместе с их аттачами. */
    public function bulkDelete(Request $request): Response
    {
        [$posts] = $this->loadBulk($request);

        DB::transaction(function () use ($posts) {
            foreach ($posts as $post) {
                Storage::disk('local')->deleteDirectory("attachments/{$post->id}");
            }
            Post::whereIn('id', $posts->pluck('id'))->delete();
        });

        return response()->noContent();
    }

    /**
     * Общие проверки для bulk-действий: загрузить карточки по ids, убедиться что они из одного
     * проекта (иначе authz и валидация target-столбца/категории расходятся) и у admin-а есть на него права.
     *
     * @return array{0: Collection<int, Post>, 1: Project}
     */
    private function loadBulk(Request $request): array
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer', 'distinct'],
        ], [
            'ids.required' => 'Выберите карточки.',
            'ids.max' => 'За раз — не больше 100 карточек.',
        ]);

        /** @var Collection<int, Post> $posts */
        $posts = Post::whereIn('id', $data['ids'])->get();

        if ($posts->count() !== count($data['ids'])) {
            throw ValidationException::withMessages(['ids' => 'Некоторые карточки уже удалены.']);
        }

        $projectIds = $posts->pluck('project_id')->unique();
        if ($projectIds->count() !== 1) {
            throw ValidationException::withMessages(['ids' => 'Карточки принадлежат разным проектам.']);
        }

        $project = Project::findOrFail($projectIds->first());
        Gate::authorize('board.triage', $project);

        return [$posts, $project];
    }

    /**
     * @param  Collection<int, Post>  $posts
     * @return Collection<int, Post>
     */
    private function refreshBulk(Collection $posts): Collection
    {
        return Post::with(['category', 'status'])
            ->withCount('attachments')
            ->whereIn('id', $posts->pluck('id'))
            ->get();
    }
}
