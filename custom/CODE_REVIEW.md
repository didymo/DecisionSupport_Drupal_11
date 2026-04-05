# Code Review: Custom Modules (Drupal 10 → 11 Upgrade)

**Date:** 2026-03-11
**Modules reviewed:** `decision_support`, `decision_support_file`, `process`
**Reviewer:** Claude Code
**Branch:** `chore/fix-custom-module-drupal-11-upgrade-blockers`

---

## Summary

Three custom modules implementing a REST API for a decision-support workflow system.
The code was generated with DCG (Drupal Code Generator) tooling and subsequently
modified. The upgrade to Drupal 11 compatibility has been partially addressed, but
significant issues remain across security, correctness, code quality, and repo hygiene.
Findings are grouped by severity.

---

## CRITICAL BUGS

### 1. Delete/Archive semantics are undefined — BLOCKED: Needs requirements

**Files:** `decision_support/src/Services/DecisionSupport/DecisionSupportService.php:244`
`process/src/Services/ProcessService/ProcessService.php:195`
`decision_support_file/src/Services/DecisionSupportFile/DecisionSupportFileService.php:117`

**Status: ⛔ BLOCKED — Do not implement until requirements are confirmed.**

The three modules currently implement "delete" inconsistently:

- `deleteProcess()` — soft-delete: renames label `"- Archived"`, sets `status = FALSE`, sets `revision_status = "Archived"`
- `deleteDecisionSupportFile()` — soft-delete: renames label `"- Archived"`, sets `visible = FALSE`
- `archiveDecisionSupport()` — **hard-delete**: calls `->delete()`, permanently destroys entity and all revisions despite the method name, log message, and REST resource name all saying "archive"

This inconsistency is a symptom of an unresolved design question. This system handles
decision support processes that may have **legal retention obligations** or
**legal destruction requirements**, and these can conflict. The correct implementation
depends on answers to the following questions — which must be resolved by the
product/legal owner before any code is written:

**Requirements that must be defined per entity type (`Process`, `DecisionSupport`, `DecisionSupportFile`):**

1. **What does "delete" mean to the user?**
   - Hidden from their UI but retained in the system (soft-delete/archive)
   - Permanently removed from the system (hard delete)
   - Different behaviour depending on record type, role, or legal category?

2. **What are the legal retention obligations?**
   - Minimum retention periods (e.g., statutory 7-year audit requirements)
   - Which roles can view archived/hidden records after deletion? (Auditors? Admins? Nobody?)
   - Is a destruction schedule required — records auto-purged after N years?

3. **Right to erasure vs. retention obligations**
   - These can conflict (e.g., a GDPR erasure request against a statutory audit requirement)
   - Which takes precedence? Is that a policy decision or a system-enforced rule?

4. **Cascade behaviour when a parent is "deleted"**
   - If a `Process` is archived, what happens to its `DecisionSupport` instances?
   - If a `DecisionSupport` is archived, what happens to its `DecisionSupportFile` attachments?
   - Attached files may carry independent legal retention obligations

5. **Audit trail**
   - Is a log of who deleted what, when, and why required?
   - Currently no audit log of destructive operations exists

**Likely implementation paths (pending requirements):**

| Requirement | Implementation |
|-------------|---------------|
| Hide from user, retain for audit | Soft-delete: set status/visible flag, filter from list endpoints |
| Legal destruction (scheduled) | Hard delete via a scheduled Drupal queue or Drush command with role-gated access |
| Both, depending on record | Separate `archive` and `destroy` endpoints with distinct permissions |
| Audit log required | Drupal core `dblog` entries or a dedicated audit entity |

**Immediate action:** The current `archiveDecisionSupport()` hard-delete is the most
dangerous state — it destroys data while logging "moved to archived". Until
requirements are confirmed, this discrepancy should be surfaced to stakeholders.
No code change should be made to any delete/archive operation until the decision
matrix is agreed.

---

### 2. Unreachable return statement after completed try/catch

**File:** `decision_support/src/Plugin/rest/resource/ArchiveDecisionSupportResource.php:139`

```php
public function delete($decisionSupportId): ModifiedResourceResponse {
    ...
    try {
        ...
        return new ModifiedResourceResponse(NULL, 204);  // ← returns here
    }
    catch (HttpExceptionInterface $e) { throw $e; }
    catch (\Throwable $e) { throw new HttpException(500, ...); }
    return new ModifiedResourceResponse(NULL, 204);  // ← UNREACHABLE DEAD CODE
}
```

