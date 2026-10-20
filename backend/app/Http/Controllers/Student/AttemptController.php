<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Attempt;
use App\Services\AttemptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttemptController extends Controller
{
    public function __construct(private AttemptService $attempts) {}

    public function current(Request $request, Attempt $attempt): JsonResponse
    {
        $this->authorizeOwner($request, $attempt);

        return response()->json($this->attempts->current($attempt));
    }

    public function answer(Request $request, Attempt $attempt): JsonResponse
    {
        $this->authorizeOwner($request, $attempt);

        $data = $request->validate([
            'question_id' => ['required', 'integer'],
            'choice_id' => ['present', 'nullable', 'integer'],
        ]);

        return response()->json($this->attempts->answer(
            $attempt,
            (int) $data['question_id'],
            isset($data['choice_id']) ? (int) $data['choice_id'] : null,
        ));
    }

    public function result(Request $request, Attempt $attempt): JsonResponse
    {
        $this->authorizeOwner($request, $attempt);

        $result = $this->attempts->result($attempt);

        return response()->json($result, $result['status'] === 'finished' ? 200 : 409);
    }

    /**
     * Another student's attempt looks like it does not exist.
     */
    private function authorizeOwner(Request $request, Attempt $attempt): void
    {
        abort_unless($attempt->student_id === $request->attributes->get('student')->id, 404);
    }
}
