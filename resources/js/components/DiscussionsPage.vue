<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import { ApiError, request } from '../api';
import { can, state } from '../auth';

const discussions = ref([]);
const selected = ref(null);
const loading = ref(false);
const error = ref('');
const notice = ref('');
const createForm = reactive({ title: '', visibility: 'public' });
const messageBody = ref('');
const memberIds = ref('');

const isAuthor = computed(() => selected.value?.author_id === state.user?.id);
const isClosed = computed(() => selected.value?.status === 'closed');
const isMember = computed(() => selected.value?.members?.includes(state.user?.id) ?? false);
const canClose = computed(() => isAuthor.value || isMember.value && can('discussions.moderate'));
const canSetVerdict = computed(() => isMember.value && can('discussions.moderate'));

function resetFeedback() {
    error.value = '';
    notice.value = '';
}

function applyDiscussion(discussion) {
    selected.value = discussion;
    const index = discussions.value.findIndex(({ id }) => id === discussion.id);
    const summary = { id: discussion.id, title: discussion.title, visibility: discussion.visibility, status: discussion.status };
    if (index === -1) discussions.value.unshift(summary);
    else discussions.value.splice(index, 1, summary);
}

async function loadDiscussions() {
    loading.value = true;
    resetFeedback();
    try {
        discussions.value = await request('discussions', {}, state.token);
    } catch (exception) {
        error.value = exception.message;
    } finally {
        loading.value = false;
    }
}

async function openDiscussion(id) {
    loading.value = true;
    resetFeedback();
    try {
        applyDiscussion(await request(`discussions/${id}`, {}, state.token));
    } catch (exception) {
        // Do not retain a previously loaded private conversation after access is denied.
        if (exception instanceof ApiError && exception.status === 403) selected.value = null;
        error.value = exception.status === 403 ? 'У вас нет доступа к этому обсуждению.' : exception.message;
    } finally {
        loading.value = false;
    }
}

async function createDiscussion() {
    resetFeedback();
    try {
        applyDiscussion(await request('discussions', { method: 'POST', body: JSON.stringify(createForm) }, state.token));
        createForm.title = '';
        notice.value = 'Обсуждение создано.';
    } catch (exception) {
        error.value = exception.message;
    }
}

async function inviteMembers() {
    const userIds = memberIds.value.split(/[\s,]+/).map((id) => id.trim()).filter(Boolean);
    if (!userIds.length) return;
    resetFeedback();
    try {
        applyDiscussion(await request(`discussions/${selected.value.id}/members`, { method: 'POST', body: JSON.stringify({ user_ids: userIds }) }, state.token));
        memberIds.value = '';
        notice.value = 'Участники приглашены.';
    } catch (exception) {
        error.value = exception.message;
    }
}

async function sendMessage() {
    if (!messageBody.value.trim() || isClosed.value) return;
    resetFeedback();
    try {
        await request(`discussions/${selected.value.id}/messages`, { method: 'POST', body: JSON.stringify({ body: messageBody.value }) }, state.token);
        messageBody.value = '';
        await openDiscussion(selected.value.id);
    } catch (exception) {
        error.value = exception.message;
    }
}

async function like(message) {
    resetFeedback();
    try {
        await request(`messages/${message.id}/like`, { method: 'POST' }, state.token);
        await openDiscussion(selected.value.id);
    } catch (exception) {
        error.value = exception.message;
    }
}

async function setVerdict(message) {
    resetFeedback();
    try {
        applyDiscussion(await request(`discussions/${selected.value.id}/verdict`, { method: 'POST', body: JSON.stringify({ message_id: message.id }) }, state.token));
        notice.value = 'Вердикт установлен.';
    } catch (exception) {
        error.value = exception.message;
    }
}

async function closeDiscussion() {
    resetFeedback();
    try {
        applyDiscussion(await request(`discussions/${selected.value.id}/close`, { method: 'POST' }, state.token));
        notice.value = 'Обсуждение закрыто.';
    } catch (exception) {
        error.value = exception.message;
    }
}

onMounted(loadDiscussions);
</script>

<template>
    <section class="discussions-page" aria-labelledby="discussions-heading">
        <h1 id="discussions-heading">Обсуждения</h1>
        <p v-if="error" role="alert">{{ error }}</p>
        <p v-if="notice" role="status">{{ notice }}</p>

        <form data-test="create-discussion" @submit.prevent="createDiscussion">
            <h2>Новое обсуждение</h2>
            <label>Тема<input v-model.trim="createForm.title" required maxlength="255" /></label>
            <label>Доступ<select v-model="createForm.visibility"><option value="public">Публичное</option><option value="private">Приватное</option></select></label>
            <button>Создать</button>
        </form>

        <div class="discussions-layout">
            <section aria-labelledby="discussion-list-heading">
                <div class="section-heading"><h2 id="discussion-list-heading">Список</h2><button type="button" @click="loadDiscussions" :disabled="loading">Обновить</button></div>
                <p v-if="loading">Загрузка…</p>
                <p v-else-if="!discussions.length">Обсуждений пока нет.</p>
                <ul v-else class="discussion-list">
                    <li v-for="discussion in discussions" :key="discussion.id"><button type="button" class="discussion-link" @click="openDiscussion(discussion.id)">{{ discussion.title }} <small>{{ discussion.visibility === 'private' ? 'Приватное' : 'Публичное' }} · {{ discussion.status === 'closed' ? 'Закрыто' : 'Открыто' }}</small></button></li>
                </ul>
            </section>

            <section v-if="selected" aria-live="polite" :aria-labelledby="`discussion-${selected.id}`">
                <div class="section-heading"><h2 :id="`discussion-${selected.id}`">{{ selected.title }}</h2><span v-if="isClosed" class="closed-badge">Закрыто</span></div>
                <p v-if="selected.verdict" class="verdict">Вердикт: сообщение от {{ selected.verdict.created_at }}</p>
                <form v-if="selected.visibility === 'private' && isAuthor" data-test="invite-members" @submit.prevent="inviteMembers">
                    <label>Идентификаторы приглашённых пользователей<input v-model="memberIds" aria-label="Идентификаторы пользователей" placeholder="UUID через запятую" /></label>
                    <button :disabled="isClosed">Пригласить</button>
                </form>
                <ol class="messages" aria-label="Сообщения">
                    <li v-for="message in selected.messages" :key="message.id" class="message">
                        <p>{{ message.body }}</p>
                        <p><small>{{ message.created_at }} · Нравится: {{ message.likes_count }}</small></p>
                        <ul v-if="message.attachments?.length" aria-label="Вложения">
                            <li v-for="attachment in message.attachments" :key="attachment.id">{{ attachment.original_name }} ({{ attachment.mime_type }})</li>
                        </ul>
                        <div class="message-actions">
                            <button type="button" data-test="like" @click="like(message)" :disabled="isClosed">Нравится</button>
                            <button v-if="canSetVerdict" type="button" data-test="set-verdict" @click="setVerdict(message)" :disabled="isClosed">Выбрать вердиктом</button>
                        </div>
                    </li>
                </ol>
                <form data-test="send-message" @submit.prevent="sendMessage">
                    <label>Сообщение<textarea v-model="messageBody" required :disabled="isClosed"></textarea></label>
                    <button :disabled="isClosed">Отправить</button>
                </form>
                <button v-if="canClose" type="button" data-test="close-discussion" @click="closeDiscussion" :disabled="isClosed">Закрыть обсуждение</button>
            </section>
            <section v-else aria-live="polite"><p>Выберите обсуждение из списка.</p></section>
        </div>
    </section>
</template>
