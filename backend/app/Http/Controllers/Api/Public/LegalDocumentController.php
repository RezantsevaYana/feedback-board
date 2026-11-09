<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Services\LegalDocuments;
use Illuminate\Http\JsonResponse;

class LegalDocumentController extends Controller
{
    public function index(LegalDocuments $documents): JsonResponse
    {
        return response()->json(['data' => $documents->all()])->header('Cache-Control', 'no-store');
    }
}
