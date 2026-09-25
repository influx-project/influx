<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class OverviewController extends Controller
{
    /**
     * Show the admin overview.
     */
    public function __invoke(): Response
    {
        return Inertia::render('admin/overview', [
            'stats' => [
                'total_users' => User::count(),
                'admins' => User::where('admin', true)->count(),
                'verified_users' => User::whereNotNull('email_verified_at')->count(),
                'two_factor_users' => User::whereNotNull('two_factor_confirmed_at')->count(),
                'new_users_last_7_days' => User::where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'recent_users' => User::latest()->limit(5)->get(['id', 'name', 'email', 'admin', 'created_at']),
        ]);
    }
}
