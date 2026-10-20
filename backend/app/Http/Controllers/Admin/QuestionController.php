<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuestionRequest;
use App\Http\Resources\Admin\QuestionResource;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class QuestionController extends Controller
{
    public function store(QuestionRequest $request, Quiz $quiz): JsonResponse
    {
        $question = DB::transaction(function () use ($request, $quiz) {
            $question = $quiz->questions()->create([
                'text' => $request->validated('text'),
                'position' => $request->validated('position') ?? $quiz->questions()->max('position') + 1,
            ]);

            foreach ($request->validated('choices') as $i => $choice) {
                $question->choices()->create([
                    'text' => $choice['text'],
                    'is_correct' => (bool) $choice['is_correct'],
                    'position' => $i + 1,
                ]);
            }

            return $question;
        });

        return (new QuestionResource($question->load('choices')))->response()->setStatusCode(201);
    }

    /**
     * Choices are updated in place (by position) so their ids stay stable.
     */
    public function update(QuestionRequest $request, Question $question): QuestionResource
    {
        DB::transaction(function () use ($request, $question) {
            $question->update($request->safe()->only(['text', 'position']));

            $existing = $question->choices()->get()->values();

            foreach (array_values($request->validated('choices')) as $i => $data) {
                $attributes = [
                    'text' => $data['text'],
                    'is_correct' => (bool) $data['is_correct'],
                    'position' => $i + 1,
                ];

                isset($existing[$i])
                    ? $existing[$i]->update($attributes)
                    : $question->choices()->create($attributes);
            }

            // Should never happen (always 4), but keep data consistent.
            $existing->slice(4)->each->delete();
        });

        return new QuestionResource($question->load('choices'));
    }

    public function destroy(Question $question): JsonResponse|Response
    {
        $quiz = $question->quiz;

        if ($quiz->is_open && $quiz->questions()->count() === 1) {
            return response()->json([
                'message' => 'Fermez le quiz avant de supprimer sa dernière question.',
            ], 422);
        }

        $question->delete();

        return response()->noContent();
    }
}
