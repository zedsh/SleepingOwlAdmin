<?php

namespace SleepingOwl\Admin\Providers;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\AliasLoader;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use SleepingOwl\Admin\AliasBinder;
use SleepingOwl\Admin\Contracts\Display\TableHeaderColumnInterface;
use SleepingOwl\Admin\Contracts\FormButtonsInterface;
use SleepingOwl\Admin\Contracts\Navigation\NavigationInterface;
use SleepingOwl\Admin\Contracts\RepositoryInterface;
use SleepingOwl\Admin\Contracts\Template\MetaInterface;
use SleepingOwl\Admin\Contracts\Wysiwyg\WysiwygMangerInterface;
use SleepingOwl\Admin\Exceptions\TemplateException;
use SleepingOwl\Admin\Model\ModelConfiguration;
use SleepingOwl\Admin\Model\ModelConfigurationManager;
use SleepingOwl\Admin\Templates\Breadcrumbs;
use Symfony\Component\Finder\Finder;

class AdminServiceProvider extends ServiceProvider
{
    protected $directory;

    public function register()
    {
        $this->registerAliases();
        $this->initializeNavigation();
        $this->initializeAssets();

        $this->app->singleton('sleeping_owl.template', function ($app) {
            if (! class_exists($class = $this->getConfig('template'))) {
                throw new TemplateException("Template class [{$class}] not found");
            }

            return $app->make($class);
        });

        $this->app->alias('sleeping_owl.template', \SleepingOwl\Admin\Contracts\Template\TemplateInterface::class);

        $this->app->singleton('sleeping_owl', function ($app) {
            return new \SleepingOwl\Admin\Admin($app['sleeping_owl.template']);
        });

        $this->app->alias('sleeping_owl', \SleepingOwl\Admin\Contracts\AdminInterface::class);

        $this->registerWysiwyg();

        $this->app->booted(function () {
            $this->registerCustomRoutes();
            $this->registerDefaultRoutes();
            $this->registerNavigationFile();

            $this->app['sleeping_owl']->initialize();
        });

        ModelConfigurationManager::setEventDispatcher($this->app['events']);
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    protected function getConfig($key)
    {
        return $this->app['config']->get('sleeping_owl.'.$key);
    }

    /**
     * @param string $path
     *
     * @return string
     */
    protected function getBootstrapPath($path = null)
    {
        if (! is_null($path)) {
            $path = DIRECTORY_SEPARATOR.$path;
        }

        return $this->getConfig('bootstrapDirectory').$path;
    }

    public function boot()
    {
        $this->registerBootstrap();

        $this->registerRoutes(function (Router $route) {
            $route->group(['as' => 'admin.', 'namespace' => 'SleepingOwl\Admin\Http\Controllers'], function ($route) {
                $route->get('assets/admin.scripts', [
                    'as'   => 'scripts',
                    'uses' => 'AdminController@getScripts',
                ]);
            });
        });
    }

    protected function initializeNavigation()
    {
        $this->app->bind(TableHeaderColumnInterface::class, \SleepingOwl\Admin\Display\TableHeaderColumn::class);
        $this->app->bind(RepositoryInterface::class, \SleepingOwl\Admin\Repository\BaseRepository::class);
        $this->app->bind(FormButtonsInterface::class, \SleepingOwl\Admin\Form\FormButtons::class);

        $this->app->bind(\KodiComponents\Navigation\Contracts\PageInterface::class, \SleepingOwl\Admin\Navigation\Page::class);
        $this->app->bind(\KodiComponents\Navigation\Contracts\BadgeInterface::class, \SleepingOwl\Admin\Navigation\Badge::class);

        $this->app->singleton('sleeping_owl.navigation', function () {
            return new \SleepingOwl\Admin\Navigation();
        });

        $this->app->alias('sleeping_owl.navigation', NavigationInterface::class);
    }

    protected function initializeAssets()
    {
        $this->app->singleton('assets.packages', function ($app) {
            return new \KodiCMS\Assets\PackageManager();
        });

        $this->app->singleton('sleeping_owl.assets', function ($app) {
            return new \SleepingOwl\Admin\Templates\Meta(
                new \KodiCMS\Assets\Assets(
                    $app['assets.packages']
                )
            );
        });

        $this->app->alias('sleeping_owl.assets', MetaInterface::class);
    }

    protected function registerWysiwyg()
    {
        $this->app->singleton('sleeping_owl.wysiwyg', function ($app) {
            return new \SleepingOwl\Admin\Wysiwyg\Manager(
                $app['sleeping_owl.assets']
            );
        });

        $this->app->alias('sleeping_owl.wysiwyg', WysiwygMangerInterface::class);
    }

    /**
     * @return array
     */
    protected function registerBootstrap()
    {
        $directory = $this->getBootstrapPath();

        if (! is_dir($directory)) {
            return;
        }

        $files = $files = Finder::create()
            ->files()
            ->name('/^.+\.php$/')
            ->notName('routes.php')
            ->notName('navigation.php')
            ->in($directory)->sort(function ($a) {
                return $a->getFilename() != 'bootstrap.php';
            });

        foreach ($files as $file) {
            require $file;
        }
    }

    protected function registerAliases()
    {
        AliasLoader::getInstance(config('sleeping_owl.aliases', []));
    }

    protected function registerCustomRoutes()
    {
        if (file_exists($file = $this->getBootstrapPath('routes.php'))) {
            $this->registerRoutes(function (Router $route) use ($file) {
                require $file;
            });
        }
    }

    protected function registerDefaultRoutes()
    {
        $this->registerRoutes(function (Router $router) {
            $router->pattern('adminModelId', '[a-zA-Z0-9_-]+');

            $aliases = $this->app['sleeping_owl']->modelAliases();

            if (count($aliases) > 0) {
                $router->pattern('adminModel', implode('|', $aliases));

                $this->app['router']->bind('adminModel', function ($model, \Illuminate\Routing\Route $route) use ($aliases) {
                    $class = array_search($model, $aliases);

                    if ($class === false) {
                        throw new ModelNotFoundException;
                    }

                    /** @var ModelConfiguration $model */
                    $model = $this->app['sleeping_owl']->getModel($class);

                    if ($model->hasCustomControllerClass()) {
                        list($controller, $action) = explode('@', $route->getActionName(), 2);

                        $newController = $model->getControllerClass().'@'.$action;

                        $route->uses($newController);
                    }

                    return $model;
                });
            }

            if (file_exists($routesFile = __DIR__.'/../Http/routes.php')) {
                require $routesFile;
            }

            foreach (AliasBinder::routes() as $route) {
                call_user_func($route, $router);
            }
        });
    }

    /**
     * @param \Closure $callback
     */
    protected function registerRoutes(\Closure $callback)
    {
        $this->app['router']->group([
            'prefix' => $this->getConfig('url_prefix'),
            'middleware' => $this->getConfig('middleware'),
        ], function ($route) use ($callback) {
            call_user_func($callback, $route);
        });
    }

    protected function registerNavigationFile()
    {
        if (file_exists($navigation = $this->getBootstrapPath('navigation.php'))) {
            $items = include $navigation;

            if (is_array($items)) {
                $this->app['sleeping_owl.navigation']->setFromArray($items);
            }
        }
    }
}
