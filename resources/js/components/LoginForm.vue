<script setup>
import { reactive, ref } from 'vue';
import { request } from '../api';

const emit = defineEmits(['authenticated']);
const form = reactive({ phone: '', password: '' });
const error = ref('');
const loading = ref(false);

async function submit() {
    error.value = '';
    loading.value = true;
    try {
        const response = await request('auth/login', { method: 'POST', body: JSON.stringify(form) });
        emit('authenticated', response.token);
    } catch (exception) {
        error.value = exception.message;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <form @submit.prevent="submit">
        <h1>Вход</h1>
        <label>Телефон<input v-model="form.phone" autocomplete="tel" inputmode="tel" required></label>
        <label>Пароль<input v-model="form.password" type="password" autocomplete="current-password" required></label>
        <p v-if="error" role="alert">{{ error }}</p>
        <button :disabled="loading">{{ loading ? 'Вход…' : 'Войти' }}</button>
    </form>
</template>
