<?php namespace Mmanos\Social;

use Closure;
use Exception;
use Mmanos\Social\Provider;
use Mmanos\Social\Facades\Social;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;

/**
 * Social providers controller.
 * 
 * @author Mark Manos
 */
class SocialController extends Controller
{
	/**
	 * Login action.
	 *
	 * @param string $provider
	 * 
	 * @return mixed
	 */
	public function getLogin($provider = null)
	{
		if (empty($provider)) {
			App::abort(404);
		}
		
		$referer = Request::header('referer', '/');
		$referer_parts = parse_url($referer);
		$onerror = array_get($referer_parts, 'path');
		if (array_get($referer_parts, 'query')) {
			$onerror .= '?' . array_get($referer_parts, 'query');
		}
		
		Session::put('mmanos.social.onsuccess', Input::get('onsuccess', '/'));
		Session::put('mmanos.social.onerror', Input::get('onerror', $onerror));
		
		if (Auth::check()) {
			return Redirect::to(Session::get('mmanos.social.onsuccess', '/'));
		}
		
		Session::forget('mmanos.social.pending');
		Session::forget('mmanos.social.failed_fields');
		
		if (Input::get('denied') || Input::get('error')) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem logging in to your account (1).'
				);
		}
		
		$provider = ucfirst($provider);
		
		try {
			$service = Social::service($provider);
			
			if (Config::get('laravel-social::providers.' . strtolower($provider) . '.offline')) {
				$service->setAccessType('offline');
			}
		} catch (Exception $e) {
			App::abort(404);
		}
		
		if (2 === Social::oauthSpec($provider)) {
			return $this->oauth2Login($provider, $service);
		}
		else {
			return $this->oauth1Login($provider, $service);
		}
	}
	
	/**
	 * Login to an OAuth2 service.
	 *
	 * @param string                                $provider
	 * @param \OAuth\Common\Service\AbstractService $service
	 * 
	 * @return Redirect
	 */
	protected function oauth2Login($provider, $service)
	{
		if ($code = Input::get('code')) {
			try {
				$token = $service->requestAccessToken($code);
			} catch (Exception $e) {
				return Redirect::to(Session::get('mmanos.social.onerror', '/'))
					->with(
						Config::get('laravel-social::error_flash_var'),
						'There was a problem logging in to your account (2).'
					);
			}
			
			return $this->processLogin($provider, $service, array(
				'token' => $token->getAccessToken(),
			));
		}
		
		return Redirect::to((string) $service->getAuthorizationUri());
	}
	
	/**
	 * Login to an OAuth1 consumer.
	 *
	 * @param string                                $provider
	 * @param \OAuth\Common\Service\AbstractService $service
	 * 
	 * @return Redirect
	 */
	protected function oauth1Login($provider, $service)
	{
		if ($oauth_token = Input::get('oauth_token')) {
			try {
				$token = $service->requestAccessToken(
					$oauth_token,
					Input::get('oauth_verifier'),
					$service->getStorage()->retrieveAccessToken($provider)->getRequestTokenSecret()
				);
			} catch (Exception $e) {
				return Redirect::to(Session::get('mmanos.social.onerror', '/'))
					->with(
						Config::get('laravel-social::error_flash_var'),
						'There was a problem logging in to your account (3).'
					);
			}
			
			return $this->processLogin($provider, $service, array(
				'token'  => $token->getAccessToken(),
				'secret' => $token->getAccessTokenSecret(),
			));
		}
		
		try {
			// Extra request needed for oauth1 to get a request token.
			$token = $service->requestRequestToken();
		} catch (Exception $e) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
						Config::get('laravel-social::error_flash_var'),
						'There was a problem logging in to your account (4).'
					);
		}
		
		return Redirect::to((string) $service->getAuthorizationUri(array(
			'oauth_token' => $token->getRequestToken(),
		)));
	}
	
	/**
	 * Process the response from a provider login attempt.
	 *
	 * @param string                                $provider
	 * @param \OAuth\Common\Service\AbstractService $service
	 * @param array                                 $access_token
	 * 
	 * @return Redirect
	 */
	protected function processLogin($provider, $service, $access_token)
	{
		$user_info_callback = Config::get(
			'laravel-social::providers.' . strtolower($provider) . '.fetch_user_info'
		);
		
		if (empty($user_info_callback) || !$user_info_callback instanceof Closure) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem logging in to your account (5).'
				);
		}
		
		try {
			$user_info = $user_info_callback($service);
		} catch (Exception $e) {}
		
		if (empty($user_info) || !is_array($user_info)) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem logging in to your account (6).'
				);
		}
		
		if (empty($user_info['id'])) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem logging in to your account (7).'
				);
		}
		
		$provider_id = array_get($user_info, 'id');
		
		$user_provider = Provider::where('provider', strtolower($provider))
			->where('provider_id', $provider_id)
			->first();
		
		if ($user_provider) {
			Auth::loginUsingId($user_provider->user_id);
			return Redirect::to(Session::get('mmanos.social.onsuccess', '/'));
		}
		
		if ($user_validation = Config::get('laravel-social::user_validation')) {
			if ($user_validation instanceof Closure) {
				$validator = $user_validation($user_info);
			}
			else {
				$validator = Validator::make($user_info, (array) $user_validation);
			}
			
			if ($validator && $validator->fails()) {
				Session::put('mmanos.social.pending', array(
					'provider'     => $provider,
					'provider_id'  => $provider_id,
					'user_info'    => $user_info,
					'access_token' => $access_token,
				));
				Session::put('mmanos.social.failed_fields', array_keys($validator->failed()));
				
				return Redirect::action(Config::get('laravel-social::user_failed_validation_redirect'))
					->withErrors($validator);
			}
		}
		
		$create_user_callback = Config::get('laravel-social::create_user');
		if (empty($create_user_callback) || !$create_user_callback instanceof Closure) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem logging in to your account (8).'
				);
		}
		
		$user_id = $create_user_callback($user_info);
		if (!$user_id || !is_numeric($user_id) || $user_id <= 0) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem logging in to your account (9).'
				);
		}
		
		$this->linkProvider($user_id, $provider, $provider_id, $access_token);
		
		Auth::loginUsingId($user_id);
		return Redirect::to(Session::get('mmanos.social.onsuccess', '/'));
	}
	
	/**
	 * Complete login action.
	 *
	 * @return View
	 */
	public function getComplete()
	{
		$user_data = Session::get('mmanos.social.pending');
		if (empty($user_data) || !is_array($user_data)) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem logging in to your account (10).'
				);
		}
		
		$failed_fields = Session::get('mmanos.social.failed_fields');
		if (empty($failed_fields) || !is_array($failed_fields)) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem logging in to your account (11).'
				);
		}
		
		return View::make('laravel-social::social.complete', array(
			'failed_fields' => $failed_fields,
			'info'          => array_get($user_data, 'user_info'),
		));
	}
	
	/**
	 * Handle the complete login form submission.
	 *
	 * @return Redirect
	 */
	public function postComplete()
	{
		$user_data = Session::get('mmanos.social.pending');
		if (empty($user_data) || !is_array($user_data)) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem logging in to your account (12).'
				);
		}
		
		$user_info = array_merge(array_get($user_data, 'user_info'), Input::all());
		
		$user_validation = Config::get('laravel-social::user_validation');
		if ($user_validation instanceof Closure) {
			$validator = $user_validation($user_info);
		}
		else {
			$validator = Validator::make($user_info, (array) $user_validation);
		}
		
		if ($validator->fails()) {
			return Redirect::action('Mmanos\Social\SocialController@getComplete')
				->withInput()
				->withErrors($validator);
		}
		
		$create_user_callback = Config::get('laravel-social::create_user');
		if (empty($create_user_callback) || !$create_user_callback instanceof Closure) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem logging in to your account (13).'
				);
		}
		
		$user_id = $create_user_callback($user_info);
		if (!$user_id || !is_numeric($user_id) || $user_id <= 0) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem logging in to your account (14).'
				);
		}
		
		$provider = array_get($user_data, 'provider');
		$provider_id = array_get($user_data, 'provider_id');
		$access_token = array_get($user_data, 'access_token');
		
		$this->linkProvider($user_id, $provider, $provider_id, $access_token);
		
		Session::forget('mmanos.social.pending');
		Session::forget('mmanos.social.failed_fields');
		
		Auth::loginUsingId($user_id);
		return Redirect::to(Session::get('mmanos.social.onsuccess', '/'));
	}
	
	/**
	 * Connect action.
	 *
	 * @param string $provider
	 * 
	 * @return mixed
	 */
	public function getConnect($provider = null)
	{
		if (empty($provider)) {
			App::abort(404);
		}
		
		$referer = Request::header('referer', '/');
		$referer_parts = parse_url($referer);
		$onboth = array_get($referer_parts, 'path');
		if (array_get($referer_parts, 'query')) {
			$onboth .= '?' . array_get($referer_parts, 'query');
		}
		
		Session::put('mmanos.social.onsuccess', Input::get('onsuccess', $onboth));
		Session::put('mmanos.social.onerror', Input::get('onerror', $onboth));
		
		if (!Auth::check()) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem connecting your account (1).'
				);
		}
		
		if (Input::get('denied') || Input::get('error')) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem connecting your account (2).'
				);
		}
		
		$provider = ucfirst($provider);
		
		try {
			$service = Social::service($provider);
			
			if (Config::get('laravel-social::providers.' . strtolower($provider) . '.offline')) {
				$service->setAccessType('offline');
			}
		} catch (Exception $e) {
			App::abort(404);
		}
		
		if (2 === Social::oauthSpec($provider)) {
			return $this->oauth2Connect($provider, $service);
		}
		else {
			return $this->oauth1Connect($provider, $service);
		}
	}
	
	/**
	 * Login to an OAuth2 service.
	 *
	 * @param string                                $provider
	 * @param \OAuth\Common\Service\AbstractService $service
	 * 
	 * @return Redirect
	 */
	protected function oauth2Connect($provider, $service)
	{
		if ($code = Input::get('code')) {
			try {
				$token = $service->requestAccessToken($code);
			} catch (Exception $e) {
				return Redirect::to(Session::get('mmanos.social.onerror', '/'))
					->with(
						Config::get('laravel-social::error_flash_var'),
						'There was a problem connecting your account (3).'
					);
			}
			
			return $this->processConnect($provider, $service, array(
				'token' => $token->getAccessToken(),
			));
		}
		
		return Redirect::to((string) $service->getAuthorizationUri());
	}
	
	/**
	 * Login to an OAuth1 consumer.
	 *
	 * @param string                                $provider
	 * @param \OAuth\Common\Service\AbstractService $service
	 * 
	 * @return Redirect
	 */
	protected function oauth1Connect($provider, $service)
	{
		if ($oauth_token = Input::get('oauth_token')) {
			try {
				$token = $service->requestAccessToken(
					$oauth_token,
					Input::get('oauth_verifier'),
					$service->getStorage()->retrieveAccessToken($provider)->getRequestTokenSecret()
				);
			} catch (Exception $e) {
				return Redirect::to(Session::get('mmanos.social.onerror', '/'))
					->with(
						Config::get('laravel-social::error_flash_var'),
						'There was a problem connecting your account (4).'
					);
			}
			
			return $this->processConnect($provider, $service, array(
				'token'  => $token->getAccessToken(),
				'secret' => $token->getAccessTokenSecret(),
			));
		}
		
		try {
			// Extra request needed for oauth1 to get a request token.
			$token = $service->requestRequestToken();
		} catch (Exception $e) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
						Config::get('laravel-social::error_flash_var'),
						'There was a problem connecting your account (5).'
					);
		}
		
		return Redirect::to((string) $service->getAuthorizationUri(array(
			'oauth_token' => $token->getRequestToken(),
		)));
	}
	
	/**
	 * Process the response from a provider connect attempt.
	 *
	 * @param string                                $provider
	 * @param \OAuth\Common\Service\AbstractService $service
	 * @param array                                 $access_token
	 * 
	 * @return Redirect
	 */
	protected function processConnect($provider, $service, $access_token)
	{
		$user_info_callback = Config::get(
			'laravel-social::providers.' . strtolower($provider) . '.fetch_user_info'
		);
		
		if (empty($user_info_callback) || !$user_info_callback instanceof Closure) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem connecting your account (6).'
				);
		}
		
		try {
			$user_info = $user_info_callback($service);
		} catch (Exception $e) {}
		
		if (empty($user_info) || !is_array($user_info)) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem connecting your account (7).'
				);
		}
		
		if (empty($user_info['id'])) {
			return Redirect::to(Session::get('mmanos.social.onerror', '/'))
				->with(
					Config::get('laravel-social::error_flash_var'),
					'There was a problem connecting your account (8).'
				);
		}
		
		$provider_id = array_get($user_info, 'id');
		
		$user_provider = Provider::where('provider', strtolower($provider))
			->where('provider_id', $provider_id)
			->first();
		
		if ($user_provider) {
			if ($user_provider->user_id != Auth::id()) {
				return Redirect::to(Session::get('mmanos.social.onerror', '/'))
					->with(
						Config::get('laravel-social::error_flash_var'),
						'There was a problem connecting your account (9).'
					);
			}
			
			$user_provider->access_token = $access_token;
			$user_provider->save();
		}
		else {
			$this->linkProvider(Auth::id(), $provider, $provider_id, $access_token);
		}
		
		return Redirect::to(Session::get('mmanos.social.onsuccess', '/'))
			->with(
				Config::get('laravel-social::success_flash_var'),
				'You have successfully connected your account.'
			);
	}
	
	/**
	 * Link the give user to the given provider.
	 *
	 * @param integer $user_id
	 * @param string  $provider
	 * @param integer $provider_id
	 * @param array   $access_token
	 * 
	 * @return Provider
	 */
	protected function linkProvider($user_id, $provider, $provider_id, $access_token)
	{
		$user_provider = new Provider;
		$user_provider->user_id = $user_id;
		$user_provider->provider = strtolower($provider);
		$user_provider->provider_id = $provider_id;
		$user_provider->access_token = $access_token;
		$user_provider->save();
		
		return $user_provider;
	}
}
