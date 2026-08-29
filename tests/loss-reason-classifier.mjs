import assert from 'node:assert/strict';
import LossReasonClassifier from '../_public/assets/loss-reason-classifier.js';

assert.equal(LossReasonClassifier.isBlunder(20, 400), true);
assert.equal(LossReasonClassifier.isBlunder(0, 250), true);
assert.equal(LossReasonClassifier.isBlunder(-200, 400), false);
assert.equal(LossReasonClassifier.isBlunder(20, 150), false);
assert.equal(LossReasonClassifier.isBlunder(20, 200), false);
assert.equal(LossReasonClassifier.isBlunder(100000, 100000), true);

console.log('All classifier tests passed.');