Dead code indicating a structural misunderstanding of try/catch/return. PHPStan (even
at level 2) should flag this.

---

### 3. Logger placeholder receives an array instead of a scalar

**File:** `process/src/Services/ProcessService/ProcessService.php:111,143`
**File:** `decision_support/src/Services/DecisionSupport/DecisionSupportService.php:209`

```php
$returnValue['entityId'] = $process->id();
$this->logger->notice('Created new Process entity with ID @id.', ['@id' => $returnValue]);
```

`$returnValue` is `['entityId' => 123]`, not a scalar. The `@id` placeholder receives
an array, which will produce "Array" or a PHP notice in the log. The intent was
`$returnValue['entityId']` or simply `$process->id()`.

---

### 4. Double-save with orphaned first revision

**Files:** `ProcessService.php:95-108`, `DecisionSupportService.php:189-206`

```php
$process = Process::create($data);
$process->save();               // ← Revision 1: no json_string, wrong state
// ... build json string ...
$process->setJsonString($processJsonstring);
$process->setRevisionStatus($data['revision_status']);
$process->save();               // ← Revision 2: correct state
```

Every create operation produces two database saves and two revisions. The first
revision is incomplete (no `json_string`, no `revision_status`). This pollutes the
revision history with meaningless "draft" states.

---

### 5. `getUid()` returns wrong value on `DecisionSupport` entity

**File:** `decision_support/src/Entity/DecisionSupport.php:344`

```php
public function getUid() {
    return $this->get('uid')->value;
}
```

For `entity_reference` fields, `->value` resolves to the first field item's `value`
property, which is `NULL` for entity references (the target ID is in `target_id`).
The correct approach is `$this->getOwnerId()` (already available via `EntityOwnerTrait`)
or `$this->get('uid')->target_id`. This method likely always returns `NULL`.

---

## SECURITY ISSUES

### 6. REST endpoints use `access content` instead of entity-specific permissions

**All 15 REST resource files** — e.g.:

```php
if (!$this->currentUser->hasPermission('access content')) {
    throw new AccessDeniedHttpException();
}
```

`access content` is the most permissive Drupal permission — typically granted to all
authenticated users and often anonymous users. Every write operation (create, update,
archive) in all three modules uses this check, bypassing the properly-defined entity
permission system entirely.

The `DecisionSupportAccessControlHandler`, `ProcessAccessControlHandler`, and
`DecisionSupportFileAccessControlHandler` all define granular permissions
(`create decision_support_entity`, `edit decision_support_entity`,
`delete decision_support_entity`, etc.) — but the REST layer ignores them.

**Impact:** Any authenticated user can create, read, modify, or destroy any process or
decision support entity they know the ID of, regardless of role.

**Correct approach:** Check the specific permission appropriate to the operation
(e.g., `create decision_support_entity` for POST, `edit decision_support_entity` for
PATCH) or call `$entity->access('update', $this->currentUser)` to go through the ACL
handler.

---

### 7. File attachment with no ownership or access check

**File:** `decision_support_file/src/Services/DecisionSupportFileService.php:92`

```php
$file_entity = $this->entityTypeManager->getStorage('file')->load($data['fid']);
```

Any authenticated user can attach any file entity (by its numeric ID) to any decision
support, regardless of who uploaded the file. There is no call to
`$file_entity->access('view', $account)` or equivalent. A user could attach files
uploaded by other users, or files that were meant to be private.

---

### 8. `setRevisionStatus()` matches terms across all vocabularies

**Files:** `process/src/Entity/Process.php:349-358`, `decision_support/src/Entity/DecisionSupport.php:374-383`

```php
$terms = \Drupal::entityTypeManager()
    ->getStorage('taxonomy_term')
    ->loadByProperties(['name' => $term_name]);
```

This loads taxonomy terms by name without constraining to the `status` vocabulary. If
any other vocabulary contains a term with the same name (e.g., "Archived"), the wrong
term will be silently applied. The query should include `'vid' => 'status'`.

---

### 9. `create-consumer.php` committed to repository

**File:** `custom/create-consumer.php`

A standalone PHP script with placeholder OAuth credentials (`'your-client-secret'`,
`'your-client-id'`) is committed. Even with placeholder values, this file:

