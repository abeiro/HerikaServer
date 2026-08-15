# HerikaServer Agent Notes

- Global Settings and Profiles must remain feature-equivalent between their PHP pages and the in-game Prisma Settings hub.
- When adding, removing, renaming, or changing a setting in `ui/global_settings.php` or `ui/core/core_profiles.php`, update `lib/core/prisma_settings_catalog.php` in the same change.
- Keep structural fields in `ui/api/chim_global_settings.php` and `ui/api/chim_profile_manager.php` allowlisted and typed. Preserve unknown profile metadata for plugin compatibility, but never map arbitrary client keys to database columns.
- Validate both the PHP page and the Prisma API response when changing either settings menu.
