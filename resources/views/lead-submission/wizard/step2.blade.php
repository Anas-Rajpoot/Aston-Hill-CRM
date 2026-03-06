@extends('layouts.app')

@section('content')
<x-ui.card>
    <div class="space-y-4">
        <div>
            <h2 class="text-xl font-semibold">Create Lead Submission</h2>
            <p class="text-sm text-gray-500">Step 2 — Service Category</p>
        </div>

        @include('lead-submissions.partials._wizard_steps', ['step' => 2])

        <form method="POST" action="{{ route('lead-submissions.wizard.step2.store', $leadSubmission) }}" class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            @csrf

            <x-ui/select
                name="service_category_id"
                label="Service Category"
                :options="$categories->pluck('name','id')->toArray()"
                :value="$leadSubmission->service_category_id"
                placeholder="Select Category"
            />

            <div class="md:col-span-2 flex justify-between gap-2 mt-2">
                <a href="{{ route('lead-submissions.wizard.step1') }}" class="px-4 py-2 rounded bg-gray-200 text-gray-900">Back</a>
                <button class="px-4 py-2 rounded bg-brand-primary text-white">Continue</button>
            </div>
        </form>
    </div>
</x-ui.card>
@endsection
