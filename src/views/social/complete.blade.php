<div class="container">
	<div class="row">
		<div class="col-md-4 col-md-offset-4">
			<h3>Please complete your profile...</h3>

			{{ Form::open(array('action' => 'Mmanos\Social\SocialController@postComplete')) }}
				<fieldset>
					@foreach ($failed_fields as $idx => $field)
						<div class="form-group{{ $errors->has($field) ? ' has-error' : '' }}">
							{{ Form::label($field, ucwords(str_replace(array('-', '_'), ' ', $field))) }}

							@if ($idx === 0)
								{{ Form::text($field, Input::old($field, array_get($info, $field)), array('autofocus' => 'autofocus', 'class' => 'form-control')) }}
							@else
								{{ Form::text($field, Input::old($field, array_get($info, $field)), array('class' => 'form-control')) }}
							@endif

							{{ $errors->first($field, '<span class="help-block">:message</span>') }}
						</div>
					@endforeach

					<div class="form-group">
						{{ Form::submit('Save', array('class' => 'btn btn-primary')) }}
					</div>
				</fieldset>
			{{ Form::close() }}
		</div>
	</div>
</div>
