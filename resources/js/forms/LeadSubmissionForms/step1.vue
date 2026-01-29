<script setup>
import { ref } from 'vue'
import api from '@/services/leadSubmissionsApi'
import { useFormErrors } from '@/composables/useFormErrors'

const emit = defineEmits(['next'])

const form = ref({
  company_name: '',
  account_number: '',
  authorized_signatory_name: '',
  contact_number_gsm: '',
  alternate_contact_number: '',
  email: '',
  address: '',
  emirates: '',
  location_coordinates: '',
  product: '',
  offer: '',
  mrc_aed: '',
  quantity: '',
  remarks: '',
})

const { errors, setErrors, clearErrors } = useFormErrors()

const submit = async () => {
  clearErrors()
  try {
    const { data } = await api.storeStep1(form.value)
    emit('next', data.id)
  } catch (e) {
    setErrors(e)
  }
}
</script>

<template>
  <form @submit.prevent="submit" class="grid grid-cols-1 md:grid-cols-3 gap-4">

    <Input label="Company Name" v-model="form.company_name" :error="errors.company_name" />
    <Input label="Account Number" v-model="form.account_number" :error="errors.account_number" />
    <Input label="Authorized Signatory" v-model="form.authorized_signatory_name" />

    <Input label="Contact Number" v-model="form.contact_number_gsm" />
    <Input label="Alternate Contact" v-model="form.alternate_contact_number" />
    <Input label="Email" type="email" v-model="form.email" :error="errors.email" />

    <div class="md:col-span-3">
      <Input label="Address" v-model="form.address" />
    </div>

    <Input label="Emirates" v-model="form.emirates" />
    <Input label="Location Coordinates" placeholder="lat,lng" v-model="form.location_coordinates" />

    <Input label="Product" v-model="form.product" />
    <Input label="Offer" v-model="form.offer" />
    <Input label="MRC" v-model="form.mrc_aed" />

    <Input label="Quantity" type="number" v-model="form.quantity" />

    <div class="md:col-span-3">
      <Input label="Remarks" v-model="form.remarks" />
    </div>

    <div class="md:col-span-3 flex justify-end">
      <button class="btn-primary">Continue</button>
    </div>
  </form>
</template>
