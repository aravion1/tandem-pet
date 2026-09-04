import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import ActivationForm from './ActivationForm.vue';

describe('ActivationForm', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('requests a code before showing confirmation fields', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, status: 202, json: async () => null }));
        const wrapper = mount(ActivationForm);
        await wrapper.get('input[autocomplete="tel"]').setValue('+79990001122');
        await wrapper.get('form').trigger('submit');
        await flushPromises();

        expect(wrapper.text()).toContain('Код из SMS');
        expect(wrapper.find('input[type="password"]').exists()).toBe(true);
    });
});
