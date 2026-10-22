/*
 * Shared helpers for the k6 load test (Step 7.5, tests/Load/README.md).
 */
import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

export const BASE = __ENV.BASE_URL || 'http://rodela.resort365.test';

/** Responses the server failed on (5xx). The test passes only when there are none. */
export const serverErrors = new Counter('server_errors');

/** Refusals the people would see as a message (taken room, validation): expected now and then, not failures. */
export const refusals = new Counter('refusals');

export function record(response, name) {
    if (response.status >= 500) {
        serverErrors.add(1, { name });
    } else if (response.status === 422 || response.status === 429) {
        refusals.add(1, { name, status: String(response.status) });
    }

    return response;
}

/** The CSRF token of a page (the meta tag or a form's hidden field). */
export function csrf(response) {
    const meta = response.body && response.body.match(/name="csrf-token" content="([^"]+)"/);
    const field = response.body && response.body.match(/name="_token" (?:autocomplete="off" )?value="([^"]+)"/);

    return (meta && meta[1]) || (field && field[1]) || '';
}

/** Signs in through the normal sign-in form; the session cookie stays in this virtual user's jar. */
export function signIn(email, password = 'password') {
    const page = record(http.get(`${BASE}/login`, { tags: { name: 'login page' } }), 'login page');
    const response = record(http.post(`${BASE}/login`, { _token: csrf(page), email, password }, { tags: { name: 'sign in' } }), 'sign in');

    check(response, { 'signed in': (r) => r.status === 200 && !r.url.endsWith('/login') });

    return response;
}

/** JSON request headers with the page's CSRF token. */
export function jsonHeaders(token) {
    return { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': token };
}

export function pick(list) {
    return list[Math.floor(Math.random() * list.length)];
}

/** A date `days` from today, as Y-m-d. */
export function inDays(days) {
    const date = new Date();
    date.setUTCDate(date.getUTCDate() + days);

    return date.toISOString().slice(0, 10);
}
