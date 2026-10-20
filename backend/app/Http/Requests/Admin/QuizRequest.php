<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class QuizRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'time_per_question' => ['sometimes', 'integer', 'min:5', 'max:300'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'Le titre est obligatoire.',
            'title.max' => 'Le titre ne doit pas dépasser 255 caractères.',
            'description.max' => 'La description ne doit pas dépasser 1000 caractères.',
            'time_per_question.integer' => 'Le temps par question doit être un nombre entier.',
            'time_per_question.min' => 'Le temps par question doit être d\'au moins 5 secondes.',
            'time_per_question.max' => 'Le temps par question ne doit pas dépasser 300 secondes.',
        ];
    }
}
