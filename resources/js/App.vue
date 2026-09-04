<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { RouterView, useRouter } from 'vue-router';
import AccessNav from './components/AccessNav.vue';
import { acceptToken, refreshUser, signOut, state } from './auth';
import { request } from './api';

const message = ref('');
const reset = reactive({ phone: '', code: '', password: '', requested: false });
const resident = reactive({ phone: '', street: '', house: '' });
const phoneChange = reactive({ userId: '', phone: '' });
const roles = ref([]);
const newRole = ref('');

const router = useRouter();

async function authenticated(token) {
    await acceptToken(token);
    await router.push('/');
}

async function resetPassword() {
    message.value = '';
    try {
        if (!reset.requested) {
            await request('auth/password/reset/request', { method: 'POST', body: JSON.stringify({ phone: reset.phone }) });
            reset.requested = true;
            message.value = 'Если учётная запись существует, код отправлен.';
        } else {
            const result = await request('auth/password/reset/confirm', { method: 'POST', body: JSON.stringify(reset) });
            await authenticated(result.token);
        }
    } catch (error) { message.value = error.message; }
}

async function importResident() {
    message.value = '';
    try {
        await request('users/import', { method: 'POST', body: JSON.stringify({ residents: [resident] }) }, state.token);
        message.value = 'Житель импортирован.';
    } catch (error) { message.value = error.message; }
}

async function changePhone() {
    message.value = '';
    try {
        await request(`users/${phoneChange.userId}/phone`, { method: 'PATCH', body: JSON.stringify({ phone: phoneChange.phone }) }, state.token);
        message.value = 'Номер изменён.';
    } catch (error) { message.value = error.message; }
}

async function loadRoles() {
    try {
        roles.value = (await request('roles', {}, state.token)).map((role) => ({
            ...role,
            permissionText: role.permissions.map((permission) => permission.code).join(', '),
        }));
    } catch (error) { message.value = error.message; }
}

async function createRole() {
    try {
        await request('roles', { method: 'POST', body: JSON.stringify({ name: newRole.value }) }, state.token);
        newRole.value = '';
        await loadRoles();
    } catch (error) { message.value = error.message; }
}

async function saveRole(role) {
    try {
        await request(`roles/${role.id}/permissions`, { method: 'PUT', body: JSON.stringify({ permissions: role.permissionText.split(',').map((code) => code.trim()).filter(Boolean) }) }, state.token);
        message.value = 'Права роли сохранены.';
        await loadRoles();
    } catch (error) { message.value = error.message; }
}

async function renameRole(role) {
    try {
        await request(`roles/${role.id}`, { method: 'PATCH', body: JSON.stringify({ name: role.name }) }, state.token);
        message.value = 'Роль переименована.';
    } catch (error) { message.value = error.message; }
}

async function deleteRole(role) {
    try {
        await request(`roles/${role.id}`, { method: 'DELETE' }, state.token);
        await loadRoles();
    } catch (error) { message.value = error.message; }
}

function logout() {
    signOut();
    router.push('/login');
}

onMounted(async () => {
    if (!state.token) return router.replace('/login');
    try { await refreshUser(); } catch { logout(); }
});

const canManageUsers = computed(() => state.user?.permissions.includes('users.manage'));
const canManageRoles = computed(() => state.user?.permissions.includes('roles.manage'));
</script>

<template>
    <main class="app-shell">
        <header><strong>Посёлок</strong><button v-if="state.user" @click="logout">Выйти</button></header>
        <p v-if="!state.user"><RouterLink to="/login">Вход</RouterLink> · <RouterLink to="/activate">Активация</RouterLink> · <RouterLink to="/reset">Восстановить пароль</RouterLink></p>
        <AccessNav :user="state.user" />
        <p v-if="message" role="status">{{ message }}</p>
        <RouterView v-slot="{ Component }">
            <component :is="Component" @authenticated="authenticated" />
        </RouterView>
        <section v-if="$route.path === '/' && state.user"><h1>Добро пожаловать</h1><p>Выберите доступный раздел.</p></section>
        <section v-if="$route.path === '/reset'"><form @submit.prevent="resetPassword"><h1>Восстановление пароля</h1><label>Телефон<input v-model="reset.phone" required></label><template v-if="reset.requested"><label>Код<input v-model="reset.code" required></label><label>Новый пароль<input v-model="reset.password" type="password" minlength="12" required></label></template><button>{{ reset.requested ? 'Сменить пароль' : 'Получить код' }}</button></form></section>
        <section v-if="$route.path === '/residents' && canManageUsers"><form @submit.prevent="importResident"><h1>Импорт жителей</h1><label>Телефон<input v-model="resident.phone" required></label><label>Улица<input v-model="resident.street" required></label><label>Дом<input v-model="resident.house" required></label><button>Импортировать</button></form><form @submit.prevent="changePhone"><h2>Смена номера</h2><label>ID пользователя<input v-model="phoneChange.userId" required></label><label>Номер<input v-model="phoneChange.phone" required></label><button>Изменить номер</button></form></section>
        <section v-if="$route.path === '/roles' && canManageRoles"><h1>Роли</h1><button @click="loadRoles">Обновить</button><form v-for="role in roles" :key="role.id" @submit.prevent="saveRole(role)"><label>Название роли<input v-model="role.name" required @change="renameRole(role)"></label><label>Права через запятую<input v-model="role.permissionText" aria-label="Права роли" placeholder="roles.manage, users.manage"></label><button>Сохранить права</button><button type="button" @click="deleteRole(role)">Удалить</button></form><form @submit.prevent="createRole"><label>Новая роль<input v-model="newRole" required></label><button>Создать роль</button></form></section>
    </main>
</template>
