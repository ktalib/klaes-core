/* ============================================================================
   Register the "Virtual Folder System" module role  —  RUN AGAINST SQL SERVER
   ----------------------------------------------------------------------------
   NO LEDGER COMPANION. This is a data row in an existing table, not a schema
   change, so there is no artisan migration to mark as run. The
   <name>.sql + <name>_ledger.mysql.sql pair applies only when a migration
   exists on the MySQL side.

   WHY THIS IS NEEDED
   The sidebar entry at resources/views/admin/menu/partials/modules/vfs.blade.php
   is gated on $hasRole('Virtual Folder System'), which resolves through
   users.assign_role. Un-commenting the @include alone surfaces the module to
   nobody, because the role does not exist in the registry and therefore cannot
   be picked in the role-assignment screen.

   WHAT THIS DOES — AND DOES NOT DO
   Adds ONE row to user_roles so administrators can grant the module. It grants
   the module to NO ONE. users.assign_role is untouched, so every existing
   user's menu is exactly as it was before this ran. Visibility remains an
   explicit, per-user decision.

   WHY department ALL
   The explorer is read-only and spans every registry — Lands, KANGIS, Deeds,
   DCIV, SLTR, ST. Scoping it to one department would misdescribe it, and
   department_id here is descriptive metadata, not an access boundary.

   SUPER ADMINS ONLY — WHAT THIS ROW IS AND IS NOT
   Access is NOT granted by this row. Both the sidebar entry and routes/vfs.php
   are gated on User::isSuperAdmin() via the super.admin middleware, so granting
   this role to an ordinary user gives them nothing.

   The row exists so the module has a name in the registry that matches the
   sidebar, and so access can be delegated later by relaxing the two gates
   without having to invent the role at that point.

   SAFETY
     - Re-runnable: the INSERT is guarded by NOT EXISTS on the name.
     - Touches one row in user_roles. No user, permission or grant is modified.
     - Undo: DELETE FROM user_roles WHERE name = 'Virtual Folder System';
       (safe only while no user has been granted it.)
   ============================================================================ */

/* Preview — expect 0 before the change */
SELECT COUNT(*) AS role_rows_before
  FROM user_roles
 WHERE name = 'Virtual Folder System';

/* Insert, guarded */
INSERT INTO user_roles (name, guard_name, description, department_id, level, user_type, is_active, created_at, updated_at)
SELECT 'Virtual Folder System',
       'web',
       'Read-only logical view over the EDMS archive. Restricted to Super Admins.',
       1,          /* ALL — the view spans every registry */
       'High',
       'System_High',
       1,
       SYSDATETIME(),
       SYSDATETIME()
 WHERE NOT EXISTS (
       SELECT 1 FROM user_roles WHERE name = 'Virtual Folder System'
 );

/* Verify — expect exactly 1, and 0 users granted */
SELECT
    (SELECT COUNT(*) FROM user_roles WHERE name = 'Virtual Folder System')                  AS role_rows_after,
    (SELECT COUNT(*) FROM users WHERE assign_role LIKE '%Virtual Folder System%')           AS users_granted;
