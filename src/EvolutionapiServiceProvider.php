<?php namespace EvolutionCMS\Evolutionapi;

use EvolutionCMS\ServiceProvider;

class EvolutionapiServiceProvider extends ServiceProvider
{
    protected $namespace = 'evolutionapi';
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {

    }

    public function boot()
    {
        $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    }
}