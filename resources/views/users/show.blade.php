<h2>{{ $user->name }}</h2>

<form method="POST" action="{{ route('users.approve',$user) }}">
    @csrf
    <select name="role">
        @foreach($roles as $role)
            <option value="{{ $role->name }}">{{ $role->name }}</option>
        @endforeach
    </select>
    <button>Approve</button>
</form>