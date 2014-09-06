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
		
		$service = Facade\Social::service($this->provider);
		
		if (2 === Facade\Social::oauthSpec($this->provider)) {
			$token = new StdOAuth2Token;
			$token->setAccessToken(array_get($access_token, 'token'));
		}
		else {
			$token = new StdOAuth1Token;
			$token->setAccessToken(array_get($access_token, 'token'));
			$token->setAccessTokenSecret(array_get($access_token, 'secret'));
		}
		
		$service->getStorage()->storeAccessToken(ucfirst($this->provider), $token);
		
		return $service->request($path, $method, $body, $extra_headers);
	}
}
