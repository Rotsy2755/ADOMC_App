CHANGELOG
=========

All notable changes to this project are documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] — 2026-05-12

### Added
- Multi-project / multi-problem decision workspace.
- NSGA-II and ε-constraint Pareto front extraction for MOKP instances.
- AHP pairwise matrix editor with consistency ratio validation.
- Pluggable MCDM engine (TOPSIS, VIKOR, PROMETHEE II, ELECTRE I, WSM).
- One-way sensitivity and Monte-Carlo robustness analyses.
- Scenario comparison and inverse preference learning from past choices.
- PDF / CSV / JSON exports and tokenised read-only share links.
- Immutable audit log and administration console.
- REST API (API Platform 3) with JWT authentication.

### Fixed
- Reserved keyword `values` backticked for MySQL 8.3 compatibility.
- RateLimiterFactory namespace corrected in `SecurityController` and
  `ApiRateLimitListener`.
- `ExportController::json()` renamed to `exportJson()` to avoid clashing
  with `AbstractController::json()` in Symfony 7.1.
- Twig templates now use the indexed getter `Problem::getObjectiveLabels()`
  instead of numeric access on an associative array.

### Security
- Argon2id password hashing (memory-hard, GPU-resistant).
- Per-IP login rate limiting via `symfony/rate-limiter`.
- JWT keypair generation with RSA-4096 and passphrase protection.
