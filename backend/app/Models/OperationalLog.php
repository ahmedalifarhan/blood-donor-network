<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * One operational log entry (data-model.md §3). Append-only: saving an
 * existing row or deleting one throws.
 *
 * @property string $id
 * @property string $level
 * @property string $event
 * @property string $actor_type
 * @property string|null $actor_ref
 * @property string|null $context
 * @property string|null $ip
 * @property CarbonImmutable $created_at
 */
#[Table('operational_logs', keyType: 'string', incrementing: false, timestamps: false)]
#[Fillable(['id', 'level', 'event', 'actor_type', 'actor_ref', 'context', 'ip', 'created_at'])]
final class OperationalLog extends Model
{
    protected static function booted(): void
    {
        self::updating(static fn (): never => throw new LogicException('Operational logs are append-only.'));
        self::deleting(static fn (): never => throw new LogicException('Operational logs are append-only.'));
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'created_at' => 'immutable_datetime',
        ];
    }
}
