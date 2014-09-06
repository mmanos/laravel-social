<?php namespace Mmanos\Social;

use Exception;
use OAuth\ServiceFactory;
use OAuth\Common\Storage\Session;
use OAuth\Common\Consumer\Credentials;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Config;

class Social
{
	/**
	 * The Service Factory instance.
	 *
	 * @var \OAuth\ServiceFactory
	 */
	protected $factory;
	
	/**
	 * The Service Storage instance.
	 *
	 * @var \OAuth\Common\Storage\TokenStorageInterface
	 */
	protected $storage;
	
	/**
	 * Create a new Social instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		$this->factory = new ServiceFactory;
		$this->storage = new Session;
	}
	
	/**
	 * Return an instance of the requested service.
	 *
	 * @param string $provider
	 * @param string $url
	 * @param array  $scope
	 * 
	 * @return \OAuth\Common\Service\AbstractService
	 * @throws \Exception
	 */
	public function service($provider, $url = null, $scope = null)
	{
		$info = Config::get('laravel-social::providers.' . strtolower($provider));
		
		if (empty($info) || !is_array($info)) {
			throw new Exception('Missing configuration details for Social service: ' . $provider);
		}
		
		$client_id     = array_get($info, 'client_id');
		$client_secret = array_get($info, 'client_secret');
		$scope         = is_null($scope) ? array_get($info, 'scope') : $scope;
		
		if (empty($client_id) || empty($client_secret)) {
			throw new Exception('Missing client id/secret for Social service: ' . $provider);
		}
		
		return $this->factory->createService(
			ucfirst($provider),
			new Credentials($client_id, $client_secret, $url ?: URL::full()),
			$this->storage,
			$scope
		);
	}
	
	/**
	 * Return the OAuth spec used by the given service provider.
	 *
	 * @param string $provider
	 * 
	 * @return integer
	 */
	public function oauthSpec($provider)
	{
		$service = $this->service($provider);
		
		if (false !== stristr(get_class($service), 'OAuth1')) {
			return 1;
		}
		else if (false !== stristr(get_class($service), 'OAuth2')) {
			return 2;
		}
		
		return null;
	}
}
