<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The active company/location must be resolved on every authenticated
        // request, so it runs as part of the web stack rather than per route.
        $middleware->web(append: [
            \App\Http\Middleware\ApplyScopeContext::class,
            // Runs after the scope so the change-password page can still render
            // the layout, but before anything the user might otherwise reach.
            \App\Http\Middleware\RequirePasswordChange::class,
        ]);

        $middleware->alias([
            'permission' => \App\Http\Middleware\CheckPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A stale CSRF token normally throws the bare "Page expired" screen and
        // loses whatever was typed. Send people back to the form they came from
        // with a fresh token and an explanation instead.
        // The framework rewrites TokenMismatchException into a 419 HttpException
        // before render callbacks run, so match on the status rather than the
        // original class.
        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your session expired. Please reload and try again.'], 419);
            }

            $target = $request->user() === null ? route('login') : url()->previous();

            return redirect()->to($target)
                ->withInput($request->except('password', 'password_confirmation'))
                ->with('error', 'Your session expired before that was submitted. Please try again.');
        });
    })->create();
