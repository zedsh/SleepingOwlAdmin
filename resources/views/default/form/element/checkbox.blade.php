<div class="form-group {{ $errors->has($name) ? 'has-error' : '' }}">
	<div class="checkbox">
		<label>
			<input type="checkbox" name="{{ $name }}" value="1" {!! $value ? 'checked="checked"' : '' !!} />{{ $label }}
		</label>
	</div>
	@include($template->getViewPath('form.element.errors'))
</div>