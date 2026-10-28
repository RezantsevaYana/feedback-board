<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\PostPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePostRequest;
use App\Http\Resources\AdminPostResource;
use App\Models\Post;
use App\Models\Project;
use App\Support\VoterToken;
use Illuminate\Database\Eloquent\Collection;
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

        $filters = $request->validate([
            'category' => ['nullable', 'regex:/^(\d+|none)$/'],
            'status_id' => ['nullable', 'integer', Rule::exists('statuses', 'id')->where('project_id', $project->id)],
            'priority' => ['nullable', Rule::in([...array_column(PostPriority::cases(), 'value'), 'none'])],
            'hidden' => ['nullable', Rule::in(['include', 'exclude'])],
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['top', 'new', 'priority'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $posts = $project->posts()
            ->when(($filters['hidden'] ?? 'include') === 'exclude', fn ($q) => $q->where('is_hidden', false))
            ->inCategory($filters['category'] ?? null)
            ->when($filters['status_id'] ?? null, fn ($q, $statusId) => $q->where('status_id', $statusId))
            ->when($filters['priority'] ?? null, fn ($q, $priority) => $priority === 'none'
                ? $q->whereNull('priority')
                : $q->where('priority', $priority))
            ->search($filters['q'] ?? null)
            ->with(['category', 'status'])
            ->withCount('attachments')
            ->sorted($filters['sort'] ?? 'new')
            ->paginate($filters['per_page'] ?? 20);

        VoterToken::markVoted($request, $posts->getCollection());

        return AdminPostResource::collection($posts);
    }

    public function show(Request $request, Post $post): AdminPostResource
    {
        Gate::authorize('board.triage', $post->project);

        VoterToken::markVoted($request, collect([$post]));

        return AdminPostResource::make($post->load('attachments', 'category', 'status'));
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
