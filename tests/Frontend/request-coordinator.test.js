import test from 'node:test';
import assert from 'node:assert/strict';
import { RequestCoordinator } from '../../public/assets/js/request-coordinator.js';

test('superseding a key aborts the prior request and marks it stale', () => {
    const coordinator = new RequestCoordinator();
    const first = coordinator.begin('status');
    const second = coordinator.begin('status');
    assert.equal(first.signal.aborted, true);
    assert.equal(first.isCurrent(), false);
    assert.equal(second.isCurrent(), true);
    second.complete();
    assert.equal(second.isCurrent(), false);
});

test('separate interaction keys remain independent', () => {
    const coordinator = new RequestCoordinator();
    const modal = coordinator.begin('modal');
    coordinator.begin('status');
    assert.equal(modal.signal.aborted, false);
});
