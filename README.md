# Rewarding

Rewarding is the SmartResponsor Symfony component for non-monetary loyalty and reward programs: points, tiers, earn/redeem rules, expiry, reversals, and benefits.

It supports standalone Symfony execution and reusable bundle composition. Product boundaries are in `docs/architecture/001-boundary.adoc`.

Current RC materialization includes the durable non-monetary points ledger core: mapped reward account and immutable transaction entities, DBAL append-only persistence, idempotent earn/redeem/expire/adjust/reverse operations, one-reversal-per-source enforcement, optimistic ledger versioning, and PostgreSQL migrations. It intentionally introduces no generic CRUD controllers, product HTTP routes, money-like Store Credit, coupon, promotion, or payment ownership.

