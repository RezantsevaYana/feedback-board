<?php

namespace App\Services;

use App\Models\LegalAcceptance;
use App\Models\User;
use Illuminate\Validation\Rule;

class LegalDocuments
{
    public const REQUIRED = ['terms', 'personal_data'];

    public function all(): array
    {
        $documents = [];
        foreach (config('legal.documents') as $key => $document) {
            $snapshot = [
                'key' => $key,
                'title' => $document['title'],
                'path' => $document['path'],
                'version' => config('legal.version'),
                'draft' => (bool) config('legal.draft'),
                'operator' => config('legal.operator'),
                'sections' => $document['sections'],
            ];
            $documents[$key] = $snapshot + [
                'content_hash' => hash('sha256', json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
            ];
        }

        return $documents;
    }

    public function rules(): array
    {
        $rules = [];
        $documents = $this->all();
        foreach (self::REQUIRED as $key) {
            $document = $documents[$key];
            $rules["legal.{$key}.accepted"] = ['required', 'accepted'];
            $rules["legal.{$key}.version"] = ['required', 'string', Rule::in([$document['version']])];
            $rules["legal.{$key}.content_hash"] = ['required', 'string', Rule::in([$document['content_hash']])];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'legal.terms.accepted.required' => 'Подтвердите принятие пользовательского соглашения.',
            'legal.terms.accepted.accepted' => 'Подтвердите принятие пользовательского соглашения.',
            'legal.personal_data.accepted.required' => 'Подтвердите согласие на обработку персональных данных.',
            'legal.personal_data.accepted.accepted' => 'Подтвердите согласие на обработку персональных данных.',
            'legal.*.version.required' => 'Откройте актуальные документы и подтвердите их.',
            'legal.*.content_hash.required' => 'Откройте актуальные документы и подтвердите их.',
            'legal.*.version.in' => 'Документы обновились. Загрузите их заново и подтвердите новую редакцию.',
            'legal.*.content_hash.in' => 'Документы обновились. Загрузите их заново и подтвердите новую редакцию.',
        ];
    }

    public function record(User $user, string $source): void
    {
        $documents = $this->all();
        foreach (self::REQUIRED as $key) {
            $snapshot = $documents[$key];
            $hash = $snapshot['content_hash'];
            unset($snapshot['content_hash']);
            LegalAcceptance::create([
                'user_id' => $user->id,
                'document_key' => $key,
                'version' => $snapshot['version'],
                'content_hash' => $hash,
                'snapshot' => $snapshot,
                'source' => $source,
                'accepted_at' => now(),
            ]);
        }
    }
}
