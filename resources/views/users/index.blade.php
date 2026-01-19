@foreach($users as $user)
<tr>
<td>{{ $user->name }}</td>
<td>{{ $user->email }}</td>
<td>{{ $user->is_active ? 'Active':'Pending' }}</td>
<td>
<a href="{{ route('users.show',$user) }}">View</a>
</td>
</tr>
@endforeach