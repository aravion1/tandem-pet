<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { request } from '../api';
import { can, state } from '../auth';

const type = ref('news');
const items = ref([]); const loading = ref(false); const error = ref(''); const notice = ref('');
const form = reactive({ title: '', body: '', presentation_format: 'board' });
const permission = computed(() => type.value === 'news' ? 'news.manage' : 'infoboards.manage');
const canManage = computed(() => can(permission.value));
const endpoint = computed(() => type.value === 'news' ? 'news' : 'infoboards');
async function load() { loading.value = true; error.value = ''; try { items.value = await request(endpoint.value, {}, state.token); } catch (e) { error.value = e.message; } finally { loading.value = false; } }
async function create() { error.value = ''; try { const payload = { title: form.title, body: form.body, ...(type.value === 'infoboard' ? { presentation_format: form.presentation_format } : {}) }; const item = await request(endpoint.value, { method: 'POST', body: JSON.stringify(payload) }, state.token); items.value.unshift(item); form.title = ''; form.body = ''; notice.value = 'Черновик создан.'; } catch (e) { error.value = e.message; } }
async function action(item, action) { error.value = ''; try { const fresh = await request(`${endpoint.value}/${item.id}/${action}`, { method: 'POST' }, state.token); Object.assign(item, fresh); } catch (e) { error.value = e.message; } }
onMounted(load);
</script>
<template>
 <section aria-labelledby="publications-heading"><h1 id="publications-heading">Публикации</h1>
  <p v-if="error" role="alert">{{ error }}</p><p v-if="notice" role="status">{{ notice }}</p>
  <fieldset><legend>Раздел</legend><label><input v-model="type" type="radio" value="news" @change="load" />Новости</label><label><input v-model="type" type="radio" value="infoboard" @change="load" />Инфо-борды</label></fieldset>
  <form v-if="canManage" @submit.prevent="create"><h2>Новая публикация</h2><label>Заголовок<input v-model.trim="form.title" required maxlength="255" /></label><label>Текст<textarea v-model.trim="form.body" required /></label><label v-if="type === 'infoboard'">Формат<select v-model="form.presentation_format"><option value="board">Доска</option><option value="page">Страница</option><option value="banner">Баннер</option></select></label><button>Создать черновик</button></form>
  <p v-if="loading">Загрузка…</p><p v-else-if="!items.length">Публикаций пока нет.</p><ul v-else class="work-list"><li v-for="item in items" :key="item.id" class="card" :class="item.presentation_format"><h2>{{ item.title }}</h2><p>{{ item.body }}</p><p><small>{{ item.status }}</small></p><ul v-if="item.attachments?.length"><li v-for="file in item.attachments" :key="file.id">{{ file.original_name }} ({{ file.mime_type }})</li></ul><div v-if="canManage" class="actions"><button v-if="item.status !== 'published'" type="button" @click="action(item, 'publish')">Опубликовать</button><button v-if="item.status === 'published'" type="button" class="secondary" @click="action(item, 'unpublish')">Снять с публикации</button></div></li></ul>
 </section>
</template>
