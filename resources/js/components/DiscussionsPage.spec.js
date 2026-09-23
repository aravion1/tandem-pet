import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import DiscussionsPage from './DiscussionsPage.vue';
import { state } from '../auth';

const discussion = { id: 'discussion-1', title: 'Дорога', visibility: 'private', status: 'open', author_id: 'user-1', members: ['user-1'], messages: [{ id: 'message-1', body: 'Починить яму', likes_count: 0, attachments: [] }], verdict: null };

function response(payload, status = 200) { return { ok: status >= 200 && status < 300, status, json: async () => payload }; }
function setupFetch(handlers = {}) {
    vi.stubGlobal('fetch', vi.fn((url, options = {}) => {
        const handler = handlers[`${options.method ?? 'GET'} ${url}`] ?? handlers[`GET ${url}`];
        return Promise.resolve(handler ? handler(options) : response([]));
    }));
}

describe('DiscussionsPage', () => {
    afterEach(() => { vi.unstubAllGlobals(); state.token = null; state.user = null; });

    it('creates public and private discussions', async () => {
        state.token = 'token'; state.user = { id: 'user-1', permissions: [] };
        let created = 0;
        setupFetch({
            'GET /api/discussions': () => response([]),
            'POST /api/discussions': (options) => { created += 1; const body = JSON.parse(options.body); return response({ ...discussion, ...body, id: `discussion-${created}` }, 201); },
        });
        const wrapper = mount(DiscussionsPage);
        await flushPromises();
        await wrapper.get('input').setValue('Первая тема');
        await wrapper.get('[data-test="create-discussion"]').trigger('submit');
        await flushPromises();
        await wrapper.get('select').setValue('private');
        await wrapper.get('input').setValue('Личная тема');
        await wrapper.get('[data-test="create-discussion"]').trigger('submit');
        await flushPromises();
        expect(fetch).toHaveBeenCalledTimes(3);
        expect(wrapper.text()).toContain('Личная тема');
    });

    it('invites a member, likes a message and closes as author', async () => {
        state.token = 'token'; state.user = { id: 'user-1', permissions: [] };
        setupFetch({
            'GET /api/discussions': () => response([{ id: discussion.id, title: discussion.title, visibility: 'private', status: 'open' }]),
            'GET /api/discussions/discussion-1': () => response(discussion),
            'POST /api/discussions/discussion-1/members': () => response(discussion),
            'POST /api/messages/message-1/like': () => response(null, 201),
            'POST /api/discussions/discussion-1/close': () => response({ ...discussion, status: 'closed' }),
        });
        const wrapper = mount(DiscussionsPage);
        await flushPromises();
        await wrapper.get('.discussion-link').trigger('click');
        await flushPromises();
        await wrapper.get('input[aria-label="Идентификаторы пользователей"]').setValue('user-2');
        await wrapper.get('[data-test="invite-members"]').trigger('submit');
        await flushPromises();
        await wrapper.get('[data-test="like"]').trigger('click');
        await flushPromises();
        await wrapper.get('[data-test="close-discussion"]').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Закрыто');
        expect(wrapper.get('textarea').attributes('disabled')).toBeDefined();
    });

    it('lets a moderator set a verdict and does not expose private data after 403', async () => {
        state.token = 'token'; state.user = { id: 'moderator', permissions: ['discussions.moderate'] };
        setupFetch({
            'GET /api/discussions': () => response([{ id: discussion.id, title: discussion.title, visibility: 'private', status: 'open' }]),
            'GET /api/discussions/discussion-1': () => response({ ...discussion, members: ['user-1', 'moderator'] }),
            'POST /api/discussions/discussion-1/verdict': () => response({ ...discussion, members: ['user-1', 'moderator'], verdict: { message_id: 'message-1', created_at: 'now' } }),
        });
        const wrapper = mount(DiscussionsPage);
        await flushPromises();
        await wrapper.get('.discussion-link').trigger('click');
        await flushPromises();
        await wrapper.get('[data-test="set-verdict"]').trigger('click');
        await flushPromises();
        expect(wrapper.text()).toContain('Вердикт');
        setupFetch({ 'GET /api/discussions': () => response([]), 'GET /api/discussions/discussion-1': () => response({ message: 'Forbidden' }, 403) });
        await wrapper.get('.discussion-link').trigger('click');
        await flushPromises();
        expect(wrapper.text()).not.toContain('Починить яму');
        expect(wrapper.text()).toContain('У вас нет доступа');
    });
});