- Has no authentication, CSRF protection, or access control
- Is not a proper Drush command or module hook — it's a raw PHP file
- Should not be in the web-accessible module tree
- If ever deployed and executed, would create an OAuth consumer with UID 1 (admin)

This file should be removed from the repository. Operational scripts like this belong
as Drush commands, not loose PHP files.

---

### 10. SQL dump committed to repository

**File:** `custom/d10d11dsd.sql`

A database dump is committed to version control. This may contain personally
identifiable information, session tokens, password hashes, or other sensitive data.
Database dumps must never be committed to source control.

---

## RELIABILITY AND ROBUSTNESS

### 11. N+1 query inside report generation loop

**File:** `decision_support/src/Services/DecisionSupportService.php:126`

```php
foreach ($jsonData['steps'] as $step) {
    $files = $this->decisionSupportFileService->getDecisionSupportFile($decisionSupportId);
    // ↑ Same database query executed once per step
```

`getDecisionSupportFile($decisionSupportId)` executes an entity query and loads
entities every iteration of the steps loop. For a 10-step decision support, this is
10 identical database queries. Call it once before the loop.

---

### 12. `loadMultiple()` with no limit — potential memory exhaustion

**Files:** `ProcessService.php:45`, `DecisionSupportService.php:55,82`

```php
$unformattedProcesses = Process::loadMultiple();
```

Loading all entities of a type with no conditions or pagination will load every row
into memory. As the dataset grows this will cause increasingly slow responses and
eventually memory exhaustion. Use entity queries with range limits or pagination.

---

### 13. `duplicateProcess()` saves entity before validating JSON input

**File:** `process/src/Services/ProcessService.php:123-129`

```php
$process = Process::create($data);
$process->save();                   // ← Entity created in DB
$data_jsonstring = json_decode($data['json_string'], TRUE);
if (!is_array($data_jsonstring) ...) {
    throw new BadRequestHttpException('Invalid process json_string payload');
    // ← Entity already in DB but exception thrown — orphaned entity
```

An incomplete/orphaned entity is left in the database if the JSON validation fails.
Validate the input before any `::create()` call.

---

### 14. Disabled process returns empty string with HTTP 200

**File:** `process/src/Services/ProcessService.php:78-83`

```php
if ($process->getStatus()) {
    $processJsonString = $process->getJsonString();
}
else {
    $processJsonString = '';
}
return $processJsonString;
```

A request for a disabled process silently returns an empty body with a 200 OK
response. The caller has no way to distinguish "process exists but is disabled" from
a bug. A `403 Forbidden` or `404 Not Found` would be more appropriate.

---

### 15. `updateDecisionSupport()` accepts unvalidated `isCompleted` type

**File:** `decision_support/src/Services/DecisionSupportService.php:228`

```php
$decisionSupport->setIsCompleted($data['isCompleted']);
```

`setIsCompleted` has a `bool` type declaration. JSON-decoded data will produce a PHP
`bool`, but if the client sends `"isCompleted": 1` or `"isCompleted": "true"`, this
will fail with a `TypeError` (strict_types is active). Explicit casting or validation
is required before passing to a strictly-typed setter.

---

## DRUPAL 11 COMPATIBILITY

### 16. `text_processing` setting is removed in Drupal 10+

**Files:** `process/src/Entity/Process.php:112`, `decision_support/src/Entity/DecisionSupport.php:111`

```php
->setSettings([
    'max_length' => 50,
    'text_processing' => 0,   // ← Removed in D10
])
```

The `text_processing` setting was deprecated in Drupal 9 and removed in Drupal 10.
It should not appear in a codebase targeting `^10 || ^11`.

---

### 17. `composer.json` still declares Drupal 10 requirements

**File:** `composer.json`

```json
"description": "Project template for Drupal 10 projects with Composer",
"drupal/core-recommended": "^10.2.0",
"drupal/core-composer-scaffold": "^10.2.0",
```

The description, core-recommended, and scaffold constraints all require D10. To target
D11 these must be updated to `^11.0` (or `^10.2 || ^11.0` for dual compatibility).

---

## CODE QUALITY

### 18. Method name has wrong case — `getupdatedTime()` vs `getUpdatedTime()`

**Files:** `DecisionSupportService.php:64,89,148`

```php
$decisionSupport['updatedTime'] = $unformattedDecisionSupport->getupdatedTime();
```

