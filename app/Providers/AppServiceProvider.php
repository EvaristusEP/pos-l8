<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Blade::directive('vite', function ($expression) {
            return "<?php echo '<link rel=\"stylesheet\" href=\"'.e(asset('build/assets/app-B5fCZYdi.css')).'\"><script type=\"module\" src=\"'.e(asset('build/assets/app-tpbaJ3jZ.js')).'\"></script>'; ?>";
        });
    }
}
