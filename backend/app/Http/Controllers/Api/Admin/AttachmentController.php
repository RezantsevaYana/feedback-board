<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Support\AttachmentResponse;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Unlike the public endpoint, also serves files of hidden posts.
     */
    public function show(Attachment $attachment): StreamedResponse
    {
        Gate::authorize('board.triage', $attachment->post->project);

        return AttachmentResponse::make($attachment);
    }
}
