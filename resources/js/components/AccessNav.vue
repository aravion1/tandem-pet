<script setup>
defineProps({ user: { type: Object, default: null } });
</script>

<template>
    <nav v-if="user" aria-label="Управление">
        <RouterLink to="/">Главная</RouterLink>
        <RouterLink v-if="user.permissions.some((permission) => ['requests.view_public', 'requests.view_private', 'requests.moderate', 'tasks.view', 'tasks.manage'].includes(permission))" to="/work-items">Обращения и задачи</RouterLink>
        <RouterLink to="/discussions">Обсуждения</RouterLink>
        <RouterLink to="/publications">Публикации</RouterLink>
        <RouterLink v-if="user.permissions.includes('users.manage')" to="/residents">Жители</RouterLink>
        <RouterLink v-if="user.permissions.includes('roles.manage')" to="/roles">Роли</RouterLink>
    </nav>
</template>
