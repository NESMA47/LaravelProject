<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->role !== 'employer') {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Employer access required.',
            ], 403);
        }

        return $next($request);
    }
}
