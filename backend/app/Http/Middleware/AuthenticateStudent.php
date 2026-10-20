<?php

namespace App\Http\Middleware;

use App\Models\Student;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rule 1: students authenticate with the plain token sent in the X-Student-Token header.
 * The authenticated student is available via $request->attributes->get('student').
 */
class AuthenticateStudent
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('X-Student-Token');
        $student = filled($token) ? Student::where('token', Student::hashToken($token))->first() : null;

        if (! $student) {
            return response()->json(['message' => 'Session étudiant invalide. Entre ton nom à nouveau.'], 401);
        }

        $request->attributes->set('student', $student);

        return $next($request);
    }
}
