# XPLabs Database Package (Server Import)

Use this folder for database files consumed by `windows/config/Integrate-XplabsDatabase.ps1`.

## Files
- `xplabs_dump.sql` (optional, not committed by default):
  - Full SQL export from your working environment.
  - If present, the import script will load this into the target DB.
- `xplabs.post-import.seed.sql`:
  - Test/admin bootstrap records you can apply after migrations/import.

## Typical flow
1. Copy your full dump here as `xplabs_dump.sql` (optional).
2. Run:
   - `..\Integrate-XplabsDatabase.ps1`
3. Script behavior:
   - If `xplabs_dump.sql` exists: create DB (if needed) and import it.
   - If no dump exists: run migration script (`database/migrate.php`) then apply `xplabs.post-import.seed.sql`.

