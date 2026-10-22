/*
 * Booking scenario (Step 7.5): a reservations agent looks at availability and the tape chart, then books a random free room through
 * the booking wizard for a random stay up to a year ahead. A room another agent took a moment earlier is a refusal, not an error.
 */
import http from 'k6/http';
import { check, sleep } from 'k6';
import { BASE, csrf, inDays, pick, record, refusals, signIn } from './lib.js';

let signedIn = false;

export function booking(fixture) {
    if (!signedIn) {
        signIn(fixture.people.booking[(__VU - 1) % fixture.people.booking.length].email);
        signedIn = true;
    }

    const checkIn = inDays(20 + Math.floor(Math.random() * 340));
    const nights = 1 + Math.floor(Math.random() * 3);
    const checkOut = addNights(checkIn, nights);

    record(http.get(`${BASE}/reservation/availability?check_in=${checkIn}&check_out=${checkOut}&adults=2`, { tags: { name: 'availability' } }), 'availability');
    record(http.get(`${BASE}/reservation/tape-chart?start=${checkIn}`, { tags: { name: 'tape chart' } }), 'tape chart');
    sleep(1);

    const datesPage = record(http.get(`${BASE}/reservation/bookings/new`, { tags: { name: 'wizard: dates' } }), 'wizard: dates');
    const plan = (datesPage.body.match(/name="rate_plan"[\s\S]*?<option value="(\d+)"/) || [])[1];
    const choose = record(http.post(`${BASE}/reservation/bookings/new`, { _token: csrf(datesPage), check_in: checkIn, check_out: checkOut, adults: 2, children: 0, rate_plan: plan }, { tags: { name: 'wizard: dates →' } }), 'wizard: dates →');
    const rooms = [...choose.body.matchAll(/name="rooms\[\]" value="(\d+)"/g)].map((match) => match[1]);

    if (rooms.length === 0) {
        refusals.add(1, { name: 'no room free' });
        http.post(`${BASE}/reservation/bookings/new/reset`, { _token: csrf(choose) }, { tags: { name: 'wizard: reset' } });

        return;
    }

    const guest = record(http.post(`${BASE}/reservation/bookings/new/choose`, { _token: csrf(choose), 'rooms[]': pick(rooms) }, { tags: { name: 'wizard: choose →' } }), 'wizard: choose →');
    const pricing = record(http.post(`${BASE}/reservation/bookings/new/guest`, { _token: csrf(guest), guest_mode: 'existing', guest_id: pick(fixture.guests), source: 'phone' }, { tags: { name: 'wizard: guest →' } }), 'wizard: guest →');
    const confirm = record(http.post(`${BASE}/reservation/bookings/new/pricing`, { _token: csrf(pricing), action: 'continue', deposit_percent: '30' }, { tags: { name: 'wizard: pricing →' } }), 'wizard: pricing →');
    sleep(1);
    const booked = record(http.post(`${BASE}/reservation/bookings/new/confirm`, { _token: csrf(confirm) }, { tags: { name: 'wizard: book' } }), 'wizard: book');

    if (/\/reservation\/bookings\/\d+/.test(booked.url)) {
        check(booked, { 'booking page shown': (r) => r.status === 200 });
    } else {
        // The room went to another agent between choosing and booking: the wizard says so and the agent starts again.
        refusals.add(1, { name: 'room taken meanwhile' });
        http.post(`${BASE}/reservation/bookings/new/reset`, { _token: csrf(booked) }, { tags: { name: 'wizard: reset' } });
    }

    sleep(2);
}

function addNights(date, nights) {
    const day = new Date(`${date}T00:00:00Z`);
    day.setUTCDate(day.getUTCDate() + nights);

    return day.toISOString().slice(0, 10);
}