The entity method is `getUpdatedTime()` (capital U). PHP method names are
case-insensitive so this works at runtime, but it will fail Drupal coding standards
(PHPCS) and is confusing to read. Three occurrences.

---

### 19. All REST resource plugins inject and instantiate `KeyValueStore` but never use it

**All 15 REST resource files** — boilerplate retained from DCG template:

```php
private readonly KeyValueStoreInterface $storage;
// ...
$this->storage = $keyValueFactory->get('...');
```

`$this->storage` is set in every constructor and never read anywhere. This is
scaffolding from the Drupal Code Generator that was never removed. It adds a
pointless service dependency and constructor parameter to every REST resource class.

---

### 20. DCG boilerplate comments left in all REST resources

Every REST resource file contains three `@DCG` comment blocks describing key-value
record handling, lack of validation, and the recommendation to use `EntityResource`.
These are generator scaffolding notes that should have been removed. They are now
actively misleading — these resources do not expose key-value records.

---

### 21. Service interface docblocks are completely wrong (copy-paste errors)

**File:** `decision_support/src/Services/DecisionSupport/DecisionSupportServiceInterface.php`

- `getDecisionSupportList()` — docblock says "Loads a DecisionSupport entity" and
  `@return DecisionSupport|null`. It actually returns an array.
- `getDecisionSupportReportList()` — docblock says "Get a DecisionSupport by ID" and
  `@return string`. It actually returns an array.
- `getDecisionSupportReport()` — docblock says "Creates a new DecisionSupport entity".

The docblocks appear to be generated boilerplate that was never updated to reflect the
actual methods.

---

### 22. Entity field names violate Drupal snake_case convention

**File:** `decision_support_file/src/Entity/DecisionSupportFile.php:173,192`

```php
$fields['decisionSupportId'] = BaseFieldDefinition::create('integer')
$fields['stepId'] = BaseFieldDefinition::create('string')
```

Drupal entity field names must be `snake_case`. Using camelCase field names
(`decisionSupportId`, `stepId`) will fail `DrupalPractice` PHPCS rules and creates
inconsistency with every other field in the system. These should be
`decision_support_id` and `step_id`. **Note:** changing these requires a database
schema update.

---

### 23. `revision_status` field uses wrong view display formatter

**Files:** `Process.php:155`, `DecisionSupport.php:155`

```php
->setDisplayOptions('view', [
    'label' => 'hidden',
    'type' => 'author',   // ← Author formatter is for User entity_reference fields
    'weight' => 0,
])
```

The `author` formatter is designed for user entity references. `revision_status` is
a taxonomy term entity reference. This will render incorrectly or throw an error on
entity view pages.

---

### 24. `urlRouteParameters()` lacks type declarations

**Files:** `Process.php:287`, `DecisionSupport.php:298`

```php
protected function urlRouteParameters($rel) {
```

Both entities declare `strict_types=1` but this method has no parameter type or
return type. The parent method signature in Drupal 11 is typed. PHPStan at higher
levels will flag this.

---

### 25. Static service calls in entity classes

**Files:** `Process.php:350,364,408`, `DecisionSupport.php:375,389,410`

```php
$date_formatter = \Drupal::service('date.formatter');
\Drupal::entityTypeManager()->getStorage('taxonomy_term')
```

Entity classes should receive dependencies through the service container, not through
`\Drupal::` static calls. Static calls break unit testability and are flagged by
`DrupalPractice` PHPCS rules. The `getRevisionStatus()`/`setRevisionStatus()` and
`getCreatedTime()`/`getUpdatedTime()` methods use static calls.

Additionally, `getCreatedTime()` and `getUpdatedTime()` format timestamps — date
formatting is a presentation concern and does not belong in the entity model layer.

---

### 26. `uniqid()` used as a UUID

**Files:** `ProcessService.php:101`, `DecisionSupportService.php:198`

```php
'uuid' => uniqid(),
```

`uniqid()` produces a 13-character hex string based on the current microtime. It is
not a UUID, not RFC 4122-compliant, and not cryptographically random. The entity
already has a proper UUID accessible via `$entity->uuid()`. Using `uniqid()` as a
"uuid" field in the JSON payload is misleading and should use the entity's UUID.

---

### 27. `decisionSupportId` is a plain integer, not an entity_reference

**File:** `decision_support_file/src/Entity/DecisionSupportFile.php:173`

