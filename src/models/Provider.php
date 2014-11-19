<?php namespace Mmanos\Social;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Config;
use Illuminate\Database\Eloquent\Model;
use OAuth\OAuth1\Token\StdOAuth1Token;
use OAuth\OAuth2\Token\StdOAuth2Token;

class Provider extends Model
{
	protected $hidden = array('user_id', 'updated_at');
	protected $guarded = array('id');

	public function getTable()
	{
		return Config::get('laravel-social::table', 'user_providers');
	}

	public function getAccessTokenAttribute($value)
	{
		return json_decode(Crypt::decrypt($value), true);
	}

	public function setAccessTokenAttribute($value)
	{
		$this->attributes['access_token'] = Crypt::encrypt(json_encode($value));
	}

	public function request($path, $method = 'GET', $body = null, array $extra_headers = array())
	{
		$access_token = $this->access_token;

		$service = Facades\Social::service($this->provider);

		if (2 === Facades\Social::oauthSpec($this->provider)) {
			$token = new StdOAuth2Token;
			$token->setAccessToken(array_get($access_token, 'token'));
		}
		else {
			$token = new StdOAuth1Token;
			$token->setAccessToken(array_get($access_token, 'token'));
			$token->setAccessTokenSecret(array_get($access_token, 'secret'));
		}

		$service->getStorage()->storeAccessToken(ucfirst($this->provider), $token);

		try {
			return $service->request($path, $method, $body, $extra_headers);
		} catch (\OAuth\Common\Http\Exception\TokenResponseException $e) {
			if ($this->refreshAccessToken()) {
				return $service->request($path, $method, $body, $extra_headers);
			}

			throw $e;
		}
	}

	public function refreshAccessToken()
	{
		$access_token = $this->access_token;

		$service = Facades\Social::service($this->provider);

		if (2 === Facades\Social::oauthSpec($this->provider)) {
			$token = new StdOAuth2Token;
			$token->setAccessToken(array_get($access_token, 'token'));
			$token->setRefreshToken(array_get($access_token, 'refresh_token'));
		}
		else {
			$token = new StdOAuth1Token;
			$token->setAccessToken(array_get($access_token, 'token'));
			$token->setAccessTokenSecret(array_get($access_token, 'secret'));
			$token->setRefreshToken(array_get($access_token, 'refresh_token'));
		}

		$service->getStorage()->storeAccessToken(ucfirst($this->provider), $token);

		try {
			$new_token = $service->refreshAccessToken($token);
		} catch (\Exception $e) {
			return false;
		}

		if (!$new_token->getAccessToken()) {
			return false;
		}

		$access_token['token'] = $new_token->getAccessToken();
		if ($new_token->getEndOfLife()) {
			$access_token['end_of_life'] = $new_token->getEndOfLife();
		}
		if ($new_token->getExtraParams()) {
			$access_token['extra_params'] = $new_token->getExtraParams();
		}
		if (2 !== Facades\Social::oauthSpec($this->provider) && $new_token->getAccessTokenSecret()) {
			$access_token['secret'] = $new_token->getAccessTokenSecret();
		}

		$this->access_token = $access_token;
		$this->save();

		return true;
	}
}
