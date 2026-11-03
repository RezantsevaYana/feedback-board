<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * «Вернуться в админку» из баннера SPA. Платформенная сессия всё это время
 * оставалась живой — просто разлогиниваем web guard и чистим ключ, по которому
 * фронт узнавал о текущей impersonation.
 */
class ImpersonationController extends Controller
{
    public function stop(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        if ($request->hasSession()) {
            $request->session()->forget('impersonator_platform_id');
            // Session token rotation — чтобы старая кука от «подменённой» сессии не могла залогинить обратно.
            $request->session()->regenerateToken();
        }

        return response()->json([
            'data' => ['redirect' => '/admin'],
        ]);
    }
}
