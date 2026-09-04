import { reactive } from 'vue';
import { request } from './api';

const tokenKey = 'settlement.token';
const state = reactive({ token: sessionStorage.getItem(tokenKey), user: null });

export function can(permission) {
    return state.user?.permissions?.includes(permission) ?? false;
}

export async function refreshUser() {
    if (!state.token) return null;
    state.user = await request('auth/me', {}, state.token);
    return state.user;
}

export async function acceptToken(token) {
    state.token = token;
    sessionStorage.setItem(tokenKey, token);
    return refreshUser();
}

export function signOut() {
    sessionStorage.removeItem(tokenKey);
    state.token = null;
    state.user = null;
}

export { state };
