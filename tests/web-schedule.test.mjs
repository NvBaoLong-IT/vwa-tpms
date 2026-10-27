import test from 'node:test';
import assert from 'node:assert/strict';
import { startWebSchedule } from '../resources/js/web-schedule.js';

const flush = () => new Promise(resolve => setImmediate(resolve));

test('web tick sends a protected POST and waits for completion before scheduling another', async () => {
    const requests = [];
    const timers = [];
    let complete;
    startWebSchedule({ url: '/schedule/tick', csrfToken: 'test-token', isVisible: () => true,
        fetchRequest: (url, options) => { requests.push({ url, options }); return new Promise(resolve => { complete = resolve; }); },
        schedule: (callback, delay) => timers.push({ callback, delay }),
    });
    assert.equal(requests.length, 1);
    assert.equal(timers.length, 0);
    assert.equal(requests[0].options.method, 'POST');
    assert.equal(requests[0].options.credentials, 'same-origin');
    assert.equal(requests[0].options.headers['X-CSRF-TOKEN'], 'test-token');
    complete({ status: 200 });
    await flush();
    assert.equal(timers.length, 1);
    assert.equal(timers[0].delay, 60000);
});

for (const response of [{ status: 401 }, { status: 403 }, { status: 419 }, { type: 'opaqueredirect' }]) {
    test(`web tick stops after session or access rejection ${response.status ?? response.type}`, async () => {
        const timers = [];
        startWebSchedule({ url: '/schedule/tick', csrfToken: 'token', isVisible: () => true,
            fetchRequest: async () => response, schedule: callback => timers.push(callback),
        });
        await flush();
        assert.equal(timers.length, 0);
    });
}

test('web tick retries network failures and skips hidden pages', async () => {
    let visible = true;
    let calls = 0;
    const timers = [];
    startWebSchedule({ url: '/schedule/tick', csrfToken: 'token', isVisible: () => visible,
        fetchRequest: async () => { calls++; throw new Error('offline'); },
        schedule: callback => timers.push(callback),
    });
    await flush();
    assert.equal(calls, 1);
    visible = false;
    await timers.shift()();
    assert.equal(calls, 1);
    visible = true;
    await timers.shift()();
    assert.equal(calls, 2);
});
