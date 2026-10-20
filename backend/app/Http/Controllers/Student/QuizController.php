<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Models\Quiz;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    /**
     * Open quizzes, plus closed ones this student already attempted (shown as « Fermé » with the score,
     * or still resumable if in progress — rule 7).
     */
    public function index(Request $request): JsonResponse
    {
        $student = $request->attributes->get('student');

        $quizzes = Quiz::query()
            ->where(fn ($q) => $q->where('is_open', true)
                ->orWhereHas('attempts', fn ($a) => $a->where('student_id', $student->id)))
            ->withCount('questions')
            ->with(['attempts' => fn ($a) => $a->where('student_id', $student->id)])
            ->latest('id')
            ->get()
            ->map(function (Quiz $quiz) {
                $attempt = $quiz->attempts->first();
                $status = match (true) {
                    ! $attempt => 'not_started',
                    ! $attempt->finished_at => 'in_progress',
                    default => 'done',
                };

                return [
                    'id' => $quiz->id,
                    'title' => $quiz->title,
                    'description' => $quiz->description,
                    'time_per_question' => $quiz->time_per_question,
                    'questions_count' => $quiz->questions_count,
                    'is_open' => $quiz->is_open,
                    'status' => $status,
                    'attempt_id' => $attempt?->id,
                    'score' => $status === 'done' ? $attempt->score : null,
                    'total' => $status === 'done' ? $attempt->total : null,
                ];
            });

        return response()->json(['data' => $quizzes]);
    }

    /**
     * Rule 2: one attempt per quiz (409 + existing attempt). Rule 7: a closed quiz cannot be started.
     */
    public function start(Request $request, Quiz $quiz): JsonResponse
    {
        $student = $request->attributes->get('student');

        $existing = fn () => $quiz->attempts()->where('student_id', $student->id)->first();

        if ($attempt = $existing()) {
            return $this->alreadyStarted($attempt);
        }

        if (! $quiz->is_open) {
            return response()->json(['message' => 'Ce quiz est fermé.'], 403);
        }

        try {
            $attempt = $quiz->attempts()->create([
                'student_id' => $student->id,
                'started_at' => now(),
                'total' => $quiz->questions()->count(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->alreadyStarted($existing()); // Double click: two simultaneous starts.
        }

        return response()->json(['attempt' => $this->attemptSummary($attempt)], 201);
    }

    private function alreadyStarted(Attempt $attempt): JsonResponse
    {
        return response()->json([
            'message' => 'Tu as déjà commencé ce quiz.',
            'attempt' => $this->attemptSummary($attempt),
        ], 409);
    }

    private function attemptSummary(Attempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'quiz_id' => $attempt->quiz_id,
            'status' => $attempt->finished_at ? 'done' : 'in_progress',
        ];
    }
}
