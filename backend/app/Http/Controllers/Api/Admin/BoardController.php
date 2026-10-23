<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateBoardRequest;
use App\Http\Requests\Admin\UpdateBoardRequest;
use App\Models\Project;
use App\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Self-serve boards owned by the current admin: list for the hub, create from the wizard,
 * slug availability for the live check, embed payload for the "how to connect" screen.
 */
class BoardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $boards = $request->user()->boards()
            ->withCount([
                // "в очереди" = posts whose status is the system "new" one; same slug drives the public widget.
                'posts as queue_count' => fn ($q) => $q->whereHas('status', fn ($s) => $s->where('slug', 'new')),
            ])
            ->withMax('posts as last_post_at', 'created_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Project $p) => $this->summary($p));

        return response()->json(['data' => $boards]);
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

        return response()->json(['data' => $this->summary($board)], 201);
    }

    /**
     * Settings screen: what the owner can edit (name, domains) plus the read-only slug.
     * Categories and statuses come with GET /public/projects/{slug}, as on the board.
     */
    public function show(Request $request, Project $board): JsonResponse
    {
        $this->authorizeOwner($request, $board);

        return response()->json(['data' => $this->settings($board)]);
    }

    public function update(UpdateBoardRequest $request, Project $board): JsonResponse
    {
        $this->authorizeOwner($request, $board);

        $data = $request->validated();
        $board->update(array_filter([
            'name' => $data['name'] ?? null,
            'allowed_origins' => array_key_exists('origins', $data) ? array_values($data['origins']) : null,
        ], fn ($v) => $v !== null));

        return response()->json(['data' => $this->settings($board)]);
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
     * Owner-only; see routes/api.php for the scopeBindings on {slug}.
     */
    public function embed(Request $request, Project $board): JsonResponse
    {
        $this->authorizeOwner($request, $board);

        $publicUrl = (string) config('feedback.public_url');
        $widgetUrl = $publicUrl.'/widget.js';
        $scriptTag = sprintf('<script src="%s" data-project="%s" defer></script>', $widgetUrl, $board->slug);

        return response()->json(['data' => [
            'script_tag' => $scriptTag,
            'widget_url' => $widgetUrl,
            'direct_url' => $publicUrl.'/'.$board->slug,
            'allowed_origins' => $board->allowed_origins ?? [],
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
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string, allowed_origins: array<int, string>}
     */
    private function settings(Project $p): array
    {
        return [
            'id' => $p->id,
            'name' => $p->name,
            'slug' => $p->slug,
            'allowed_origins' => $p->allowed_origins ?? [],
        ];
    }

    private function authorizeOwner(Request $request, Project $board): void
    {
        if ($board->owner_id !== $request->user()->id) {
            abort(403, 'Эта доска не ваша.');
        }
    }
}
