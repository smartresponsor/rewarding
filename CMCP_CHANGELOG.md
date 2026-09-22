# CMCP Change Journal

## Canonization read

Read current local Canonization `AGENTS.md`, `README.md`, `MANIFEST.json`, Canon000/007/009/018/020/022/023/024/025/026/029/031/032/033/034/039/043, and the corresponding current executable Gating rules.

## Target-to-canon mapping

- Composer: `rewarding/reward`.
- Namespace: `App\\Rewarding\\`.
- Subject: `Reward*`.
- Dual runtime: standalone Symfony plus reusable `RewardingBundle`.
- Mandatory sibling dependencies use symlinked path repositories and exact `dev-master`.
- Production manifest uses package dependencies only.
- Generic CRUD remains in Cruding.
- Store Credit remains outside Rewarding pending Walleting audit; no Crediting repository is created.

## Baseline repository state

The target repository did not exist before materialization. Requested collision aliases returned no canonical repository-file evidence, and workspace creation succeeded without overwrite.

## Created

Composer manifests and lockfile, Symfony bootstrap/bundle surfaces, component-owned `.gating/profile.yaml`, quality tooling, smoke test, ignore baseline, boundary/canon/roadmap/benchmark docs, Git repository metadata on `master`, and this journal.

## Risks

- Composer install depends on sibling package consistency and external resolution.
- No speculative reward model is created in this wave.

## Gates to run

Composer validation/install, PHP lint, PHPUnit, coverage where driver permits, PHPStan, PHP-CS-Fixer dry-run, Gating, and Symfony boot/container checks.

## Validation result

All requested component-local gates passed after repair: Composer validation/install, explicit PHP syntax lint, PHPUnit, Xdebug branch coverage with persistent summary, PHPStan, PHP-CS-Fixer dry-run, selected Gating rules (17/17; zero failures/warnings/skips), Symfony boot, YAML lint, and container lint.

## 2026-09-20 capability implementation pass

Reconnaissance re-read current Canonization role-first, subject-prefix, PSR-4, dependency-integrity, typed-boundary, documentation/runtime, Composer identity, and no-alternative-layer rules plus the current Rewarding baseline and mandatory helper package contracts. Market comparison confirmed immutable ledgers, idempotent mutation boundaries, traceability, expiry journals, and checkout-safe redemption semantics as the RC-critical baseline.

Selected RC-critical work: M1 plus the coherent M2 ledger-operation slice. Added RewardAccountEntity, immutable RewardTransactionEntity, transaction type vocabulary, append-only optimistic repository contract, ledger-derived balance, replay-safe earn/redeem/expire/adjust/reverse behavior, explicit idempotency conflicts, insufficient-balance rejection, and concurrent-version rejection tests. No money-like Store Credit, coupon, promotion, generic CRUD, Domain/Application/Infrastructure, or Port/Adapter ownership was introduced.

Remaining material risk: the repository contract still needs a durable atomic persistence implementation and migration before M1/M2 can be considered complete in production. Rules/tiers/benefits and commerce/Walleting acceptance remain later milestones.

Validation: Composer validate strict GREEN; changed PHP lint GREEN; PHPUnit GREEN (6 tests, 10 assertions); Xdebug branch coverage run GREEN; PHPStan GREEN; PHP-CS-Fixer dry-run GREEN; Gating GREEN (17/17, zero warnings); Symfony container lint GREEN; YAML lint GREEN. No browser/mobile behavior changed, so UI behavioral and visual evidence are not applicable in this slice.

## 2026-09-22 — durable ledger RC closure

- Re-ran the repository against current Gating master through Composer and migrated the consumer away from embedded `.gating` runtime/policy state; `.gating/` is artifact-only and repository-specific profile ownership is `config/reward_gating_profile.json`.
- Applied Canon004 terminal persistence naming: `RewardAccountEntity` and `RewardTransactionEntity`.
- Added canonical standalone behavioral tooling with Symfony Test Pack, Panther, Playwright, a real front controller, and a route-free HTTP runtime smoke that verifies Symfony-owned 404 behavior without inventing product routes.
- Added reproducible Canon042 inventories; UI eligibility is truthfully empty because Rewarding owns no renderer/UI surface.
- Closed the prior M1/M2 durable-persistence gap: Doctrine-mapped account/transaction entities, PostgreSQL `data` plus SQLite `infra`, initial migration, DBAL `RewardTransactionRepository`, account-scoped ledger version uniqueness, idempotency uniqueness, transactional append, and unique-race recovery.
- Added DBAL integration coverage for ordering, lookup, hydration, idempotent replay/conflict, stale version, primary-key collision, and race recovery; expanded M2 service/entity invariant coverage.
- Final aggregate `composer quality`: GREEN — PHPUnit 24 tests / 52 assertions, PHPStan 0 errors, PHP-CS-Fixer clean, Playwright 1/1, Gating 68 rules with 0 failures and 0 warnings.
- Canon040 evidence: 99.2% lines, 80.0% methods, 93.5% branches. Canon041/042/052 all PASS.
- Doctrine mapping validation and Symfony container lint PASS. The earlier standalone credential blocker was later resolved through the host application's canonical PostgreSQL credential authority; see the current RC verification refresh below.
- Growth remains separate: versioned earn/redeem rules, tiers/status progression, benefits, expiry scheduling/source-lot policy, commerce references, and Walleting boundary acceptance.

## 2026-09-22 — Current RC verification refresh

- Re-read current Rewarding boundary, canon map, roadmap/competitor baseline, Composer/runtime configuration, durable ledger service/repository, current migration diff, and the shared dependency/Canonization/Gating contour.
- Current dirty baseline was preserved: migration namespace/config were already changing from generic DoctrineMigrations to component-owned App\\Rewarding\\Migrations; generated config/reference.php was also present.
- Market maturity baseline confirms accrual, redemption, expiry, transaction journals, tier assessment, and reversals/adjustments as loyalty concerns; money-like balances, coupons/promotions, payments, and identity truth remain outside Rewarding.
- The migration namespace change passed full repository acceptance and is retained as RC-critical package/schema identity hardening.
- composer quality: PASS; PHPUnit 24/24 with 52 assertions, PHPStan clean, CS clean, Playwright 1/1, behavioral evidence generated, Gating 68 rules with 0 failed / 0 warning.
- Fresh coverage: 99.2% lines, 80.0% methods, 93.5% branches; Canon040 passes.
- Doctrine mapping validation: PASS.
- Host application `www/app` was confirmed as the credential authority for the platform PostgreSQL `app` database; its canonical `tools/resolve-database-url.php` resolver was used without exposing or persisting secrets.
- Because the host Symfony Kernel was independently blocked by an unrelated `App\\RelatingBundle` autoload mismatch, Rewarding migration `App\\Rewarding\\Migrations\\Version20260922195700` was applied transactionally through `psql` using the host resolver, with the exact migration SQL and Doctrine migration metadata recorded in the same transaction.
- PostgreSQL verification PASS: `reward_account` and `reward_transaction` exist with expected columns; primary, member-reference, ledger-version, idempotency, and account/occurred indexes exist; `doctrine_migration_versions` records the Rewarding version as executed.
- Temporary host-side migration runner/config artifacts were moved into ignored `var/`; no host credential or new tracked host artifact remains from this operation.
- config/reference.php is generated Symfony evidence and is now ignored rather than committed.

