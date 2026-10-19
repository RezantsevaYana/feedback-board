<?php

namespace App\Http\Middleware;

use App\Models\Project;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-project CORS for the widget: it may read the project (categories) and submit only from the project's allowed_origins.
 * Requests from the board itself (our own frontend) are always allowed.
 */
class AllowProjectOrigin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Project $project */
        $project = $request->route('project');
        $origin = $request->headers->get('Origin');

        if ($origin !== null && ! $this->isOwnOrigin($request, $origin) && ! $project->allowsOrigin($origin)) {
            return response()->json(['message' => 'Отправка с этого сайта не разрешена для проекта.'], 403);
        }

        $response = $request->isMethod('OPTIONS') ? response()->noContent() : $next($request);

        if ($origin !== null) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, OPTIONS');
            $response->headers->set('Access-Control-Allow-Headers', 'Accept, Content-Type, X-Requested-With');
            $response->headers->set('Access-Control-Max-Age', '600');
            $response->headers->set('Vary', 'Origin');
        }

        return $response;
    }

    private function isOwnOrigin(Request $request, string $origin): bool
    {
        $own = array_filter([$request->getSchemeAndHttpHost(), rtrim((string) config('app.frontend_url'), '/')]);

        return in_array($origin, $own, true);
    }
}
