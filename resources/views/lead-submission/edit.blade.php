@extends('layouts.app')

@section('content')
<x-ui.card>
    <div class="flex items-start justify-between gap-4">
        <div>
            <h2 class="text-xl font-semibold">Edit Lead Submission #{{ $leadSubmission->id }}</h2>
            <p class="text-sm text-gray-500">Update primary info, service selection, dynamic fields and documents.</p>
        </div>
        <a href="{{ route('lead-submissions.show', $leadSubmission) }}" class="px-4 py-2 rounded bg-gray-200 text-gray-900 text-sm">Back</a>
    </div>

    <form method="POST" action="{{ route('lead-submissions.update', $leadSubmission) }}" enctype="multipart/form-data" class="mt-5 space-y-5">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <x-ui/input name="company_name" label="Company Name" :value="$leadSubmission->company_name" />
            <x-ui/input name="account_number" label="Account Number" :value="$leadSubmission->account_number" />
            <x-ui/input name="request_type" label="Request Type" :value="$leadSubmission->request_type" />

            <x-ui/input name="email" label="Email" type="email" :value="$leadSubmission->email" />
            <x-ui/input name="contact_number" label="Contact Number" :value="$leadSubmission->contact_number" />
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-ui/select name="service_category_id" label="Service Category"
                :options="$categories->pluck('name','id')->toArray()" :value="$leadSubmission->service_category_id" />

            <x-ui/select name="service_type_id" label="Service Type"
                :options="$types->pluck('name','id')->toArray()" :value="$leadSubmission->service_type_id" />
        </div>

        <div class="border rounded-xl p-4">
            <p class="font-semibold text-gray-800 mb-2">Dynamic Fields</p>
            @if(empty($fields))
                <p class="text-sm text-gray-500">No schema fields found.</p>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($fields as $field)
                        @php
                            $key = $field['key'] ?? '';
                            $label = $field['label'] ?? $key;
                            $type = $field['type'] ?? 'text';
                            $val = data_get($leadSubmission->meta, $key);
                            $placeholder = $field['placeholder'] ?? '';
                        @endphp

                        @if($type === 'textarea')
                            <div class="md:col-span-2">
                                <x-ui/textarea name="meta[{{ $key }}]" label="{{ $label }}" :value="$val" placeholder="{{ $placeholder }}" />
                            </div>
                        @elseif($type === 'select')
                            @php
                                $opts = [];
                                foreach(($field['options'] ?? []) as $o){
                                    $opts[(string)$o] = (string)$o;
                                }
                            @endphp
                            <x-ui/select name="meta[{{ $key }}]" label="{{ $label }}" :options="$opts" :value="$val" />
                        @elseif($type === 'checkbox')
                            <div class="flex items-center gap-2 border rounded-md p-3">
                                <input type="checkbox" name="meta[{{ $key }}]" value="1"
                                       class="rounded border-gray-300 focus:ring-brand-primary"
                                       @checked(old("meta.$key", $val)) />
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $label }}</p>
                                    @error("meta.$key") <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                                </div>
                            </div>
                        @else
                            <x-ui/input name="meta[{{ $key }}]" label="{{ $label }}" type="{{ $type }}" :value="$val" placeholder="{{ $placeholder }}" />
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        <div class="border rounded-xl p-4">
            <p class="font-semibold text-gray-800 mb-2">Documents</p>

            @if($existingDocs->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                    @foreach($existingDocs as $existingDoc)
                        <a class="block border rounded-lg p-3 hover:bg-gray-50"
                           href="{{ asset('storage/'.$d->path) }}" target="_blank">
                            <p class="text-sm font-medium">{{ $existingDoc->original_name }}</p>
                            <p class="text-xs text-gray-500">{{ $existingDoc->doc_key }}</p>
                        </a>
                    @endforeach
                </div>
            @endif

            @if(!empty($docDefs))
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($docDefs as $docDef)
                        @php
                            $key = $docDef['key'] ?? '';
                            $label = $docDef['label'] ?? $key;
                        @endphp
                        <div class="space-y-1">
                            <label class="text-xs font-medium text-gray-600">{{ $label }}</label>
                            <input type="file" name="documents[{{ $key }}]"
                                   class="w-full rounded-md border-gray-300 focus:border-brand-primary focus:ring-brand-primary"/>
                            @error("documents.$key") <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500">No document schema found.</p>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <button class="px-4 py-2 rounded bg-brand-primary text-white">Save Changes</button>
        </div>
    </form>
</x-ui.card>
@endsection
