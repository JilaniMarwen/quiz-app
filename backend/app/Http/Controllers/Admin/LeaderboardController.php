<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;

class LeaderboardController extends Controller
{
    /**
     * Rule 6: score desc, then total time asc. Attempts still in progress come after
     * finished ones with the same score.
     */
    public function __invoke(Quiz $quiz): JsonResponse
    {
        $rows = $quiz->attempts()->with('student:id,name')->get()
            ->map(fn (Attempt $attempt) => [
                'attempt_id' => $attempt->id,
                'student_id' => $attempt->student_id,
                'name' => $attempt->student->name,
                'score' => $attempt->score,
                'total' => $attempt->total,
                'finished' => $attempt->finished_at !== null,
                'duration_ms' => $attempt->durationMs(),
                'duration_seconds' => $attempt->finished_at ? intdiv($attempt->durationMs(), 1000) : null,
            ])
            ->sort(fn ($a, $b) => [$b['score'], $b['finished'], $a['duration_ms'] ?? PHP_INT_MAX]
                <=> [$a['score'], $a['finished'], $b['duration_ms'] ?? PHP_INT_MAX])
            ->values();

        // Same score and same time = same rank (1, 2, 2, 4…).
        $previous = null;
        $rows = $rows->map(function ($row, $i) use (&$previous) {
            $key = [$row['score'], $row['finished'], $row['duration_ms']];
            $row['rank'] = $previous && $previous['key'] === $key ? $previous['rank'] : $i + 1;
            $previous = ['key' => $key, 'rank' => $row['rank']];

            return $row;
        });

        return response()->json([
            'quiz' => [
                'id' => $quiz->id,
                'title' => $quiz->title,
                'is_open' => $quiz->is_open,
                'questions_count' => $quiz->questions()->count(),
            ],
            'entries' => $rows,
        ]);
    }
}
