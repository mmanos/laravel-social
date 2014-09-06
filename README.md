# Social Login Package for Laravel 4

This package is a simple Laravel 4 service provider for [Lusitanian/PHPoAuthLib](https://github.com/Lusitanian/PHPoAuthLib) which provides oAuth support in PHP 5.3+ and is very easy to integrate with any project which requires an oAuth client.

In addition, you may take advantage of the optional controller and model to make very easy to:

* Log in with a social provider
* Connect an existing user record with a social provider
* Perform requests against the social provider API with each user's unique access token

## Supported Services

See the documentation for Lusitanian/PHPoAuthLib for the [list of supported services](https://github.com/Lusitanian/PHPoAuthLib#included-service-implementations).

## Installation Via Composer

Add this to you composer.json file, in the require object:

```javascript
"mmanos/laravel-social": "dev-master"
```

After that, run composer install to install the package.

Add the service provider to `app/config/app.php`, within the `providers` array.

```php
'providers' => array(
	// ...
	'Mmanos\Social\SocialServiceProvider',
)
```

Add a class alias to `app/config/app.php`, within the `aliases` array.

```php
'aliases' => array(
	// ...
	'Social' => 'Mmanos\Social\Facades\Social',
)
```

## Configuration

Publish the default config file to your application so you can make modifications.

```console
$ php artisan config:publish mmanos/laravel-social
```

Run the database migrations for this package.

```console
$ php artisan migrate --package="mmanos/laravel-social"
```

Add your service provider credentials to the published config file: `app/config/packages/mmanos/laravel-social/config.php`

## Basic Usage

Obtain a service class object for a provider.

```php
$service = Social::service('facebook');
```

Optionally, add a second parameter with the URL which the service needs to redirect to, otherwise it will redirect to the current URL.

```php
$service = Social::service('facebook', 'http://example.com');
```

Redirect the user to the oAuth log in page for a provider.

```php
return Redirect::to((string) $service->getAuthorizationUri());
```

For examples of how to integrate with a specific provider, [see here](https://github.com/Lusitanian/PHPoAuthLib/tree/master/examples).

## Using The Provided Controller And Model

For added convenience, use the built-in controller and model to easily log in or connect with a social provider account.

#### Requirements

This implementation is fairly opinionated and assumes that you want to allow your users to log in or sign up seamlessly with their existing social provider account and associate that social provider account with an existing user record.

It has these requirements:

* You have an existing user record with a numeric primary key
* You are using the Laravel authentication system
* You have the Laravel session system enabled
* You have an app key defined in your config/app.php file so the Crypt class can be used to encrypt the access tokens

#### Migration

Run the database migrations for this package. This will create a `user_providers` table.

```console
$ php artisan migrate --package="mmanos/laravel-social"
```

> **Note:** You can change the default table name used to store the user provider information by changing the value of the `table` key in the config file.

#### Model Setup

Add the ProvidersTrait to your User model definition.

```php
use Mmanos\Social\ProvidersTrait;

class User extends Eloquent
{
	use ProvidersTrait;
	
}
```

#### Social Login Flow

Simply create a link to the built-in controller to initiate a log in flow. The user will be redirected to the provider login page before they return to your website.

If an existing user is already linked to the provider account, they will be logged in as that user.

If an existing user is not found for the provider account, a new user record will be created and then a link to the provider account will be made.

```php
<a href="{{ action('Mmanos\Social\SocialController@getLogin', array('twitter')) }}">Log in with Twitter</a>
```

To customize where the user is redirected to after the log in flow, add `onsuccess` and `onerror` parameters.

```php
<a href="{{ action('Mmanos\Social\SocialController@getLogin', array('twitter')) }}?onsuccess=/account&onerror=/login">
	Log in with Twitter
</a>
```

> **Note:** The default redirect location is to `/`.

#### Connecting A Social Account

You can associate a social provider account to an existing user if they are already logged in.

```php
<a href="{{ action('Mmanos\Social\SocialController@getConnect', array('twitter')) }}">Connect Your Twitter Account</a>
```

> **Note:** This action also supports the `onsuccess` and `onerror` parameters.

#### Working With Users And Providers

You can fetch all providers linked with a user.

```php
$providers = Auth::user()->providers;
```

You can check to see if a user is connected to a given provider.

```php
if (Auth::user()->hasProvider('twitter')) {
	//
}
```

You can fetch a single provider instance for a user.

```php
$provider = Auth::user()->provider('twitter');
```

This package stores an encrypted version of the access_token for each user provider. That way you can easily make API calls to the provider service on behalf of the user.

```php
$result = Auth::user()->provider('twitter')->request('account/verify_credentials.json');
```

> **Note:** Keep in mind it is possible for an access_token to expire. You can refresh a user's access_token by initiating a redirect to the `getConnect` action in the controller.

#### Create User Logic

If you want to customize the logic used to create a user during the social login flow, modify the `create_user` key in the config file.

```php
'create_user' => function ($data) {
	$user = new User;
	$user->email = array_get($data, 'email');
	$user->password = Hash::make(Str::random());
	$user->location = array_get($data, 'location'); // Only passed by certain providers.
	$user->save();
	
	return $user->id;
},
```

> **Note:** Make sure to return a numeric primary key when finished.

#### Provider User Information

To customize which data you want to retrieve from a social provider account, modify the `fetch_user_info` key for each provider in the config file.

```php
	'fetch_user_info' => function ($service) {
		$result = json_decode($service->request('account/verify_credentials.json'), true);
		return array(
			'id'         => array_get($result, 'id'),
			'email'      => null,
			'first_name' => array_get(explode(' ', array_get($result, 'name')), 0),
			'last_name'  => array_get(explode(' ', array_get($result, 'name')), 1)
		);
	},
```

> **Note:** This function also allows you to normalize the user data returned by each of the social providers by mapping their fields to fields you want to store in your user record.

#### Social Login Validation

You can configure the social login flow to validate the user information returned by the social provider. This way you can ensure that all of the data you require for a new user is properly obtained and in the correct format.

To customize the validation rules, modify the `user_validation` key in the config file.

```php
'user_validation' => array(
	'email'      => 'required|email',
	'first_name' => 'required',
),
```

> **Note:** You may also declare a closure function to create and return a Validator instance for greater flexibility.

## Social Buttons

This package provides some convenient css that allows you to create nice social buttons. It is built for use with the Twitter Bootstrap framework.

#### Publish The Assets

Publish the public assets for this package.

```console
$ php artisan asset:publish mmanos/laravel-social
```

#### Include The Assets

```php
{{ HTML::style('/packages/mmanos/laravel-social/css/socialbuttons.css') }}
```

#### Building Buttons

```php
<a href="{{ action('Mmanos\Social\SocialController@getLogin', array('twitter')) }}" class="btn btn-social btn-twitter">
	<i class="fa fa-twitter"></i> Log in with Twitter
</a>
<a href="{{ action('Mmanos\Social\SocialController@getLogin', array('twitter')) }}" class="btn btn-social-icon btn-twitter">
	<i class="fa fa-twitter"></i>
</a>
```

[See here](https://github.com/lipis/bootstrap-social#available-classes) for a list of supported class names.
