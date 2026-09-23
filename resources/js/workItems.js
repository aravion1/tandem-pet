import { computed, reactive, ref } from 'vue';
import { request } from './api';
import { state } from './auth';

export const labels = {
    request: 'Обращение', task: 'Задача', public: 'Публичное', private: 'Личное',
    queued: 'В очереди', in_progress: 'В работе', suspended: 'Приостановлено', review: 'На проверке',
    completed: 'Выполнено', cancelled: 'Отменено', rejected: 'Отклонено',
    low: 'Низкий', normal: 'Обычный', high: 'Высокий', urgent: 'Срочный',
};

const transitions = {
    request: { queued: ['in_progress', 'cancelled', 'rejected'], in_progress: ['completed', 'rejected'] },
    task: { queued: ['in_progress', 'suspended', 'cancelled'], in_progress: ['suspended', 'review', 'cancelled'], suspended: ['in_progress', 'cancelled'], review: ['in_progress', 'completed', 'cancelled'] },
};

export function useWorkItems() {
    const items = ref([]); const selected = ref(null); const loading = ref(false); const error = ref('');
    const filters = reactive({ kind: '', status: '', priority: '', assignee: '', created_by: '', period: '' });
    const permissions = computed(() => state.user?.permissions ?? []);
    const has = (permission) => permissions.value.includes(permission);
    const canCreateTask = computed(() => has('tasks.manage'));
    const canManage = (item) => has(item.kind === 'task' ? 'tasks.manage' : 'requests.moderate');
    const canCancel = (item) => item.kind === 'request' && item.status === 'queued' && (item.created_by === state.user?.id || canManage(item));
    const availableTransitions = (item) => (transitions[item.kind]?.[item.status] ?? []).filter((status) => {
        if (status === 'cancelled' && item.kind === 'request') return canCancel(item);
        if (status === 'completed' && item.kind === 'task') return has('tasks.close');
        return canManage(item);
    });
    const filtered = computed(() => items.value.filter((item) => Object.entries(filters).every(([key, value]) => !value || String(item[key] ?? '').includes(value))));
    async function load() { loading.value = true; error.value = ''; try { items.value = await request('work-items', {}, state.token); } catch (e) { error.value = e.message; } finally { loading.value = false; } }
    async function select(item) { error.value = ''; try { selected.value = await request(`work-items/${item.id}`, {}, state.token); } catch (e) { error.value = e.message; } }
    async function create(data) { const item = await request('work-items', { method: 'POST', body: JSON.stringify(data) }, state.token); items.value.unshift(item); selected.value = item; return item; }
    async function action(item, endpoint, data, method = 'POST') { const fresh = await request(`work-items/${item.id}/${endpoint}`, { method, body: JSON.stringify(data) }, state.token); if (fresh) { selected.value = fresh; const index = items.value.findIndex(({ id }) => id === fresh.id); if (index >= 0) items.value[index] = fresh; } else await select(item); return fresh; }
    return { items, selected, loading, error, filters, filtered, has, canCreateTask, canManage, canCancel, availableTransitions, load, select, create, action };
}
