@extends('layouts.app')

@section('content')
<h2>Edit Permission</h2>

<form method="POST" action="{{ route('super-admin.permissions.update',$permission) }}">
  @csrf @method('PUT')
  <label>Name</label>
  <input name="name" value="{{ old('name',$permission->name) }}">
  @error('name') <div style="color:red">{{ $message }}</div> @enderror
  <button type="submit">Update</button>
</form>
@endsection