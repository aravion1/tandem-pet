<script setup>
import { reactive, ref } from 'vue';
import { request } from '../api';

const emit = defineEmits(['authenticated']);
const form = reactive({ phone: '', code: '', password: '', consent_version: 'v1' });
const requested = ref(false);
const error = ref('');

async function requestCode() {
    error.value = '';
    try {
        await request('auth/activation/request', { method: 'POST', body: JSON.stringify({ phone: form.phone }) });
        requested.value = true;
    } catch (exception) { error.value = exception.message; }
}

async function confirm() {
    error.value = '';
    try {
        const response = await request('auth/activation/confirm', { method: 'POST', body: JSON.stringify(form) });
        emit('authenticated', response.token);
    } catch (exception) { error.value = exception.message; }
}
</script>

<template>
    <form @submit.prevent="requested ? confirm() : requestCode()">
        <h1>Активация</h1>
        <label>Телефон<input v-model="form.phone" autocomplete="tel" inputmode="tel" required></label>
        <template v-if="requested">
            <label>Код из SMS<input v-model="form.code" inputmode="numeric" pattern="[0-9]{6}" required></label>
            <label>Пароль<input v-model="form.password" type="password" autocomplete="new-password" minlength="12" required></label>
            <label><input type="checkbox" required> Согласен на обработку данных</label>
        </template>
        <p v-if="error" role="alert">{{ error }}</p>
        <button>{{ requested ? 'Подтвердить' : 'Получить код' }}</button>
    </form>
</template>
