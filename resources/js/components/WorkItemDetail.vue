<script setup>
import { computed, reactive, ref } from 'vue';
import { labels } from '../workItems';
const props = defineProps({ item: { type: Object, required: true }, canManage: Boolean, canAssign: Boolean, canBudget: Boolean, canConvert: Boolean, transitions: { type: Array, default: () => [] } });
const emit = defineEmits(['action', 'close']);
const comment = ref(''); const attachment = ref(null); const assignee = ref('');
const time = reactive({ minutes: '', description: '', recorded_at: new Date().toISOString().slice(0, 16) });
const budget = reactive({ amount: '', currency: 'RUB', description: '', recorded_at: new Date().toISOString().slice(0, 16) });
const isTask = computed(() => props.item.kind === 'task');
function act(endpoint, data, method) { emit('action', endpoint, data, method); }
function upload() { if (!attachment.value) return; emit('upload', attachment.value); attachment.value = null; }
</script>
<template>
 <section class="work-detail" aria-label="Карточка работы">
  <button class="secondary" @click="emit('close')">К списку</button><h1>{{ item.title }}</h1><p>{{ item.description }}</p>
  <p><strong>{{ labels[item.kind] }}</strong> · {{ labels[item.status] }} · {{ labels[item.priority] }}</p><p v-if="item.due_at">Срок: {{ new Date(item.due_at).toLocaleString('ru-RU') }}</p>
  <div v-if="transitions.length" class="actions"><button v-for="status in transitions" :key="status" @click="act('transition', { status })">{{ labels[status] }}</button></div>
  <button v-if="canConvert" @click="act('convert', { kind: isTask ? 'request' : 'task' })">Преобразовать в {{ isTask ? 'обращение' : 'задачу' }}</button>
  <form @submit.prevent="act('comments', { body: comment }); comment = ''"><label>Комментарий<textarea v-model="comment" required></textarea></label><button>Добавить комментарий</button></form>
  <form @submit.prevent="upload"><label>Вложение<input type="file" @change="attachment = $event.target.files[0]"></label><button :disabled="!attachment">Прикрепить файл</button></form>
  <form v-if="isTask && canAssign" @submit.prevent="act('assignees', { assignees: [{ external_name: assignee }] }, 'PUT')"><label>Исполнитель<input v-model="assignee" required></label><button>Назначить</button></form>
  <form v-if="isTask && canBudget" @submit.prevent="act('time-entries', { ...time, minutes: Number(time.minutes) }); time.minutes = ''"><h2>Учёт времени</h2><label>Минуты<input v-model="time.minutes" type="number" min="1" required></label><label>Описание<input v-model="time.description"></label><button>Записать время</button></form>
  <form v-if="isTask && canBudget" @submit.prevent="act('budget-entries', { ...budget, amount: Number(budget.amount) }); budget.amount = ''"><h2>Бюджет</h2><label>Сумма<input v-model="budget.amount" type="number" min="0" required></label><label>Валюта<input v-model="budget.currency" maxlength="3" required></label><label>Описание<input v-model="budget.description"></label><button>Записать расход</button></form>
  <h2>Исполнители</h2><p v-if="!item.assignees?.length">Не назначены</p><ul><li v-for="assignee in item.assignees" :key="assignee.user_id || assignee.external_name">{{ assignee.external_name || assignee.user_id }}</li></ul>
  <h2>Вложения</h2><p v-if="!item.attachments?.length">Нет вложений</p><ul><li v-for="file in item.attachments" :key="file.id">{{ file.original_name }} ({{ file.mime_type }}, {{ file.size_bytes }} байт)</li></ul>
  <h2>Комментарии</h2><p v-if="!item.comments?.length">Нет комментариев</p><ul><li v-for="entry in item.comments" :key="entry.id">{{ entry.body }}</li></ul>
  <h2>История</h2><p v-if="!item.history?.length">Нет записей</p><ol><li v-for="entry in item.history" :key="entry.id">{{ entry.field }}: {{ entry.old_value || '—' }} → {{ entry.new_value || '—' }}</li></ol>
 </section>
</template>
