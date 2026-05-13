<?php

use App\Exceptions\KamusReportableException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->throttleApi();

        $middleware->alias([
            'role' => \App\Http\Middleware\EnsureUserHasRole::class,
        ]);

        // API requests must never be redirected to a HTML login page; return null
        // so the AuthenticationException renderer below produces a JSON 401.
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return null;
            }

            return null;
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (KamusReportableException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return $e->render();
            }

            return null;
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'domain' => 'validation',
                        'code' => 'invalid_input',
                        'message' => 'Data yang dikirim tidak valid.',
                        'data' => ['errors' => $e->errors()],
                    ],
                ], 422);
            }

            return null;
        });

        // Force JSON for every /api/* failure — Sanctum/Laravel default redirects to
        // the (non-existent) `login` named route when Accept header isn't JSON.
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'domain' => 'auth',
                        'code' => 'unauthenticated',
                        'message' => 'Anda harus login terlebih dahulu.',
                    ],
                ], 401);
            }

            return null;
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'domain' => 'http',
                        'code' => 'not_found',
                        'message' => 'Endpoint atau resource tidak ditemukan.',
                    ],
                ], 404);
            }

            return null;
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($request->is('api/*') && $e->getStatusCode() >= 400) {
                return response()->json([
                    'error' => [
                        'domain' => 'http',
                        'code' => 'http_'.$e->getStatusCode(),
                        'message' => $e->getMessage() ?: 'Permintaan tidak dapat diproses.',
                    ],
                ], $e->getStatusCode());
            }

            return null;
        });
    })->create();
