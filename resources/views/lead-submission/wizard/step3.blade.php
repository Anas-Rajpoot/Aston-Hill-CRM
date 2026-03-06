@extends('layouts.app')

@section('content')
<x-ui.card>
    <div class="space-y-4">
        <div>
            <h2 class="text-xl font-semibold">Create Lead Submission</h2>
            <p class="text-sm text-gray-500">Step 3 — Service Type & Dynamic Fields</p>
        </div>

        @include('lead-submissions.partials._wizard_steps', ['step' => 3])

        <form method="GET" action="{{ route('lead-submissions.wizard.step3', $leadSubmission) }}" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-ui/select
                name="service_type_id"
                label="Service Type"
                :options="$types->pluck('name','id')->toArray()"
                :value="request('service_type_id') ?: $leadSubmission->service_type_id"
                placeholder="Select Service Type"
            />
            <div class="flex items-end">
                <button class="px-4 py-2 rounded bg-gray-900 text-white">Load Fields</button>
            </div>
        </form>

        <form method="POST" action="{{ route('lead-submissions.wizard.step3.store', $leadSubmission) }}" class="mt-2 space-y-4">
            @csrf

            <input type="hidden" name="service_type_id" value="{{ $selectedType?->id ?? $leadSubmission->service_type_id }}"/>

            @if(!$selectedType)
                <div class="p-4 rounded-lg border bg-gray-50 text-gray-600 text-sm">
                    Please select Service Type to load dynamic fields.
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($fields as $f)
                        @php
                            $key = $f['key'] ?? '';
                            $label = $f['label'] ?? $key;
                            $type = $f['type'] ?? 'text';
                            $placeholder = $f['placeholder'] ?? '';
                            $required = (bool)($f['required'] ?? false);
                            $val = data_get($leadSubmission->meta, $key);
                        @endphp

                        @if($type === 'textarea')
                            <div class="md:col-span-2">
                                <x-ui/textarea name="meta[{{ $key }}]" label="{{ $label }}" :value="$val" placeholder="{{ $placeholder }}" />
                            </div>
                        @elseif($type === 'select')
                            @php
                                $opts = [];
                                foreach(($f['options'] ?? []) as $o){
                                    $opts[(string)$o] = (string)$o;
                                }
                            @endphp
                            <x-ui/select
                                name="meta[{{ $key }}]"
                                label="{{ $label }}"
                                :options="$opts"
                                :value="$val"
                                placeholder="Select"
                            />
                        @elseif($type === 'checkbox')
                            <div class="flex items-center gap-2 border rounded-md p-3">
                                <input type="checkbox" name="meta[{{ $key }}]" value="1"
                                       class="rounded border-gray-300 focus:ring-brand-primary"
                                       @checked(old("meta.$key", $val)) />
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $label }}</p>
                                    @if($placeholder)
                                        <p class="text-xs text-gray-500">{{ $placeholder }}</p>
                                    @endif
                                    @error("meta.$key")
                                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        @else
                            {{-- text/number/email/date --}}
                            <x-ui/input
                                name="meta[{{ $key }}]"
                                label="{{ $label }}"
                                type="{{ $type }}"
                                :value="$val"
                                placeholder="{{ $placeholder }}"
                            />
                        @endif
                    @endforeach
                </div>
            @endif

            <div class="flex justify-between gap-2 mt-2">
                <a href="{{ route('lead-submissions.wizard.step2', $leadSubmission) }}" class="px-4 py-2 rounded bg-gray-200 text-gray-900">Back</a>
                <button class="px-4 py-2 rounded bg-brand-primary text-white">Continue</button>
            </div>
        </form>
    </div>
</x-ui.card>
@endsection
