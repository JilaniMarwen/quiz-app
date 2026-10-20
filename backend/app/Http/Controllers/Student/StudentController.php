<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    /**
     * Rule 1: unique name (case-insensitive). The plain token is returned once and only its hash is stored.
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge(['name' => Str::squish((string) $request->input('name'))]);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:30'],
        ], [
            'name.required' => 'Entre ton nom.',
            'name.min' => 'Ton nom doit contenir au moins 2 caractères.',
            'name.max' => 'Ton nom ne doit pas dépasser 30 caractères.',
        ]);

        $taken = ValidationException::withMessages(['name' => 'Ce nom est déjà utilisé.']);

        if (Student::whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])->exists()) {
            throw $taken;
        }

        $token = Str::random(60);

        try {
            $student = Student::create(['name' => $data['name'], 'token' => Student::hashToken($token)]);
        } catch (UniqueConstraintViolationException) {
            throw $taken; // Two devices submitted the same name at the same time.
        }

        return response()->json([
            'student' => ['id' => $student->id, 'name' => $student->name],
            'token' => $token,
        ], 201);
    }

    public function me(Request $request): JsonResponse
    {
        $student = $request->attributes->get('student');

        return response()->json(['student' => ['id' => $student->id, 'name' => $student->name]]);
    }
}
