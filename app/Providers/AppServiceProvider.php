<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Plain, dependency-free pagination markup (no Tailwind / Bootstrap needed).
        Paginator::defaultView('pagination.default');
        Paginator::defaultSimpleView('pagination.default');
    }
}
