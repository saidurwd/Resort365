/*
 * POS scenario (Step 7.5): a cashier on the Main Restaurant's cashier desk opens a takeaway order, adds a few dishes, sends them to
 * the kitchen, prints the bill and takes cash, and the floor screen polls as it does every few seconds. All cashiers share the
 * registered device (as the staff of one desk do) and each signs in with their own PIN.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE, csrf, jsonHeaders, pick, record } from './lib.js';

let ready = false;

export function pos(fixture, device) {
    if (!ready) {
        http.cookieJar().set(BASE, 'pos_device', device);
        const person = fixture.people.pos[(__VU - 1) % fixture.people.pos.length];
        const lock = record(http.get(`${BASE}/pos`, { tags: { name: 'pos: lock screen' } }), 'pos: lock screen');
        const signedIn = record(http.post(`${BASE}/pos/sign-in`, { _token: csrf(lock), user_id: person.id, pin: person.pin }, { tags: { name: 'pos: sign in' } }), 'pos: sign in');
        ready = check(signedIn, { 'cashier signed in': (r) => r.status === 200 && !r.url.endsWith('/pos') });

        if (!ready) {
            return;
        }
    }

    const floor = record(http.get(`${BASE}/pos/floor`, { tags: { name: 'pos: floor' } }), 'pos: floor');
    const opened = record(http.post(`${BASE}/pos/orders`, { _token: csrf(floor), type: 'takeaway' }, { tags: { name: 'pos: open order' } }), 'pos: open order');
    const id = (opened.url.match(/\/pos\/orders\/(\d+)/) || [])[1];

    if (!check(opened, { 'order opened': () => id !== undefined })) {
        return;
    }

    const headers = { headers: jsonHeaders(csrf(opened)) };
    const api = `${BASE}/pos/api/orders/${id}`;

    for (let line = 0; line < 3; line++) {
        record(http.post(`${api}/lines`, JSON.stringify({ item_id: pick(fixture.items), quantity: 1 + Math.floor(Math.random() * 2) }), { ...headers, tags: { name: 'pos: add line' } }), 'pos: add line');
        sleep(0.5);
    }

    record(http.post(`${api}/send`, '{}', { ...headers, tags: { name: 'pos: send to kitchen' } }), 'pos: send to kitchen');
    record(http.get(`${BASE}/pos/api/floor`, { headers: headers.headers, tags: { name: 'pos: poll floor' } }), 'pos: poll floor');
    sleep(1);

    const printed = record(http.post(`${api}/bill/print`, JSON.stringify({ mode: 'none' }), { ...headers, tags: { name: 'pos: print bill' } }), 'pos: print bill');
    const bill = printed.status === 200 ? printed.json('billing.bills.0') : null;

    if (bill) {
        const paid = record(http.post(`${BASE}/pos/api/bills/${bill.id}/payments`, JSON.stringify({ method: 'cash', amount: bill.due, tendered: bill.due }), { ...headers, tags: { name: 'pos: take cash' } }), 'pos: take cash');
        check(paid, { 'bill settled': (r) => r.status === 200 });
    }

    record(http.get(`${BASE}/pos/api/ready`, { headers: headers.headers, tags: { name: 'pos: poll ready' } }), 'pos: poll ready');
    sleep(2);
}
