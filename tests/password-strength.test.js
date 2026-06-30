// Unit tests for the password strength scorer in auth.js.
// Run with: node tests/password-strength.test.js
"use strict";

const assert = require("assert");
const { scorePassword } = require("../auth.js");

// [password, expected score 0..4]
const cases = [
  ["", 0],                 // empty
  ["short", 0],            // under 8 chars
  ["manybeans", 1],        // 9 chars, lowercase only -> weak
  ["Manybeans", 2],        // adds uppercase (2 classes) -> fair
  ["Manybeans1", 3],       // adds a digit (3 classes) -> good
  ["Manybeans12!", 4],     // 12 chars + 4 classes -> strong
  ["password", 1],         // common sequence -> capped weak
  ["1234abcd", 1],         // numeric sequence prefix -> capped weak
  ["zzzzzzzz", 1],         // pure repetition -> capped weak
];

for (const [pw, expected] of cases) {
  const got = scorePassword(pw);
  assert.strictEqual(
    got,
    expected,
    `scorePassword(${JSON.stringify(pw)}) returned ${got}, expected ${expected}`
  );
}

// Monotonic-ish sanity: a clearly strong password must outscore a weak one.
assert.ok(scorePassword("Tr0ub4dour&3xtra") > scorePassword("abcdefgh"));

console.log("Password strength contract passed.");
