<div class="form-group {{ $errors->has($name) ? 'has-error' : '' }}">
	<label for="{{ $name }}" class="control-label">
		{{ $label }}

		@if($required)
			<span class="text-danger">*</span>
		@endif
	</label>
	<input class="form-control"
		   name="{{ $name }}"
		   type="password"
		   id="{{ $name }}"
		   value=""
		   @if($readonly) readonly @endif
	>
	@include($template->getViewPath('form.element.errors'))
</div>