@extends('layouts.app')

@section('content')
<x-ui.card>
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold text-brand-dark">Lead Submission #{{ $leadSubmission->id }}</h2>
            <p class="text-sm text-gray-500">View lead Submission details & documents</p>
        </div>

        <div class="flex gap-2">
            @can('lead-submissions.edit')
                <a href="{{ route('lead-submissions.edit', $leadSubmission) }}" class="px-4 py-2 rounded bg-indigo-600 text-white text-sm">Edit</a>
            @endcan
            <a href="{{ route('lead-submissions.index') }}" class="px-4 py-2 rounded bg-gray-200 text-gray-900 text-sm">Back</a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
        <div class="md:col-span-2 space-y-3">
            <div class="border rounded-xl p-4">
                <p class="text-xs text-gray-500">Company</p>
                <p class="font-semibold">{{ $leadSubmission->company_name }}</p>
                <p class="text-sm text-gray-500 mt-1">Account: {{ $leadSubmission->account_number ?? '-' }}</p>
            </div>

            <div class="border rounded-xl p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <p class="text-xs text-gray-500">Category</p>
                    <p class="font-medium">{{ $leadSubmission->category?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Type</p>
                    <p class="font-medium">{{ $leadSubmission->type?->name ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Status</p>
                    <p class="font-medium">{{ ucfirst($leadSubmission->status ?? '-') }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-500">Created</p>
                    <p class="font-medium">{{ $leadSubmission->created_at?->format('d-M-Y h:i A') }}</p>
                </div>
            </div>

            <div class="border rounded-xl p-4">
                <p class="font-semibold text-gray-800 mb-2">Dynamic Fields</p>
                @if(empty($fields))
                    <p class="text-sm text-gray-500">No dynamic fields.</p>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($fields as $field)
                            @php
                                $key = $field['key'] ?? '';
                                $label = $field['label'] ?? $key;
                                $val = data_get($leadSubmission->meta, $key);
                                if(is_bool($val)) $val = $val ? 'Yes' : 'No';
                            @endphp
                            <div class="border rounded-lg p-3">
                                <p class="text-xs text-gray-500">{{ $label }}</p>
                                <p class="text-sm font-medium text-gray-800 break-words">{{ $val ?? '-' }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="space-y-3">
            <div class="border rounded-xl p-4">
                <p class="font-semibold text-gray-800 mb-2">Creator</p>
                <p class="text-sm text-gray-700">{{ $leadSubmission->creator?->name ?? '-' }}</p>
                <p class="text-xs text-gray-500">{{ $leadSubmission->creator?->email ?? '' }}</p>
            </div>

            <div class="border rounded-xl p-4">
                <p class="font-semibold text-gray-800 mb-2">Documents</p>
                @if($docs->isEmpty())
                    <p class="text-sm text-gray-500">No documents.</p>
                @else
                    <div class="space-y-2">
                        @foreach($docs as $doc)
                            <a class="block border rounded-lg p-3 hover:bg-gray-50"
                               href="{{ asset('storage/'.$d->path) }}" target="_blank">
                                <p class="text-sm font-medium text-gray-800">{{ $doc->original_name }}</p>
                                <p class="text-xs text-gray-500">{{ $doc->doc_key }} • {{ number_format(($doc->size ?? 0)/1024, 1) }} KB</p>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-ui.card>
@endsection
