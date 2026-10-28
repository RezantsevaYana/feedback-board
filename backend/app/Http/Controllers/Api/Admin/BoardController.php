<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateBoardRequest;
use App\Http\Requests\Admin\UpdateBoardRequest;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Boards in the cabinet: the hub list (every board the user is on), create from the wizard,
 * slug availability for the live check, embed payload for the "how to connect" screen,
 * delete to the trash and restore from it (PurgeTrashedBoards empties the trash).
 */
class BoardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // every board the user is on (project_user), whatever the role: the hub groups them
        // into «Я веду» (owner / admin) and «Я участник» by the role in the summary
        $boards = $request->user()->projects()
            ->withCount([
                // "в очереди" = posts whose status is the system "new" one; same slug drives the public widget.
                'posts as queue_count' => fn ($q) => $q->whereHas('status', fn ($s) => $s->where('slug', 'new')),
                'members',
            ])
            ->withMax('posts as last_post_at', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Project $p) => $this->summary($p));

        // the hub shows them in a separate "Корзина" block, newest deletion first
        $trashed = $request->user()->boards()->onlyTrashed()
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn (Project $p) => $this->trashedSummary($p));

        return response()->json(['data' => $boards, 'trashed' => $trashed]);
    }

    public function store(CreateBoardRequest $request, ProjectService $projects): JsonResponse
    {
        $data = $request->validated();
        $board = $projects->create(
            $data['name'],
            $data['slug'],
            $data['origins'] ?? [],
            $request->user(),
        );

        return response()->json(['data' => $this->summary($board->loadCount('members'))], 201);
    }

    /**
     * Settings screen: what owner / admin can edit (name, domains) plus the read-only slug.
     * Categories and statuses come with GET /public/projects/{slug}, as on the board.
     */
    public function show(Request $request, Project $board): JsonResponse
    {
        Gate::authorize('board.configure', $board);

        return response()->json(['data' => $this->settings($board)]);
    }

    public function update(UpdateBoardRequest $request, Project $board): JsonResponse
    {
        Gate::authorize('board.configure', $board);

        $data = $request->validated();
        $board->fill(array_filter([
            'name' => $data['name'] ?? null,
            'allowed_origins' => array_key_exists('origins', $data) ? array_values($data['origins']) : null,
        ], fn ($v) => $v !== null));
        // null is a real value here (back to the default green), so it can't go through the filter above
        if (array_key_exists('widget_color', $data)) {
            $board->widget_color = $data['widget_color'];
        }
        $board->save();

        return response()->json(['data' => $this->settings($board)]);
    }

    /**
     * To the trash, not gone: the owner types the slug to confirm, the board stays recoverable
     * for feedback.trash_retention_days. Its slug stays taken meanwhile (checkSlug uses withTrashed).
     */
    public function destroy(Request $request, Project $board): JsonResponse
    {
        $this->authorizeOwner($request, $board);

        $request->validate(
            ['confirm' => ['required', 'string', Rule::in([$board->slug])]],
            [
                'confirm.required' => 'Введите адрес доски, чтобы подтвердить удаление.',
                'confirm.in' => 'Адрес не совпадает — проверьте и введите ещё раз.',
            ],
        );

        $board->delete();

        return response()->json(['data' => $this->trashedSummary($board)]);
    }

    /**
     * Back from the trash. The route binds {board} withTrashed(); a live board is a no-op.
     */
    public function restore(Request $request, Project $board): JsonResponse
    {
        $this->authorizeOwner($request, $board);

        if ($board->trashed()) {
            $board->restore();
        }

        $board->loadCount([
            'posts as queue_count' => fn ($q) => $q->whereHas('status', fn ($s) => $s->where('slug', 'new')),
            'members',
        ])->loadMax('posts as last_post_at', 'created_at');

        return response()->json(['data' => $this->summary($board)]);
    }

    /**
     * Live slug check for the create form. The frontend debounces ~400ms before calling.
     * Returns {available, reason} so the UI can tailor the message.
     */
    public function checkSlug(Request $request): JsonResponse
    {
        $slug = Str::lower(trim((string) $request->input('slug', '')));

        if ($slug === '' || ! preg_match('/^[a-z0-9][a-z0-9-]{1,63}$/', $slug)) {
            return response()->json(['data' => ['available' => false, 'reason' => 'format']]);
        }
        if (in_array($slug, (array) config('feedback.reserved_slugs'), true)) {
            return response()->json(['data' => ['available' => false, 'reason' => 'reserved']]);
        }
        if (Project::withTrashed()->where('slug', $slug)->exists()) {
            return response()->json(['data' => ['available' => false, 'reason' => 'taken']]);
        }

        return response()->json(['data' => ['available' => true, 'reason' => null]]);
    }

    /**
     * "How to connect" payload: ready-to-paste <script> for the widget and the direct board link.
     * Owner / admin (board.configure).
     */
    public function embed(Request $request, Project $board): JsonResponse
    {
        Gate::authorize('board.configure', $board);

        $publicUrl = (string) config('feedback.public_url');
        $widgetUrl = $publicUrl.'/widget.js';
        $scriptTag = sprintf('<script src="%s" data-project="%s" defer></script>', $widgetUrl, $board->slug);

        return response()->json(['data' => [
            'script_tag' => $scriptTag,
            'widget_url' => $widgetUrl,
            'direct_url' => $publicUrl.'/'.$board->slug,
            'allowed_origins' => $board->allowed_origins ?? [],
            'widget_color' => $board->widget_color,
        ]]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Project $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'created_at' => $p->created_at,
            'queue_count' => (int) ($p->queue_count ?? 0),
            'last_post_at' => $p->last_post_at ?? null,
            'members_count' => (int) ($p->members_count ?? 0),
            // from the pivot when listed through projects(); a board just created or restored is the owner's
            'role' => $p->pivot?->role ?? ProjectRole::Owner->value,
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string, deleted_at: mixed, purge_at: mixed}
     */
    private function trashedSummary(Project $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'deleted_at' => $p->deleted_at,
            'purge_at' => $p->deleted_at?->copy()->addDays((int) config('feedback.trash_retention_days')),
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string, allowed_origins: array<int, string>, widget_color: ?string, trash_retention_days: int}
     */
    private function settings(Project $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'allowed_origins' => $p->allowed_origins ?? [],
            'widget_color' => $p->widget_color,
            // the delete section says how long the board stays recoverable
            'trash_retention_days' => (int) config('feedback.trash_retention_days'),
        ];
    }

    /**
     * Delete / restore stay with the creator (owner_id): a trashed board's pivot rows aren't reachable
     * through roleOn() (the relation skips soft-deleted projects), so the role gate can't vouch for it.
     */
    private function authorizeOwner(Request $request, Project $board): void
    {
        if ($board->owner_id !== $request->user()->id) {
            abort(403, 'Эта доска не ваша.');
        }
    }
}
