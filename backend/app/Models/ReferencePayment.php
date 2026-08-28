<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Persistence model for `reference_payments`. Kept to persistence concerns
 * only — no business decisions here (see App\Domain\Checks\ReferencePayment
 * for the Domain-layer representation used by CheckRunner/CheckRule).
 */
class ReferencePayment extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'address',
        'amount',
        'network',
        'allowed_scripts',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_scripts' => 'array',
        ];
    }
}
