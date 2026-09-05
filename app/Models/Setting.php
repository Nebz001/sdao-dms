<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic key/value store for admin-controlled, system-wide settings — e.g.
 * `current_period`. Do not read/write this model directly outside a typed
 * accessor (see App\Support\CurrentPeriod) — the accessor owns
 * parsing/casting so callers never handle raw strings.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value
 */
#[Fillable(['key', 'value'])]
class Setting extends Model
{
    //
}
