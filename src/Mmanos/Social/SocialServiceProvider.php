<?php namespace Mmanos\Social;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Config;

class SocialServiceProvider extends ServiceProvider
{
	/**
	 * Indicates if loading of the provider is deferred.
	 *
	 * @var bool
	 */
	protected $defer = false;

	/**
	 * Bootstrap the application events.
	 *
	 * @return void
	 */
	public function boot()
	{
		$this->package('mmanos/laravel-social');

		if ($route = Config::get('laravel-social::route')) {
			Route::get($route . '/login/{provider}', array(
				'as'   => 'social-login',
				'uses' => 'Mmanos\Social\SocialController@getLogin',
			));
			Route::get($route . '/connect/{provider}', array(
				'as'   => 'social-connect',
				'uses' => 'Mmanos\Social\SocialController@getConnect',
			));
			Route::controller($route, 'Mmanos\Social\SocialController');
		}
	}

	/**
	 * Register the service provider.
	 *
	 * @return void
	 */
	public function register()
	{
		$this->app->bindShared('social', function ($app) {
			return new \Mmanos\Social\Social;
		});
	}

	/**
	 * Get the services provided by the provider.
	 *
	 * @return array
	 */
	public function provides()
	{
		return array();
	}
}
