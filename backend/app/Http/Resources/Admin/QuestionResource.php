<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin only: includes is_correct.
 *
 * @mixin \App\Models\Question
 */
class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quiz_id' => $this->quiz_id,
            'text' => $this->text,
            'position' => $this->position,
            'choices' => $this->whenLoaded('choices', fn () => $this->choices->map(fn ($choice) => [
                'id' => $choice->id,
                'text' => $choice->text,
                'is_correct' => $choice->is_correct,
                'position' => $choice->position,
            ])),
        ];
    }
}
