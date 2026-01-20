@extends('layouts.app')

@section('title', 'User Details')
@section('page-title', 'User Details')
@section('page-desc', 'View user info and status.')

@section('content')
    @can('accounts.create')
    <a href="{{ route('accounts.create') }}">Add Account</a>
    @endcan

    @foreach($accounts as $account)
    <a href="{{ route('accounts.show', $account) }}">View</a>

    @can('update', $account)
        <a href="{{ route('accounts.edit', $account) }}">Edit</a>
    @endcan

    @can('delete', $account)
        <form method="POST" action="{{ route('accounts.destroy', $account) }}">
        @csrf
        @method('DELETE')
        <button type="submit">Delete</button>
        </form>
    @endcan
    @endforeach
@endsection