<script setup>
import { onMounted, ref } from 'vue';
import { state } from '../auth';
import { labels, useWorkItems } from '../workItems';
import WorkItemDetail from './WorkItemDetail.vue';
import WorkItemForm from './WorkItemForm.vue';
const work = useWorkItems(); const showForm = ref(false);
async function create(data) { try { await work.create(data); showForm.value = false; } catch (e) { work.error.value = e.message; } }
async function action(endpoint, data, method = 'POST') { try { await work.action(work.selected.value, endpoint, data, method); } catch (e) { work.error.value = e.message; } }
async function upload(file) { const form = new FormData(); form.append('file', file); try { const response = await fetch(`/api/work-items/${work.selected.value.id}/attachments`, { method: 'POST', headers: { Accept: 'application/json', Authorization: `Bearer ${state.token}` }, body: form }); if (!response.ok) throw new Error((await response.json()).message); await work.select(work.selected.value); } catch (e) { work.error.value = e.message; } }
onMounted(work.load);
</script>
<template>
 <div class="work-page"><p v-if="work.error" role="alert">{{ work.error }}</p>
 <WorkItemDetail v-if="work.selected" :item="work.selected" :can-manage="work.canManage(work.selected)" :can-convert="work.canManage(work.selected)" :can-assign="work.has('tasks.assign')" :can-budget="work.has('budget.manage')" :transitions="work.availableTransitions(work.selected)" @close="work.selected = null" @action="action" @upload="upload" />
 <template v-else><WorkItemForm v-if="showForm" :can-create-task="work.canCreateTask" @submit="create" /><section v-else><div class="section-title"><h1>Обращения и задачи</h1><button @click="showForm = true">Создать</button></div><fieldset><legend>Фильтры</legend><label>Тип<select v-model="work.filters.kind"><option value="">Все</option><option value="request">Обращения</option><option value="task">Задачи</option></select></label><label>Статус<select v-model="work.filters.status"><option value="">Все</option><option v-for="(label, status) in { queued: 'В очереди', in_progress: 'В работе', completed: 'Выполнено', cancelled: 'Отменено' }" :key="status" :value="status">{{ label }}</option></select></label><label>Приоритет<select v-model="work.filters.priority"><option value="">Все</option><option value="high">Высокий</option><option value="urgent">Срочный</option></select></label></fieldset><p v-if="work.loading">Загрузка…</p><p v-else-if="!work.filtered.length">Нет доступных обращений и задач.</p><ul class="work-list"><li v-for="item in work.filtered" :key="item.id"><button class="card" @click="work.select(item)"><strong>{{ item.title }}</strong><span>{{ labels[item.kind] }} · {{ labels[item.status] }} · {{ labels[item.priority] }}</span><span v-if="item.due_at">до {{ new Date(item.due_at).toLocaleDateString('ru-RU') }}</span></button></li></ul></section></template>
 </div>
</template>
