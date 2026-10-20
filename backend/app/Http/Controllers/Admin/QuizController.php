<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\QuizRequest;
use App\Http\Resources\Admin\QuizResource;
use App\Models\Quiz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class QuizController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $quizzes = Quiz::withCount(['questions', 'attempts'])->latest('id')->get();

        return QuizResource::collection($quizzes);
    }

    public function store(QuizRequest $request): JsonResponse
    {
        $quiz = Quiz::create($request->validated());

        return $this->detail($quiz)->response()->setStatusCode(201);
    }

    public function show(Quiz $quiz): QuizResource
    {
        return $this->detail($quiz);
    }

    public function update(QuizRequest $request, Quiz $quiz): QuizResource
    {
        $quiz->update($request->validated());

        return $this->detail($quiz);
    }

    public function destroy(Quiz $quiz): Response
    {
        $quiz->delete();

        return response()->noContent();
    }

    /**
     * Open ↔ close. Rule 8: an invalid quiz cannot be opened (closing is always allowed).
     */
    public function toggle(Quiz $quiz): JsonResponse|QuizResource
    {
        if (! $quiz->is_open && ! $quiz->isValid()) {
            return response()->json([
                'message' => 'Impossible d\'ouvrir ce quiz : il faut au moins une question, chacune avec 4 réponses dont une seule correcte.',
            ], 422);
        }

        $quiz->update(['is_open' => ! $quiz->is_open]);

        return $this->detail($quiz);
    }

    /**
     * Rule 9 (option): delete all results so the quiz can be edited and replayed.
     */
    public function resetAttempts(Quiz $quiz): QuizResource
    {
        $quiz->attempts()->delete();

        return $this->detail($quiz);
    }

    private function detail(Quiz $quiz): QuizResource
    {
        return new QuizResource($quiz->load('questions.choices')->loadCount(['questions', 'attempts']));
    }
}
