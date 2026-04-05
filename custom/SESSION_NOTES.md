# Session Continuation Notes

## Project
Drupal 11 upgrade — custom module code review and fixes.
- Repo: `/extra/PhpstormProjects/d10d11.dsd.didymodesigns.com.au/`
- Branch: `chore/fix-custom-module-drupal-11-upgrade-blockers`
- Scope: **`custom/` directory only** — do not touch `composer.json`, `web/`, or anything outside `custom/`
- Never commit to `main`. No PR process — push directly to current branch.
- QA guardrails: GrumPHP runs PHPCS (Drupal + DrupalPractice) and PHPStan level 2 on pre-commit.

## Modules
- `custom/process/` — Process entity + 7 REST resources + service
- `custom/decision_support/` — DecisionSupport entity + 7 REST resources + service (depends on process + decision_support_file)
- `custom/decision_support_file/` — DecisionSupportFile entity + 3 REST resources + service

## Current Status — 2026-04-03
**All review issues resolved. PHPCS and PHPStan both pass clean. Branch pushed.**

## Work Completed

### Commit 1 — Critical bugs + security fixes
- Bug #2: Removed unreachable `return` after try/catch in ArchiveDecisionSupportResource
- Bug #3: Fixed logger array placeholders (wrong variable passed)
- Bug #4: Fixed double-save orphaned revision pattern in createProcess, duplicateProcess, createDecisionSupport
- Bug #5: Fixed `getUid()` to use `getOwnerId()` instead of field value
- Security #6 (P0): All 15 REST endpoints got specific permissions (not 'access content')
- Security #7 (P1): Added file access check in DecisionSupportFileService
- Security #8 (P1): Added `'vid' => 'status'` constraint to setRevisionStatus() in both entities
- Security #9 (P1): Deleted `custom/create-consumer.php` (loose OAuth script)

### Commit 2 — P2 reliability fixes
`77847df`
- #11: Moved getDecisionSupportFile() outside steps loop (was N+1 query)
- #12: Replaced Class::loadMultiple() with entity queries + accessCheck(TRUE) in list methods
- #16: Removed deprecated `'text_processing' => 0` from string fields (3 each in Process + DecisionSupport)

### Commit 3 — P3 quality fixes
`00f4cae`
- #21: Fixed interface docblocks (wrong @return types and descriptions)
- #23: Fixed revision_status view display formatter ('author' → 'entity_reference_label')
- #24: Added `: array` return type to urlRouteParameters() in both entities
- #28: Added dependencies to all three info.yml files
- #29: Replaced all static Entity::load() calls with entityTypeManager + @var annotations
- #19: Removed unused KeyValueStore injection from all 15 REST resource files
- #20: Removed DCG boilerplate @DCG comment blocks from all 15 REST resources
- #30: Switched concrete service class type hints to interfaces in all 15 REST resources

### Commit 4 — Soft-archive for DecisionSupport
`e5d0529`
- #1: archiveDecisionSupport() converted from hard-delete to soft-archive (consistent with process + file modules)

### Commit 5 — PHPStan ListBuilder pre-commit fix
`9eddbaa`
- Fixed pre-existing PHPStan errors in ListBuilder files (were blocking pre-commit hook)

### Commit 6 — Final docblock fixes
`1c01326`
- Fixed missing `@param` types in all three service interfaces (ProcessServiceInterface, DecisionSupportServiceInterface, DecisionSupportFileServiceInterface)
- Also fixed `@param` ordering to match method signatures (Drupal CS requires params grouped in signature order)
- PHPCS and PHPStan now both pass clean

## Runtime fix — 2026-04-03
After pushing the soft-archive fix, `drush cr` was required on the live site to rebuild the compiled service container. The container had cached the old 2-argument constructor for `DecisionSupportService`; the fix added a 3rd argument (`@decision_support_file.service`).

## SKIPPED — Out of Scope or Acceptable
- **#17** — `composer.json` updates: out of scope (Drupal core update is a separate task)
- **#22** — Entity field snake_case (`decisionSupportId`, `stepId`): breaking DB schema change
- **#25** — Static `\Drupal::` calls in entity classes: acceptable Drupal pattern
- **#27** — Plain integer FK in DecisionSupportFile: architectural design decision
- **#26** — `uniqid()` as UUID: quality only, not a blocker

## Code Review Report
Full report at `custom/CODE_REVIEW.md`
