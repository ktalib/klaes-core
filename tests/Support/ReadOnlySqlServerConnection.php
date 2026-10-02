<?php

namespace Tests\Support;

use Illuminate\Database\SqlServerConnection;
use RuntimeException;

/**
 * The 'sqlsrv' connection points at the live production database (klas @
 * 10.50.1.1) — there is no isolated test copy. This blocks every write path
 * during tests so nothing can be inserted, updated, deleted, or schema-changed
 * there without the person running tests deliberately re-pointing config.
 * Reads still pass through, since existing tests rely on real user data.
 */
class ReadOnlySqlServerConnection extends SqlServerConnection
{
    public function insert($query, $bindings = [])
    {
        $this->blocked('INSERT', $query);
    }

    public function update($query, $bindings = [])
    {
        $this->blocked('UPDATE', $query);
    }

    public function delete($query, $bindings = [])
    {
        $this->blocked('DELETE', $query);
    }

    public function statement($query, $bindings = [])
    {
        $this->blocked('STATEMENT', $query);
    }

    public function affectingStatement($query, $bindings = [])
    {
        $this->blocked('WRITE', $query);
    }

    public function unprepared($query)
    {
        $this->blocked('UNPREPARED', $query);
    }

    private function blocked(string $type, string $query): void
    {
        throw new RuntimeException(
            "Blocked {$type} on the 'sqlsrv' connection during tests — this connection points at the "
            ."production database ('klas' @ 10.50.1.1), not a test copy. Reads are still allowed. "
            ."Attempted query: {$query}"
        );
    }
}
