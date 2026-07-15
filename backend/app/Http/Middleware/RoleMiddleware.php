<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.'
            ], 401);
        }

        if (!$user->role) {
            return response()->json([
                'success' => false,
                'message' => 'No role assigned.'
            ], 403);
        }

        if (!in_array($user->role->roleName, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied.'
            ], 403);
        }

        return $next($request);
    }
}