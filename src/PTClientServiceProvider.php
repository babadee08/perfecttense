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

            $api_key = $app->config->get('perfect-tense.api-key');
            $app_name = $app->config->get('perfect-tense.app-name');
            $email = $app->config->get('perfect-tense.email');
            $site_url = $app->config->get('perfect-tense.site-url');

            $response = PTClientHelper::pt_generate_app_key($api_key, $app_name, 'description: Here is the Description', $email, $site_url);

            $appKey = $response['key'];

            return new PTClient([
                'appKey' => $appKey
            ]);
        });

        /*$this->app->singleton(PerfectTense::class, function ($app) {
            //return new PerfectTense($app->config->get('perfect-tense.app-name'));
            return new PerfectTense($app->config->get('perfect-tense.app-name'));
        });*/
    }
}
