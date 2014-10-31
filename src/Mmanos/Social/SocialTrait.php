<?php namespace Mmanos\Social;

trait SocialTrait
{
	/**
	 * Return the social providers associated with this user.
	 *
	 * @var \Collection
	 */
	public function providers()
	{
		return $this->hasMany('Mmanos\Social\Provider');
	}

	/**
	 * Return the reqeusted social provider associated with this user.
	 *
	 * @param string $name
	 *
	 * @var Provider
	 */
	public function provider($name)
	{
		$providers = $this->providers->filter(function ($provider) use ($name) {
			return strtolower($provider->provider) == strtolower($name);
		});

		return $providers->first();
	}

	/**
	 * Return true if this user has connected to the requested social provider.
	 * False, otherwise.
	 *
	 * @param string $name
	 *
	 * @var boolean
	 */
	public function hasProvider($name)
	{
		return $this->provider($name) ? true : false;
	}
}
