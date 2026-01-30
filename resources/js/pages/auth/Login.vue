<script setup>
    import { ref } from 'vue'
    import { useRouter } from 'vue-router'
    import { useAuthStore } from '@/stores/auth'

    const router = useRouter()
    const auth = useAuthStore()

    const email = ref('')
    const password = ref('')
    const error = ref(null)

    const submit = async () => {
        try {
            await auth.login({
                email: email.value,
                password: password.value
            })

            router.push('/users')
        } catch {
            error.value = 'Invalid credentials'
        }
    }
</script>
