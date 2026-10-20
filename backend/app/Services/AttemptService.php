<?php

namespace App\Services;

use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Choice;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Plays an attempt one question at a time, with the timer enforced server-side (rule 3).
 *
 * An attempt always has at most one "pending" answer row: the question currently served
 * (served_at set, answered_at null). Every state returned here is safe to send to the
 * student: it never contains is_correct (rule 4).
 */
class AttemptService
{
    /** Network latency margin added to the question time. */
    public const GRACE_SECONDS = 2;

    /**
     * Current state: resumes the pending question, or expires it and serves the next one,
     * or finishes the attempt when no question is left.
     */
    public function current(Attempt $attempt): array
    {
        return DB::transaction(fn () => $this->advance($this->lock($attempt)));
    }

    /**
     * Record the answer to the pending question ($choiceId null = time ran out),
     * then return the next state.
     */
    public function answer(Attempt $attempt, int $questionId, ?int $choiceId): array
    {
        return DB::transaction(function () use ($attempt, $questionId, $choiceId) {
            $attempt = $this->lock($attempt);
            $pending = $attempt->finished_at ? null : $this->pending($attempt);

            if (! $pending || $pending->question_id !== $questionId) {
                throw new HttpException(409, 'Cette question n\'est plus active.');
            }

            $choice = null;
            if ($choiceId !== null) {
                $choice = Choice::where('question_id', $questionId)->find($choiceId)
                    ?? throw new HttpException(422, 'Cette réponse n\'appartient pas à la question.');
            }

            $deadline = $this->deadline($attempt, $pending);
            $onTime = $this->now()->lte($deadline->copy()->addSeconds(self::GRACE_SECONDS));

            $pending->update([
                'choice_id' => $choice?->id,
                'is_correct' => $onTime && $choice?->is_correct === true,
                'answered_at' => $onTime ? $this->now() : $deadline,
            ]);

            $attempt->update(['score' => $attempt->answers()->where('is_correct', true)->count()]);

            return $this->advance($attempt);
        });
    }

    /**
     * State of a finished attempt (or of one still in progress).
     */
    public function result(Attempt $attempt): array
    {
        return $attempt->finished_at
            ? $this->finishedState($attempt)
            : ['status' => 'in_progress', 'attempt_id' => $attempt->id];
    }

    private function advance(Attempt $attempt): array
    {
        if ($attempt->finished_at) {
            return $this->finishedState($attempt);
        }

        $pending = $this->pending($attempt);

        // Time ran out (student left or lost connection): counts as a wrong answer.
        if ($pending && $this->now()->gt($this->deadline($attempt, $pending)->addSeconds(self::GRACE_SECONDS))) {
            $pending->update(['answered_at' => $this->deadline($attempt, $pending)]);
            $pending = null;
        }

        if (! $pending) {
            $next = $attempt->quiz->questions()
                ->whereNotIn('id', $attempt->answers()->select('question_id'))
                ->first();

            if (! $next) {
                return $this->finish($attempt);
            }

            $pending = $attempt->answers()->create(['question_id' => $next->id, 'served_at' => $this->now()]);
        }

        return $this->questionState($attempt, $pending);
    }

    private function finish(Attempt $attempt): array
    {
        $lastAnswer = $attempt->answers()->whereNotNull('answered_at')->orderByDesc('answered_at')->first();

        $attempt->update([
            'score' => $attempt->answers()->where('is_correct', true)->count(),
            'total' => $attempt->quiz->questions()->count(),
            'finished_at' => $lastAnswer?->answered_at ?? $this->now(),
        ]);

        return $this->finishedState($attempt);
    }

    private function questionState(Attempt $attempt, AttemptAnswer $pending): array
    {
        $question = $pending->question()->with('choices')->first();
        $remaining = $this->now()->diffInSeconds($this->deadline($attempt, $pending), false);

        return [
            'status' => 'in_progress',
            'attempt_id' => $attempt->id,
            'quiz' => ['id' => $attempt->quiz->id, 'title' => $attempt->quiz->title],
            'question' => [
                'id' => $question->id,
                'text' => $question->text,
                // Explicit fields only: is_correct must never leave the server.
                'choices' => $question->choices->map(fn (Choice $choice) => [
                    'id' => $choice->id,
                    'text' => $choice->text,
                ])->values(),
            ],
            'index' => $attempt->answers()->count(),
            'total' => $attempt->quiz->questions()->count(),
            'time_per_question' => $attempt->quiz->time_per_question,
            'remaining_seconds' => max(0, (int) ceil($remaining)),
        ];
    }

    private function finishedState(Attempt $attempt): array
    {
        return [
            'status' => 'finished',
            'attempt_id' => $attempt->id,
            'quiz' => ['id' => $attempt->quiz->id, 'title' => $attempt->quiz->title],
            'score' => $attempt->score,
            'total' => $attempt->total,
        ];
    }

    private function pending(Attempt $attempt): ?AttemptAnswer
    {
        return $attempt->answers()->whereNull('answered_at')->latest('id')->first();
    }

    private function deadline(Attempt $attempt, AttemptAnswer $pending): Carbon
    {
        return $pending->served_at->copy()->addSeconds($attempt->quiz->time_per_question);
    }

    /**
     * Current time at the precision stored in the database (milliseconds).
     */
    private function now(): Carbon
    {
        return now()->startOfMillisecond();
    }

    /**
     * Serialize concurrent requests on the same attempt (double clicks, two tabs).
     */
    private function lock(Attempt $attempt): Attempt
    {
        return Attempt::with('quiz')->lockForUpdate()->findOrFail($attempt->id);
    }
}
