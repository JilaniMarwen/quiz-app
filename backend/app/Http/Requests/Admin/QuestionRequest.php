<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A question = non-empty text + exactly 4 non-empty choices, exactly 1 of them correct.
 */
class QuestionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:1000'],
            'position' => ['sometimes', 'integer', 'min:1'],
            'choices' => ['required', 'array', 'size:4'],
            'choices.*.text' => ['required', 'string', 'max:255'],
            'choices.*.is_correct' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $correct = collect($this->input('choices'))
                    ->filter(fn ($choice) => filter_var($choice['is_correct'], FILTER_VALIDATE_BOOLEAN))
                    ->count();

                if ($correct !== 1) {
                    $validator->errors()->add('choices', 'Il faut exactement une bonne réponse.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'text.required' => 'Le texte de la question est obligatoire.',
            'choices.required' => 'Les 4 réponses sont obligatoires.',
            'choices.size' => 'Une question doit avoir exactement 4 réponses.',
            'choices.*.text.required' => 'Chaque réponse doit avoir un texte.',
            'choices.*.text.max' => 'Une réponse ne doit pas dépasser 255 caractères.',
            'choices.*.is_correct.required' => 'Indiquez pour chaque réponse si elle est correcte.',
        ];
    }
}
