<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ComplianceCaseNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'compliance_case_id',
        'user_id',
        'note',
        'note_type',
        'is_confidential',
    ];

    protected $casts = [
        'is_confidential' => 'boolean',
    ];

    public function case(): BelongsTo
    {
        return $this->belongsTo(ComplianceCase::class, 'compliance_case_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
