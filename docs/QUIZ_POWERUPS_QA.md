# Quiz Powerups QA Matrix

## Feature flags and baseline

- Ensure quiz has `allow_powerups = 1`.
- Confirm student has positive point balance in `user_point_balances`.
- Confirm powerups exist in `powerups` table (codes from migration 047).

## Purchase and redemption checks

- Attempt to redeem a powerup with enough points:
  - Expected: success, points decrease, inventory quantity increases.
- Attempt to redeem with insufficient points:
  - Expected: error `Insufficient points`, no inventory increase.
- Redeem same powerup multiple times:
  - Expected: inventory quantity increments, no duplicate-row corruption.

## Activation checks by question type

- Multiple choice:
  - `mc_halve_choices`: wrong options hidden.
  - `mc_reveal_answer`: correct option visually highlighted.
- Short answer / fill in blank:
  - `fib_scramble_hint`: scrambled word shown.
  - `fib_reveal_letters`: letter mask shown.
  - `fib_dictionary_hint`: online/cached definition shown.
- Any type:
  - `quiz_exemption`: answer can be submitted with non-penalizing behavior.

## Anti-abuse checks

- Reuse same powerup on same question after one activation:
  - Expected: blocked by per-question limit.
- Use reveal and exemption on same question:
  - Expected: blocked by incompatible-combination rule.
- Use per-attempt-limited powerup repeatedly (e.g., exemption):
  - Expected: blocked after configured limit.

## Attempt ownership and auth checks

- Try powerup APIs with another user's attempt id:
  - Expected: rejected (invalid attempt ownership).
- Call write endpoints without CSRF:
  - Expected: rejected.

## Finish and scoring checks

- Submit regular answers without powerups:
  - Expected: legacy scoring unchanged.
- Submit with exemption activation:
  - Expected: points reflect exemption behavior and finish percentage uses effective total question count.
- `show_results_immediately = 0`:
  - Expected: finish still succeeds; review visibility remains governed by quiz config.

## Dictionary hint resilience

- Simulate no internet / timeout:
  - Expected: fallback definition message returned.
- Repeat same word:
  - Expected: response served from cache source.
