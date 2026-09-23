import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import WorkItemDetail from './WorkItemDetail.vue';
import WorkItemForm from './WorkItemForm.vue';

const queuedRequest = { id: '1', created_by: 'author', kind: 'request', title: 'Личное', description: 'Текст', visibility: 'private', status: 'queued', priority: 'normal', assignees: [], attachments: [], comments: [], history: [{ id: 'h', field: 'created', old_value: null, new_value: 'request' }] };

describe('work items UI', () => {
    it('creates a personal request with its addressee', async () => {
        const wrapper = mount(WorkItemForm);
        await wrapper.get('select').setValue('request');
        await wrapper.get('input[required]').setValue('Личный вопрос');
        await wrapper.get('textarea').setValue('Описание');
        const selects = wrapper.findAll('select');
        await selects[1].setValue('private');
        await wrapper.get('[aria-label="Адресат личного обращения"]').setValue('recipient');
        await wrapper.get('form').trigger('submit');
        expect(wrapper.emitted('submit')[0][0]).toMatchObject({ kind: 'request', visibility: 'private', addressee_user_id: 'recipient' });
    });

    it('hides cancellation after a request is in progress and shows recorded history', () => {
        const item = { ...queuedRequest, status: 'in_progress' };
        const wrapper = mount(WorkItemDetail, { props: { item, transitions: [] } });
        expect(wrapper.text()).not.toContain('Отменено');
        expect(wrapper.text()).toContain('История');
        expect(wrapper.text()).toContain('created');
    });

    it('offers conversion only for a manager', () => {
        const hidden = mount(WorkItemDetail, { props: { item: queuedRequest, canConvert: false, transitions: [] } });
        expect(hidden.text()).not.toContain('Преобразовать');
        const allowed = mount(WorkItemDetail, { props: { item: queuedRequest, canConvert: true, transitions: [] } });
        expect(allowed.text()).toContain('Преобразовать в задачу');
    });

    it('does not render forbidden status actions', () => {
        const wrapper = mount(WorkItemDetail, { props: { item: queuedRequest, transitions: [] } });
        expect(wrapper.find('.actions').exists()).toBe(false);
    });
});
