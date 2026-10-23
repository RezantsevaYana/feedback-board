<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Boards deleted more than feedback.trash_retention_days ago leave the trash for good.
Schedule::command('boards:purge-trashed')->daily();
