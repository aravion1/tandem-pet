import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import AccessNav from './AccessNav.vue';

const stubs = { RouterLink: { props: ['to'], template: '<a><slot /></a>' } };

describe('AccessNav', () => {
    it('hides role management without its permission', () => {
        const wrapper = mount(AccessNav, { props: { user: { permissions: ['users.manage'] } }, global: { stubs } });
        expect(wrapper.text()).toContain('Жители');
        expect(wrapper.text()).not.toContain('Роли');
    });
});
