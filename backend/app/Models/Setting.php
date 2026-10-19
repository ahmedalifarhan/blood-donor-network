<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A stored setting override (data-model.md §1). Used only by the settings repository.
 *
 * @property string $id
 * @property string $key
 * @property string $value
 * @property CarbonImmutable $updated_at
 * @property string $updated_by
 */
#[Table('settings', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable(['id', 'key', 'value', 'updated_at', 'updated_by'])]
final class Setting extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'updated_at' => 'immutable_datetime',
        ];
    }
}
