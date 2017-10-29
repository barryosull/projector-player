<?php namespace App\Providers;

use App\Services\EventStore;
use App\Services\ProjectorLoader;
use App\Services\ProjectorPositionRepository;
use App\Services\ProjectorRegisterer;
use Illuminate\Container\Container;
use Infrastructure\App\Services\FakeEventStore;
use Infrastructure\App\Services\InMemoryProjectorPositionRepository;
use Infrastructure\App\Services\LaravelProjectorLoader;
use Tests\Projectors\RunFromLaunch;
use Tests\Projectors\RunFromStart;
use Tests\Projectors\RunOnce;

class AppProvider
{
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function boot()
    {
        $this->bind();
        $this->loadConfigs();
    }

    public function bind()
    {
        $this->container->singleton(
            Container::class,
                function() {
                    return $this->container;
                }
        );

        $this->container->singleton(
            ProjectorPositionRepository::class,
                InMemoryProjectorPositionRepository::class
        );

        $this->container->bind(
            ProjectorLoader::class,
                LaravelProjectorLoader::class
        );

        $this->container->bind(
            EventStore::class,
                FakeEventStore::class
        );
    }

    public function loadConfigs()
    {
        /** @var ProjectorRegisterer $registerer */
        $registerer = $this->container->make(ProjectorRegisterer::class);
        $registerer->register([
            RunFromLaunch::class,
            RunFromStart::class,
            RunOnce::class
        ]);
    }
}