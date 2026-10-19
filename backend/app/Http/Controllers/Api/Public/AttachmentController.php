<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Support\AttachmentResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentController extends Controller
{
    /**
     * Files live outside the public folder and are served only while the post is visible.
     */
    public function show(Attachment $attachment): StreamedResponse
    {
        abort_if($attachment->post->is_hidden, 404);

        return AttachmentResponse::make($attachment);
    }
}
