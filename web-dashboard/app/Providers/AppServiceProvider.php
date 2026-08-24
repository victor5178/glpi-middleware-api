<?php

namespace App\Providers;

use App\Services\Rbac;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One RBAC resolution per request (queries the middleware once).
        $this->app->singleton(Rbac::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // @perm('module','action') ... @endperm  — hide UI the user can't use.
        // Action defaults to 'view'; the special @perm('admin') checks is_admin.
        Blade::directive('perm', function ($expression) {
            return "<?php if (app(\\App\\Services\\Rbac::class)->" .
                "{$this->permCall($expression)}): ?>";
        });
        Blade::directive('endperm', fn () => '<?php endif; ?>');
    }

    /** Translate the @perm(...) arguments into an Rbac method call. */
    protected function permCall(string $expression): string
    {
        $args = array_map('trim', explode(',', trim($expression, '()')));
        $first = trim($args[0], "'\"");
        if ($first === 'admin') {
            return 'isAdmin()';
        }
        $module = var_export($first, true);
        $action = isset($args[1]) ? var_export(trim($args[1], "'\""), true) : "'view'";
        return "can({$module}, {$action})";
    }
}
