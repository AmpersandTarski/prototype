# Database Schema Compatibility Warnings

## Critical Warning Signs - Schema Incompatibility Risk

### HIGH RISK Actions That Require Database Volume Reset:

1. **Framework Version Changes**
   - Changing Ampersand prototype framework versions (e.g., `ampersandtarski/prototype-framework:main` → `v1.19`)
   - Switching between different framework approaches (Angular frontend vs no-frontend)
   - Upgrading/downgrading Ampersand compiler versions

2. **Docker Configuration Changes**
   - Modifying base Docker image in `project/Dockerfile`
   - Changing Ampersand compilation parameters (`--crud-defaults`, `--proto-dir`, schema options)
   - Switching between different prototype generation modes

3. **Schema-Affecting Code Changes**
   - Major modifications to `.adl` files that alter database structure
   - Changes to database initialization scripts in `db-init-scripts/`
   - Modifications to table relationships or constraints

### WARNING: When These Changes Occur

**BEFORE proceeding with framework changes:**
1. **Backup current database**: `docker exec prototype-db mysqldump -u ampersand -p[password] [database] > backup.sql`
2. **Document current setup**: Note current framework version, Ampersand version, schema version
3. **Test in isolation**: Use fresh database volume for testing new framework
4. **Plan volume reset**: Be prepared to delete `db-data` volume if incompatibility occurs

### Symptoms of Schema Incompatibility:
- Container starts successfully but application fails
- Database connection works but queries fail silently
- Missing tables/columns errors in logs
- Foreign key constraint violations
- Prototype appears broken despite successful container startup

### Resolution:
```bash
# Stop containers
docker-compose down

# Remove database volume (DESTRUCTIVE - will lose all data)
docker volume rm fc4_db-data

# Restart with fresh database
docker-compose up -d
```

### Prevention Strategy:
- Always test framework changes with fresh database first
- Use explicit version tags instead of `:latest` or `:main`
- Keep separate volume names for different framework versions during testing
- Document all framework version changes in project history

---

## Object-identity shortening (cross-repo contract)

OBJECT atom identities are stored in a `VARCHAR(255)` column and must be unique within their
concept. Both the runtime importers AND the compiler shorten any object id longer than 254
characters to a deterministic, DB-safe value:

```
len(id) <= 254  -> id unchanged
len(id) >  254  -> first 243 chars + "_" + first 10 lowercase hex chars of sha1(utf8(id))   (= 254 chars)
```

This is **one algorithm implemented in two repositories** — they must stay byte-for-byte identical,
otherwise a compiler-generated object atom and the same atom imported/created at runtime map to
different database rows (or the un-shortened id overflows `VARCHAR(255)` and the install fails):

- **Runtime (this repo):** `backend/src/Ampersand/Core/Atom.php` → `Atom::setId()`, OBJECT case.
  Covers both importers (Excel + JSON population) and the API, because every atom is built via `new Atom`.
- **Compiler (`~/git/Ampersand`):** `src/Ampersand/Basics/Hashing.hs` → `shortenObjectId`, applied in
  `src/Ampersand/Core/AbstractSyntaxTree.hs` (`mkObjectAtomVal`, the three `Object` branches of
  `unsafePAtomVal2AtomValue`). Bakes the shortened id into `database.sql` / generated population.

**Shared known-answer vector** (asserted in both test suites): `"a" x 300` → `"a" x 243 + "_003ef1ba9e"`.
- PHP: `test/unit/AtomObjectIdShorteningTest.php`
- Haskell: `src/Ampersand/Test/Parser/QuickChecks.hs` (`doObjectIdShorteningTest`) +
  `testing/Travis/testcases/Parsing/xlsxLongObjectId/` (a `validate` test that fails if the
  generated population overflows the column).

**Sync requirement:** ship the compiler change and the framework change together. An old compiler
(no shortening) writing into a new framework's `VARCHAR(255)` will fail to install; bump
`backend/generics/compiler-version.txt` to require the compiler version that includes shortening.

---
**Last Updated**: June 22, 2026
**Context**: Object-identity shortening added so importers guarantee object-id uniqueness within 254
chars. The earlier note below stems from experience where switching Ampersand prototype frameworks
caused database schema incompatibility, requiring volume deletion to resolve.