```php
$fields['decisionSupportId'] = BaseFieldDefinition::create('integer')
```

Storing the decision support ID as a raw integer (rather than an `entity_reference`
field) means:
- No referential integrity — deleting a `decision_support_entity` leaves orphaned file records
- No access control propagation through the reference
- No Views join support
- The entity query in `DecisionSupportFileService::getDecisionSupportFile()` works,
  but cascading operations must be handled manually

---

### 28. Missing `dependencies` in module info files

**Files:** `decision_support.info.yml`, `decision_support_file.info.yml`, `process.info.yml`

None of the three modules declare their inter-module or contrib module dependencies.
`decision_support` directly instantiates `Process` entities and depends on
`decision_support_file.service` — neither dependency is declared. Without `dependencies`
declarations, Drupal cannot enforce install order or prevent uninstalling a dependency.

---

### 29. `ProcessService` and `DecisionSupportFileService` inject `EntityTypeManager` but use static `Class::loadMultiple()`

Both services inject `EntityTypeManagerInterface` (correct DI pattern) but then bypass
it by calling the static `Process::loadMultiple()` and
`DecisionSupportFile::loadMultiple()` directly. The injected `$entityTypeManager`
is used only in `DecisionSupportFileService::getDecisionSupportFile()` for the query.
The pattern should be consistent: either always use the static API or always use the
injected storage handler.

---

### 30. REST resource plugins inject concrete service classes, not interfaces

**All 15 REST resource files:**

```php
private DecisionSupportService $decisionSupportService;
// injected as:
$container->get('decision_support.service')
```

The type hint is the concrete class `DecisionSupportService` rather than
`DecisionSupportServiceInterface`. This defeats the purpose of having an interface,
prevents mocking in tests, and means the interface is effectively unused by its
primary consumers.

---

## QA TOOLING OBSERVATIONS

The project has PHPCS, PHPStan (level 2), and GrumPHP configured. Running these
against the current codebase will surface the following in addition to the above:

- **PHPCS** will flag: camelCase field names (issues 22), static calls (issue 25),
  wrong method casing `getupdatedTime` (issue 18), missing `declare(strict_types=1)`
  in `.module` files (the `.module` files only `decision_support.module` has it —
  checking `process.module` shows it does too, but verify `decision_support_file.module`).
- **PHPStan level 2** may miss: the type errors in logger placeholders (issue 3),
  the wrong field access in `getUid()` (issue 5). Raising to level 5 is recommended.
- Neither tool will catch: the security issues (issues 6–10), the archive-vs-delete
  semantic bug (issue 1), or the N+1 query (issue 11).

---

## REPOSITORY HYGIENE

| File | Problem |
|------|---------|
| `custom/d10d11dsd.sql` | Database dump — may contain PII/secrets, must not be in VCS |
| `custom/create-consumer.php` | Loose script with OAuth credentials, not a proper Drush command |
| `custom/single-export-*.txt` | Config exports — may be appropriate but verify intent |
| `custom/upgrade-status-export-*.txt` | Upgrade status snapshots — informational, can be removed |

---

## ISSUE PRIORITISATION

| Priority | Issue |
|----------|-------|
| **⛔ BLOCKED** | #1 Delete/archive semantics — requires legal/product requirements before any code change |
| **P0 — Security** | #6 REST endpoints bypass entity permissions |
| **P0 — Repo** | #10 SQL dump in repository |
| **P1 — Security** | #7 File attachment without access check |
| **P1 — Security** | #8 Taxonomy term lookup without vocabulary constraint |
| **P1 — Security** | #9 `create-consumer.php` in repository |
| **P1 — Bug** | #2 Unreachable return statement in `ArchiveDecisionSupportResource` |
| **P1 — Bug** | #3 Logger receives array instead of scalar |
| **P1 — Bug** | #4 Double-save creates orphaned revision |
| **P1 — Bug** | #5 `getUid()` always returns NULL |
| **P1 — Bug** | #13 Orphaned entity on JSON validation failure |
| **P2 — Reliability** | #11 N+1 query in report generation |
| **P2 — Reliability** | #12 `loadMultiple()` with no limit |
| **P2 — Compat** | #16 `text_processing` removed in D10 |
| **P2 — Compat** | #17 `composer.json` still targets D10 |
| **P3 — Quality** | #18–#30 Code quality and standards issues |
