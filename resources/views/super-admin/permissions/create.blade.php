@extends('layouts.app')

@section('content')
<h2>Create Permission</h2>

@if($errors->any())
  <div style="color:red">
    <ul>
      @foreach($errors->all() as $e) <li>{{ $e }}</li> @endforeach
    </ul>
  </div>
@endif

<form method="POST" action="{{ route('super-admin.permissions.store') }}">
  @csrf

  <label>
    <input type="radio" name="mode" value="module_actions" checked>
    Create by Module + Actions (recommended)
  </label>
  <br>

  <div style="margin:10px 0; padding:10px; border:1px solid #ddd;">
    <label>Module</label>
    <select name="module">
      @foreach($modules as $k => $label)
        <option value="{{ $k }}" @selected(old('module')===$k)>{{ $label }} ({{ $k }})</option>
      @endforeach
    </select>

    <p style="margin-top:10px;"><b>Actions</b></p>
    @foreach($actions as $actionKey => $actionLabel)
      <label style="display:inline-block;margin-right:12px;">
        <input type="checkbox" name="actions[]" value="{{ $actionKey }}">
        {{ $actionLabel }}
      </label>
    @endforeach
  </div>

  <hr>

  <label>
    <input type="radio" name="mode" value="custom" @checked(old('mode')==='custom')>
    Create Custom Permission (must be module.action)
  </label>

  <div style="margin:10px 0; padding:10px; border:1px solid #ddd;">
    <label>Custom Name</label>
    <input name="custom_name" placeholder="accounts.edit" value="{{ old('custom_name') }}">
    <small>Format: module.action (example: accounts.edit)</small>
  </div>

  <button type="submit">Save</button>
</form>
@endsection