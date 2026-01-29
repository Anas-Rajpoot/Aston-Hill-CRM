@extends('layouts.app')

@section('content')
<x-ui.card>
    <div class="space-y-4">
        <div>
            <h2 class="text-xl font-semibold">Upload Documents</h2>
            <p class="text-sm text-gray-500">Step 4 — Upload required files (stored in public/lead-submission/{leadId}/...)</p>
        </div>

        @include('lead-submission.partials._wizard_steps', ['step' => 4])

        <form method="POST" action="{{ route('lead-submission.wizard.step4.store', $leadSubmission) }}" enctype="multipart/form-data" class="mt-4 space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($docDefs as $doc)
                    @php
                        $key = $doc['key'];
                        $label = $doc['label'] ?? $key;
                        $required = (bool)($doc['required'] ?? false);
                    @endphp

                    <div class="space-y-1">
                        <label class="text-xs font-medium text-gray-600">
                            {{ $label }} @if($required) <span class="text-red-600">*</span> @endif
                        </label>

                        <input type="file" name="documents[{{ $key }}]"
                               class="w-full rounded-md border-gray-300 focus:border-brand-primary focus:ring-brand-primary"/>

                        @error("documents.$key")
                          <p class="text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>

            <div class="flex justify-end gap-2">
                <button name="action" value="save" class="px-4 py-2 rounded bg-gray-900 text-white">Save</button>
                <button name="action" value="submit" class="px-4 py-2 rounded bg-brand-primary text-white">Submit Lead Submission</button>
            </div>
        </form>
    </div>
</x-ui.card>
@endsection
