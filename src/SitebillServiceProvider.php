<?php

namespace Sitebill\CRUD;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Sitebill\CRUD\app\Library\CrudPanel\CrudPanel;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Routing\Router;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class SitebillServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register()
    {
        // Bind the CrudPanel object to Laravel's service container
        $this->app->singleton('crud', function ($app) {
            return new CrudPanel($app);
        });

        // Bind the widgets collection object to Laravel's service container
        $this->app->singleton('widgets', function ($app) {
            return new Collection();
        });


        // load a macro for Route,
        // helps developers load all routes for a CRUD resource in one line
        if (! Route::hasMacro('crud')) {
            $this->addRouteMacro();
        }

        // register the helper functions
        $this->loadHelpers();



        //$this->app->make('Sitebill\Sitebill');
        //$this->app->register('Sitebill\CRUD');

        /*
        $this->app->make('Sitebill\Http\Controllers\SitebillGridController');
        $this->app->register('Sitebill\Http\Controllers\SitebillGridController');


        $this->app->register(VoyagerEventServiceProvider::class);
        $this->app->register(ImageServiceProvider::class);
        $this->app->register(VoyagerDummyServiceProvider::class);
        $this->app->register(VoyagerHooksServiceProvider::class);
        $this->app->register(DoctrineSupportServiceProvider::class);

        $loader = AliasLoader::getInstance();
        $loader->alias('Voyager', VoyagerFacade::class);

        $this->app->singleton('voyager', function () {
            return new Voyager();
        });

        $this->app->singleton('VoyagerGuard', function () {
            return config('auth.defaults.guard', 'web');
        });

        $this->loadHelpers();

        $this->registerAlertComponents();
        $this->registerFormFields();

        $this->registerConfigs();

        if ($this->app->runningInConsole()) {
            $this->registerPublishableResources();
            $this->registerConsoleCommands();
        }

        if (!$this->app->runningInConsole() || config('app.env') == 'testing') {
            $this->registerAppCommands();
        }
        */
    }

    /**
     * The route macro allows developers to generate the routes for a CrudController,
     * for all operations, using a simple syntax: Route::crud().
     *
     * It will go to the given CrudController and get the setupRoutes() method on it.
     */
    private function addRouteMacro()
    {
        Route::macro('crud', function ($name, $controller) {
            // put together the route name prefix,
            // as passed to the Route::group() statements
            $routeName = '';
            if ($this->hasGroupStack()) {
                foreach ($this->getGroupStack() as $key => $groupStack) {
                    if (isset($groupStack['name'])) {
                        if (is_array($groupStack['name'])) {
                            $routeName = implode('', $groupStack['name']);
                        } else {
                            $routeName = $groupStack['name'];
                        }
                    }
                }
            }
            // add the name of the current entity to the route name prefix
            // the result will be the current route name (not ending in dot)
            $routeName .= $name;

            // get an instance of the controller
            if ($this->hasGroupStack()) {
                $groupStack = $this->getGroupStack();
                $groupNamespace = $groupStack && isset(end($groupStack)['namespace']) ? end($groupStack)['namespace'].'\\' : '';
            } else {
                $groupNamespace = '';
            }
            $namespacedController = $groupNamespace.$controller;
            $controllerInstance = App::make($namespacedController);

            return $controllerInstance->setupRoutes($name, $routeName, $controller);
        });
    }


    /**
     * Bootstrap the application services.
     *
     * @param \Illuminate\Routing\Router $router
     */
    public function boot(Router $router, Dispatcher $event)
    {
        $this->loadViewsWithFallbacks();
        $this->loadTranslationsFrom(realpath(__DIR__.'/resources/lang'), 'backpack');
        $this->loadConfigs();

        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sitebill');
        $this->loadRoutesFrom(__DIR__.'/../routes/routes.php');
    }

    public function loadViewsWithFallbacks()
    {
        $customBaseFolder = resource_path('views/vendor/backpack/base');
        $customCrudFolder = resource_path('views/vendor/backpack/crud');

        // - first the published/overwritten views (in case they have any changes)
        if (file_exists($customBaseFolder)) {
            $this->loadViewsFrom($customBaseFolder, 'backpack');
        }
        if (file_exists($customCrudFolder)) {
            $this->loadViewsFrom($customCrudFolder, 'crud');
        }
        // - then the stock views that come with the package, in case a published view might be missing
        $this->loadViewsFrom(realpath(__DIR__.'/resources/views/base'), 'backpack');
        $this->loadViewsFrom(realpath(__DIR__.'/resources/views/crud'), 'crud');
    }

    public function loadConfigs()
    {
        // use the vendor configuration file as fallback
        $this->mergeConfigFrom(__DIR__.'/config/backpack/crud.php', 'backpack.crud');
        $this->mergeConfigFrom(__DIR__.'/config/backpack/base.php', 'backpack.base');

        // add the root disk to filesystem configuration
        app()->config['filesystems.disks.'.config('backpack.base.root_disk_name')] = [
            'driver' => 'local',
            'root'   => base_path(),
        ];

        /*
         * Backpack login differs from the standard Laravel login.
         * As such, Backpack uses its own authentication provider, password broker and guard.
         *
         * THe process below adds those configuration values on top of whatever is in config/auth.php.
         * Developers can overwrite the backpack provider, password broker or guard by adding a
         * provider/broker/guard with the "backpack" name inside their config/auth.php file.
         * Or they can use another provider/broker/guard entirely, by changing the corresponding
         * value inside config/backpack/base.php
         */

        // add the backpack_users authentication provider to the configuration
        app()->config['auth.providers'] = app()->config['auth.providers'] +
            [
                'backpack' => [
                    'driver'  => 'eloquent',
                    'model'   => config('backpack.base.user_model_fqn'),
                ],
            ];

        // add the backpack_users password broker to the configuration
        app()->config['auth.passwords'] = app()->config['auth.passwords'] +
            [
                'backpack' => [
                    'provider'  => 'backpack',
                    'table'     => 'password_resets',
                    'expire'    => 60,
                ],
            ];

        // add the backpack_users guard to the configuration
        app()->config['auth.guards'] = app()->config['auth.guards'] +
            [
                'backpack' => [
                    'driver'   => 'session',
                    'provider' => 'backpack',
                ],
            ];
    }



    /**
     * Load the Backpack helper methods, for convenience.
     */
    public function loadHelpers()
    {
        require_once __DIR__.'/helpers.php';
    }
}
