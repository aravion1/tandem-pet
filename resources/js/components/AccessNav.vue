<script setup>
defineProps({ user: { type: Object, default: null } });
</script>

<template>
    <nav v-if="user" aria-label="Управление">
        <RouterLink to="/">Главная</RouterLink>
        <RouterLink v-if="user.permissions.includes('users.manage')" to="/residents">Жители</RouterLink>
        <RouterLink v-if="user.permissions.includes('roles.manage')" to="/roles">Роли</RouterLink>
    </nav>
</template>
