<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Справочник анонимных авторов: один `voter_token` → одно имя. Используется только
 * для публичного лидерборда. Пустое имя не хранится — такой автор остаётся анонимным.
 */
#[Fillable(['token', 'display_name'])]
class Voter extends Model
{
    protected $primaryKey = 'token';

    public $incrementing = false;

    protected $keyType = 'string';
}
