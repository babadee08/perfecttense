<?php

namespace Damilare\PerfectTenseClient;

use Illuminate\Support\ServiceProvider;
use test\Mockery\HasUnknownClassAsTypeHintOnMethod;

class PTClientServiceProvider extends ServiceProvider
{

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $source = realpath($raw = __DIR__ . '/../config/perfecttense.php') ?: $raw;

        $this->mergeConfigFrom($source, 'perfect-tense');
    }

    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton(PTClient::class, function ($app) {

            return new PTClient([
                'appKey' => $app->config->get('perfect-tense.app-key')
            ]);

        });
    }
}
