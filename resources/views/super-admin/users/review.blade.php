<h2 class="text-xl font-semibold mb-4">User Approval</h2>

<div class="mb-4">
    <p><strong>Name:</strong> {{ $user->name }}</p>
    <p><strong>Email:</strong> {{ $user->email }}</p>
    <p><strong>Status:</strong> {{ $user->status }}</p>
</div>

@if ($errors->any())
    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3">
        <ul class="list-disc pl-5">
            @foreach ($errors->all() as $err)
                <li>{{ $err }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('super-admin.users.approve', $user->id) }}">
    @csrf
    @method('PUT')

    <div class="mb-4">
        <label class="block font-medium mb-1">Status</label>
        <select id="status" name="status" class="border rounded-md px-3 py-2 w-full" required>
            <option value="">Select</option>
            <option value="approved">Approved</option>
            <option value="pending">Pending</option>
            <option value="rejected">Rejected</option>
        </select>
    </div>

    <div id="rolesBox" class="mb-4 hidden">
        <label class="block font-medium mb-2">Assign Roles (Only if Approved)</label>

        <div class="border rounded-md p-3 grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-auto">
            @foreach($roles as $role)
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="roles[]" value="{{ $role->id }}" class="rounded border-gray-300">
                    <span>{{ $role->name }}</span>
                </label>
            @endforeach
        </div>

        <p class="text-xs text-gray-500 mt-2">Select one or more roles.</p>
    </div>

    <div id="rejectReasonBox" class="mb-4 hidden">
        <label class="block font-medium mb-1">Rejection Reason (optional)</label>
        <input type="text" name="rejection_reason" class="border rounded-md px-3 py-2 w-full"
               placeholder="e.g. Missing documents">
    </div>

    <button type="submit" class="bg-indigo-600 text-white px-4 py-2 rounded-md">
        Submit
    </button>
</form>

<script>
    (function () {
        const status = document.getElementById('status');
        const rolesBox = document.getElementById('rolesBox');
        const rejectReasonBox = document.getElementById('rejectReasonBox');

        function toggle() {
            const v = status.value;
            rolesBox.classList.toggle('hidden', v !== 'approved');
            rejectReasonBox.classList.toggle('hidden', v !== 'rejected');
        }

        status.addEventListener('change', toggle);
        toggle(); // initial
    })();
</script>
