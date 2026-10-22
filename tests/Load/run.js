/*
 * The load test (Step 7.5): 25 reservations agents and 25 cashiers at once for five minutes. It passes with no server errors,
 * every check passing, and 95% of requests under the response-time budget. See tests/Load/README.md.
 *
 *   k6 run tests/Load/run.js                     (local: http://rodela.resort365.test)
 *   BASE_URL=https://rodela.staging.example k6 run tests/Load/run.js
 */
import http from 'k6/http';
import { BASE, csrf } from './lib.js';
import { booking } from './booking.js';
import { pos } from './pos.js';

const fixture = JSON.parse(open('./fixture.json'));
const users = Number(__ENV.USERS_PER_SCENARIO || 25);
const duration = __ENV.DURATION || '5m';

export const options = {
    scenarios: {
        booking: { executor: 'constant-vus', vus: users, duration, exec: 'bookingScenario' },
        pos: { executor: 'constant-vus', vus: users, duration, exec: 'posScenario' },
    },
    thresholds: {
        server_errors: ['count==0'],
        checks: ['rate==1'],
        http_req_duration: [`p(95)<${__ENV.P95_MS || 800}`],
    },
    // Each person stays signed in from one iteration to the next, as a real agent or cashier does.
    noCookiesReset: true,
    // Locally the .test domains point at this machine.
    hosts: __ENV.BASE_URL ? {} : { 'rodela.resort365.test': '127.0.0.1' },
};

/** Registers the cashier desk once; every cashier uses the same device cookie. */
export function setup() {
    const page = http.get(`${BASE}/pos/register`);
    http.post(`${BASE}/pos/register`, { _token: csrf(page), token: fixture.device_token });
    const cookie = http.cookieJar().cookiesForURL(BASE).pos_device;

    if (!cookie || cookie.length === 0) {
        throw new Error('The POS device did not register: run `php tests/Load/prepare.php` after seeding the demo.');
    }

    return { device: cookie[0] };
}

export function bookingScenario() {
    booking(fixture);
}

export function posScenario(data) {
    pos(fixture, data.device);
}
