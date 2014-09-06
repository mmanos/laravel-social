<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class LaravelSocialCreateProvidersTable extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		$table_name = Config::get('laravel-social::table', 'user_providers');
		
		Schema::create($table_name, function ($table) {
			$table->increments('id');
			$table->integer('user_id');
			$table->string('provider');
			$table->string('provider_id');
			$table->text('access_token');
			$table->timestamps();
			
			$table->unique(array('provider', 'provider_id'));
			$table->index(array('user_id', 'provider'));
		});
	}
	
	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		$table_name = Config::get('laravel-social::table', 'user_providers');
		
		Schema::drop($table_name);
	}
}