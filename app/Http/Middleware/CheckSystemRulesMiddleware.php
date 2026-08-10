<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\SystemRule;
use Symfony\Component\HttpFoundation\Response;

class CheckSystemRulesMiddleware
{
    /**
     * Handle an incoming request by dynamically evaluating active System Rules.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && \Illuminate\Support\Facades\Schema::hasTable('system_rules')) {
            $rawPath = '/' . ltrim($request->path(), '/');

            // Fetch active page access rules
            $pageAccessRules = SystemRule::where('enabled', true)
                ->where('type', 'page_access_rule')
                ->get();

            foreach ($pageAccessRules as $rule) {
                $logic = $rule->rule_logic ?? [];
                $rawTarget = $logic['target_route'] ?? null;

                if (!$rawTarget) {
                    continue;
                }

                // Normalize target route and current path for exact and prefix matching
                $targetRoute = '/' . ltrim(trim($rawTarget), '/');
                $currentPath = $rawPath;

                $isMatch = ($currentPath === $targetRoute) || 
                           (str_starts_with($currentPath, rtrim($targetRoute, '/') . '/'));

                if ($isMatch) {
                    $allowedSystemRoles = $logic['allowed_system_roles'] ?? [];
                    $allowedFunctionalRoles = $logic['allowed_functional_roles'] ?? [];

                    $userSystemRole = $user->role?->slug;
                    $userFunctionalRoles = $user->member ? $user->member->memberRoles->pluck('slug')->toArray() : [];

                    $hasSystemRoleAccess = !empty($allowedSystemRoles) && in_array($userSystemRole, $allowedSystemRoles);
                    $hasFunctionalRoleAccess = !empty($allowedFunctionalRoles) && count(array_intersect($userFunctionalRoles, $allowedFunctionalRoles)) > 0;

                    $isAllowed = $hasSystemRoleAccess || $hasFunctionalRoleAccess;

                    if (!$isAllowed) {
                        $action = $logic['restriction_action'] ?? 'block_and_redirect';
                        $errorMsg = $logic['error_message'] ?? "Access Restricted: You do not have permission to view this page according to active System Rule '{$rule->name}'.";

                        if ($action === 'block_and_redirect') {
                            return redirect()->route('dashboard')->with('error', $errorMsg);
                        } else {
                            abort(403, $errorMsg);
                        }
                    }
                }
            }
        }

        return $next($request);
    }
}
