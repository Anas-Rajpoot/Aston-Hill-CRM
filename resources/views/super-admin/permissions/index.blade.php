@extends('layouts.app')

@section('content')
<h2>Permissions</h2>
@if(session('success')) <p style="color:green">{{ session('success') }}</p> @endif
<a href="{{ route('super-admin.permissions.create') }}">Add Permission</a>

<table border="1" cellpadding="8">
  <thead>
    <tr><th>ID</th><th>Name</th><th>Actions</th></tr>
  </thead>
  <tbody>
  @foreach($permissions as $p)
    <tr>
      <td>{{ $p->id }}</td>
      <td>{{ $p->name }}</td>
      <td>
        <a href="{{ route('super-admin.permissions.show',$p) }}">Show</a>
        <a href="{{ route('super-admin.permissions.edit',$p) }}">Edit</a>
        <form action="{{ route('super-admin.permissions.destroy',$p) }}" method="POST" style="display:inline">
          @csrf @method('DELETE')
          <button type="submit" onclick="return confirm('Delete?')">Delete</button>
        </form>
      </td>
    </tr>
  @endforeach
  </tbody>
</table>

{{ $permissions->links() }}
@endsection