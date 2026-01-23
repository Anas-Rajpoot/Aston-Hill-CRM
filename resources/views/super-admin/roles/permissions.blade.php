@extends('layouts.app')

@section('content')
<h2>Set Permissions for Role: {{ $role->name }}</h2>
<x-breadcrumbs />

<div class="flex items-center gap-2">
    @php
      $tabId = request()->query('__tab') ?? request()->cookie('__tab');
      $trail = $tabId ? session("breadcrumbs_trail.$tabId", []) : [];
      $backUrl = count($trail) > 1 ? ($trail[count($trail)-2]['url'] ?? url()->previous()) : url()->previous();
    @endphp

    <a href="{{ $backUrl }}"
        class="text-sm text-gray-600 hover:text-indigo-600">
        ← Back
    </a>
    <a href="{{ route('super-admin.roles.create') }}"
    class="bg-indigo-600 text-white px-4 py-2 rounded-md">Add Role</a>
</div>

@if(session('success')) <p style="color:green">{{ session('success') }}</p> @endif
@if(session('error')) <p style="color:red">{{ session('error') }}</p> @endif

{{-- GLOBAL ACTIONS --}}
<div style="margin:12px 0; padding:10px; border:1px solid #ddd;">
  <b>Global Controls</b><br><br>

  <button type="button" onclick="checkAll(true)">Check All (All Modules)</button>
  <button type="button" onclick="checkAll(false)">Uncheck All (All Modules)</button>

  {{-- Save all permissions --}}
  <button type="submit" form="save-all-form">Save All</button>
</div>

{{-- Save All form (no inputs needed here because checkboxes below belong to this form too) --}}
<form id="save-all-form" method="POST" action="{{ route('super-admin.roles.permissions.update', $role) }}">
  @csrf
  @method('PUT')
</form>

{{-- MODULE FORMS (one per module) --}}
@foreach($modules as $moduleKey => $moduleLabel)
  <form
    id="perm-form-{{ $moduleKey }}"
    method="POST"
    action="{{ route('super-admin.roles.permissions.updateModule', [$role, $moduleKey]) }}"
  >
    @csrf
    @method('PUT')
  </form>
@endforeach

<table border="1" cellpadding="10" style="width:100%; border-collapse:collapse;">
  <thead>
    <tr>
      <th style="width:220px;">Module</th>
      @foreach($actions as $actionKey => $actionLabel)
        <th style="text-align:center">{{ $actionLabel }}</th>
      @endforeach
    </tr>
  </thead>

  <tbody>
    @foreach($modules as $moduleKey => $moduleLabel)
      @php
        $moduleFormId = "perm-form-{$moduleKey}";
      @endphp

      <tr>
        <td>
          <b>{{ $moduleLabel }}</b>
          <div style="font-size:12px;color:#666">{{ $moduleKey }}</div>

          <div style="margin-top:8px;">
            <button type="button" onclick="toggleModule('{{ $moduleKey }}', true)">Check All</button>
            <button type="button" onclick="toggleModule('{{ $moduleKey }}', false)">Uncheck All</button>

            {{-- Save only this module --}}
            <button type="submit" form="{{ $moduleFormId }}">Save</button>
          </div>
        </td>

        @foreach($actions as $actionKey => $actionLabel)
          @php
            $perm = "{$moduleKey}.{$actionKey}";
            $exists = isset($allPermissions[$perm]);
            $checked = isset($rolePermissions[$perm]);
          @endphp

          <td style="text-align:center">
            @if($exists)
              <input
                class="perm-cb perm-{{ $moduleKey }}"
                type="checkbox"
                name="permissions[]"
                value="{{ $perm }}"
                @checked($checked)

                form="save-all-form"
                data-module-form="{{ $moduleFormId }}"
              >
              <div style="font-size:11px;color:#666">{{ $perm }}</div>

              <input
                type="checkbox"
                name="permissions[]"
                value="{{ $perm }}"
                @checked($checked)
                form="{{ $moduleFormId }}"
                class="mirror-{{ $moduleKey }}"
                style="display:none;"
              >
            @else
              <span style="color:red;font-size:12px">Missing</span>
            @endif
          </td>
        @endforeach
      </tr>
    @endforeach
  </tbody>
</table>
@endsection

<script>
  function toggleModule(moduleKey, state) {
    document.querySelectorAll('.perm-' + moduleKey).forEach(cb => cb.checked = state);
    syncModuleMirrors(moduleKey);
  }

  function checkAll(state){
    document.querySelectorAll('.perm-cb').forEach(cb => cb.checked = state);
    // sync all mirrors
    @foreach($modules as $moduleKey => $moduleLabel)
      syncModuleMirrors("{{ $moduleKey }}");
    @endforeach
  }

  // Keep module-form hidden inputs synced with visible checkboxes
  function syncModuleMirrors(moduleKey){
    const visibles = document.querySelectorAll('.perm-' + moduleKey);
    const mirrors  = document.querySelectorAll('.mirror-' + moduleKey);

    visibles.forEach((v, idx) => {
      if (mirrors[idx]) mirrors[idx].checked = v.checked;
    });
  }

  // initial sync on page load
  document.addEventListener('DOMContentLoaded', () => {
    @foreach($modules as $moduleKey => $moduleLabel)
      syncModuleMirrors("{{ $moduleKey }}");
    @endforeach

    // whenever a checkbox changes, sync its module mirrors
    document.querySelectorAll('.perm-cb').forEach(cb => {
      cb.addEventListener('change', () => {
        const classes = [...cb.classList];
        const moduleClass = classes.find(c => c.startsWith('perm-') && c !== 'perm-cb');
        if(moduleClass){
          const moduleKey = moduleClass.replace('perm-','');
          syncModuleMirrors(moduleKey);
        }
      });
    });
  });
</script>
