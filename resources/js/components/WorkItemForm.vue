<script setup>
import { reactive, watch } from 'vue';
const emit = defineEmits(['submit']);
const props = defineProps({ canCreateTask: Boolean });
const form = reactive({ kind: 'request', title: '', description: '', visibility: 'public', addressee_user_id: '', priority: 'normal', due_at: '' });
watch(() => form.kind, (kind) => { if (kind === 'task') form.visibility = 'public'; });
function submit() { emit('submit', { ...form, addressee_user_id: form.visibility === 'private' ? form.addressee_user_id : null, due_at: form.due_at || null }); }
</script>
<template>
  <form @submit.prevent="submit" aria-label="Новое обращение или задача">
    <h1>Новое обращение</h1>
    <label>Тип<select v-model="form.kind"><option value="request">Обращение</option><option v-if="canCreateTask" value="task">Задача</option></select></label>
    <label>Заголовок<input v-model="form.title" required maxlength="255"></label>
    <label>Описание<textarea v-model="form.description" required rows="4"></textarea></label>
    <template v-if="form.kind === 'request'"><label>Видимость<select v-model="form.visibility"><option value="public">Публичное</option><option value="private">Личное</option></select></label><label v-if="form.visibility === 'private'">ID адресата<input v-model="form.addressee_user_id" required aria-label="Адресат личного обращения"></label></template>
    <label>Приоритет<select v-model="form.priority"><option value="low">Низкий</option><option value="normal">Обычный</option><option value="high">Высокий</option><option value="urgent">Срочный</option></select></label>
    <label v-if="form.kind === 'task'">Срок<input v-model="form.due_at" type="datetime-local"></label>
    <button>Создать</button>
  </form>
</template>
