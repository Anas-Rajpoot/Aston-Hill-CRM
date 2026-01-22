@extends('layouts.app')

@section('content')
<h2>Permission Details</h2>
<p><b>ID:</b> {{ $permission->id }}</p>
<p><b>Name:</b> {{ $permission->name }}</p>
<a href="{{ route('super-admin.permissions.index') }}">Back</a>

@endsection