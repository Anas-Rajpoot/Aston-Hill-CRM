@extends('layouts.app')

@section('content')
<x-ui.card>
    <div class="space-y-4">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold">Create Lead Submission</h2>
                <p class="text-sm text-gray-500">Step 1 — Primary Information</p>
            </div>
        </div>

        @include('lead-submission.partials._wizard_steps', ['step' => 1])

        <form method="POST" action="{{ route('lead-submissions.store.step1') }}" class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf

            <x-ui/input name="company_name" label="Company Name" />
            <x-ui/input name="account_number" label="Account Number" />
            <x-ui/input name="authorized_signatory_name" label="Authorized Signatory" />

            <x-ui/input name="contact_number" label="Contact Number" />
            <x-ui/input name="alternate_contact_number" label="Alternate Contact" />
            <x-ui/input name="email" label="Email" type="email" />

            <div class="md:col-span-3">
                <x-ui/input name="address" label="Address" />
            </div>

            <x-ui/input name="emirates" label="Emirates" />
            <x-ui/input name="location_coordinates" label="Location Coordinates" placeholder="lat,lng" />

            <x-ui/input name="product" label="Product" />
            <x-ui/input name="offer" label="Offer" />
            <x-ui/input name="mrc_aed" label="MRC" />

            <x-ui/input name="quantity" label="Quantity" type="number" />
            <div class="md:col-span-3">
                <x-ui/input name="remarks" label="Remarks" />
            </div>

            <div class="md:col-span-3 flex justify-end gap-2 mt-2">
                <button class="px-4 py-2 rounded bg-brand-primary text-white">Continue</button>
            </div>
        </form>
    </div>
</x-ui.card>
@endsection
