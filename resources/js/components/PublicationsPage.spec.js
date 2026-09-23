import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import PublicationsPage from './PublicationsPage.vue';
import { state } from '../auth';

const response = (payload, status = 200) => ({ ok: status >= 200 && status < 300, status, json: async () => payload });

describe('PublicationsPage', () => {
    afterEach(() => { vi.unstubAllGlobals(); state.token = null; state.user = null; });

    it('shows published news and all three infoboard formats', async () => {
        state.token = 'token'; state.user = { id: 'resident', permissions: [] };
        vi.stubGlobal('fetch', vi.fn((url) => Promise.resolve(response(url === '/api/news' ? [{ id: 'news-1', title: 'Новость', body: 'Текст', status: 'published', attachments: [] }] : [{ id: 'board', title: 'Доска', body: 'Текст', status: 'published', presentation_format: 'board', attachments: [] }, { id: 'page', title: 'Страница', body: 'Текст', status: 'published', presentation_format: 'page', attachments: [] }, { id: 'banner', title: 'Баннер', body: 'Текст', status: 'published', presentation_format: 'banner', attachments: [] }]))));
        const wrapper = mount(PublicationsPage); await flushPromises();
        expect(wrapper.text()).toContain('Новость');
        await wrapper.get('input[value="infoboard"]').setValue(); await flushPromises();
        expect(wrapper.findAll('.card').map((card) => card.classes()).flat()).toEqual(expect.arrayContaining(['board', 'page', 'banner']));
    });

    it('creates a draft and only exposes management actions with permission', async () => {
        state.token = 'token'; state.user = { id: 'manager', permissions: ['news.manage'] };
        vi.stubGlobal('fetch', vi.fn((url, options = {}) => Promise.resolve(options.method === 'POST' ? response({ id: 'draft', title: 'Черновик', body: 'Текст', status: 'draft', attachments: [] }, 201) : response([]))));
        const wrapper = mount(PublicationsPage); await flushPromises();
        await wrapper.get('input').setValue('Черновик'); await wrapper.get('textarea').setValue('Текст'); await wrapper.get('form').trigger('submit'); await flushPromises();
        expect(wrapper.text()).toContain('Черновик');
        state.user = { id: 'resident', permissions: [] }; await wrapper.get('input[value="news"]').setValue(); await flushPromises();
        expect(wrapper.find('form').exists()).toBe(false);
    });
});
