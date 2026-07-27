<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates every /admin/* marketplace route (spec 2.5 Admin CRUD) behind a real
 * authorization check, not just "any authenticated user."
 *
 * Register in bootstrap/app.php (Laravel 11+):
 *   $middleware->alias(['marketplace.admin' => \App\Http\Middleware\EnsureUserIsMarketplaceAdmin::class]);
 * or in app/Http/Kernel.php $middlewareAliases (Laravel 10):
 *   'marketplace.admin' => \App\Http\Middleware\EnsureUserIsMarketplaceAdmin::class,
 *
 * Then route group middleware becomes ['auth:sanctum', 'marketplace.admin'].
 *
 * If your app already has a roles/permissions package (Spatie
 * laravel-permission, etc.), swap the check below for
 * $request->user()->hasRole('admin') or ->can('marketplace.manage') and drop
 * the is_marketplace_admin migration.
 */
class EnsureUserIsMarketplaceAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_marketplace_admin) {
            abort(403, 'You do not have access to the Provider Marketplace admin.');
        }

        return $next($request);
    }
}
