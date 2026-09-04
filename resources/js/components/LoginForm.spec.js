import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import LoginForm from './LoginForm.vue';

describe('LoginForm', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('submits credentials and emits the token', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({ token: 'token' }) }));
        const wrapper = mount(LoginForm);
        await wrapper.get('input[autocomplete="tel"]').setValue('+79990001122');
        await wrapper.get('input[type="password"]').setValue('strong-password');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.emitted('authenticated')).toEqual([['token']]);
    });

    it('shows an API error', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false, status: 401, json: async () => ({ message: 'Неверный пароль' }) }));
        const wrapper = mount(LoginForm);
        await wrapper.get('input[autocomplete="tel"]').setValue('+79990001122');
        await wrapper.get('input[type="password"]').setValue('strong-password');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.get('[role="alert"]').text()).toBe('Неверный пароль');
    });
});
