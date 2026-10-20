<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['student_id', 'quiz_id', 'started_at', 'finished_at', 'score', 'total'])]
class Attempt extends Model
{
    public $timestamps = false;

    // Millisecond precision: durations break leaderboard ties.
    protected $dateFormat = 'Y-m-d H:i:s.v';

    public function durationMs(): ?int
    {
        return $this->finished_at
            ? (int) abs($this->started_at->diffInMilliseconds($this->finished_at))
            : null;
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'score' => 'integer',
            'total' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function quiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }
}
