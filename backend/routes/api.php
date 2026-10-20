<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\LeaderboardController;
use App\Http\Controllers\Admin\QuestionController;
use App\Http\Controllers\Admin\QuizController;
use App\Http\Controllers\Student\AttemptController as StudentAttemptController;
use App\Http\Controllers\Student\QuizController as StudentQuizController;
use App\Http\Controllers\Student\StudentController;
use Illuminate\Support\Facades\Route;

// Student — X-Student-Token header
Route::post('students', [StudentController::class, 'store']);

Route::prefix('student')->middleware('student')->group(function () {
    Route::get('me', [StudentController::class, 'me']);
    Route::get('quizzes', [StudentQuizController::class, 'index']);
    Route::post('quizzes/{quiz}/start', [StudentQuizController::class, 'start']);
    Route::get('attempts/{attempt}/current', [StudentAttemptController::class, 'current']);
    Route::post('attempts/{attempt}/answer', [StudentAttemptController::class, 'answer']);
    Route::get('attempts/{attempt}/result', [StudentAttemptController::class, 'result']);
});

// Professor (admin) — Sanctum bearer token
Route::prefix('admin')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);

        Route::apiResource('quizzes', QuizController::class);
        Route::patch('quizzes/{quiz}/toggle', [QuizController::class, 'toggle']);
        Route::delete('quizzes/{quiz}/attempts', [QuizController::class, 'resetAttempts']);
        Route::get('quizzes/{quiz}/leaderboard', LeaderboardController::class);

        Route::post('quizzes/{quiz}/questions', [QuestionController::class, 'store']);
        Route::put('questions/{question}', [QuestionController::class, 'update']);
        Route::delete('questions/{question}', [QuestionController::class, 'destroy']);
    });
});
