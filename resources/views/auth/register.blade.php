<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Phone -->
        <div class="mt-4">
            <x-input-label for="phone" :value="__('Phone')" />
            <x-text-input id="phone" class="block mt-1 w-full" type="tel" name="phone" :value="old('phone')" required autocomplete="phone" />
            <x-input-error :messages="$errors->get('phone')" class="mt-2" />
        </div>

        <!-- Country -->
        <div class="mt-4">
            <x-input-label for="country" :value="__('Country')" />
            <!-- <x-text-input id="country" class="block mt-1 w-full" type="text" name="country" :value="old('country')" required autocomplete="country" /> -->
            <select id="country" name="country"
                class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                
                <option value="" disabled selected>
                    Select your country
                </option>

                @foreach ($countries as $country)
                    <option value="{{ $country->code }}" data-timezone="{{ $country->timezone }}">
                        {{ $country->name }}
                    </option>
                @endforeach
            </select>
            <x-input-error :messages="$errors->get('country')" class="mt-2" />
        </div>

        <!-- Timezone -->
        <div class="mt-4">
            <x-input-label for="timezone" :value="__('Time zone')" />
            <x-text-input id="timezone" class="block mt-1 w-full" type="text" name="timezone" :value="old('timezone')" required autocomplete="timezone" readonly />
            <x-input-error :messages="$errors->get('timezone')" class="mt-2" />
        </div>

        <!-- CNIC Number -->
        <div class="mt-4">
            <x-input-label for="cnic_number" :value="__('CNIC Number')" />
            <x-text-input id="cnic_number" class="block mt-1 w-full" type="text" name="cnic_number" :value="old('cnic_number')" required autocomplete="cnic_number" />
            <x-input-error :messages="$errors->get('cnic_number')" class="mt-2" />
        </div>

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>

<script>
    const countrySelect = document.getElementById('country');
    const timezoneInput = document.getElementById('timezone');

    countrySelect.addEventListener('change', function () {
        const timezone = this.options[this.selectedIndex].dataset.timezone || '';
        timezoneInput.value = timezone;
    });
</script>
