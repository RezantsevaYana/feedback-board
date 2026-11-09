<?php

namespace Tests;

use App\Services\LegalDocuments;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function legalPayload(): array
    {
        $documents = app(LegalDocuments::class)->all();
        $payload = [];
        foreach (['terms', 'personal_data'] as $key) {
            $payload[$key] = ['accepted' => true, 'version' => $documents[$key]['version'], 'content_hash' => $documents[$key]['content_hash']];
        }

        return $payload;
    }
}
