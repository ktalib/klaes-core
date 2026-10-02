<?php

namespace App\Services;

use App\Models\RequesterDirector;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Supplies the "Requester Director" dropdown used by Create File Tracker and
 * the Quick Search request form.
 *
 * Every department gets a standing director entry named "<Department> Director"
 * (e.g. "DCIV Director", "Land Director") so the dropdown is never empty when a
 * department is picked. Named individuals added through "Add New Director" live
 * in the same table and are listed after the department entry.
 *
 * The department list is the union of the two sources the two pages populate
 * their Department select from — offices.department (Create File Tracker) and
 * departments.name (Quick Search) — so the JS filter, which matches on the
 * department string, always finds a match.
 */
class RequesterDirectorService
{
    /** Title appended to the department name for the standing entry. */
    public const DIRECTOR_TITLE = 'Director';

    /** Buckets that are not real departments and get no director. */
    protected const EXCLUDED_DEPARTMENTS = ['ALL', 'OTHER', 'OTHER DEPARTMENTS'];

    /**
     * Department names that should have a standing director entry.
     */
    public function departmentNames(): Collection
    {
        $fromOffices = DB::connection('sqlsrv')
            ->table('offices')
            ->where('is_active', 1)
            ->whereNotNull('department')
            ->distinct()
            ->pluck('department');

        $fromDepartments = DB::connection('sqlsrv')
            ->table('departments')
            ->where('is_active', 1)
            ->whereNotNull('name')
            ->pluck('name');

        return $fromOffices
            ->merge($fromDepartments)
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->reject(fn ($name) => in_array(strtoupper($name), self::EXCLUDED_DEPARTMENTS, true))
            ->unique(fn ($name) => strtolower($name))
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * Create the missing "<Department> Director" rows. Idempotent — new
     * departments pick up their entry the next time the page is opened.
     *
     * @return int number of rows created
     */
    public function ensureDepartmentDefaults(): int
    {
        $existing = RequesterDirector::query()
            ->where('last_name', self::DIRECTOR_TITLE)
            ->get()
            ->keyBy(fn ($d) => strtolower(trim((string) $d->department)));

        $created = 0;
        $now = now();
        $rows = [];

        foreach ($this->departmentNames() as $department) {
            if ($existing->has(strtolower($department))) {
                continue;
            }

            $rows[] = [
                'department' => $department,
                'first_name' => $department,
                'last_name'  => self::DIRECTOR_TITLE,
                'full_name'  => $department . ' ' . self::DIRECTOR_TITLE,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $created++;
        }

        if ($rows) {
            RequesterDirector::insert($rows);
        }

        return $created;
    }

    /**
     * The full dropdown payload: department directors first (within their own
     * department), then any named individuals, alphabetically.
     */
    public function optionsForDropdown(): Collection
    {
        $this->ensureDepartmentDefaults();

        return RequesterDirector::query()
            ->orderBy('department')
            ->orderBy('first_name')
            ->get()
            ->sortBy([
                fn ($a, $b) => strcasecmp((string) $a->department, (string) $b->department),
                fn ($a, $b) => $this->isDepartmentDirector($b) <=> $this->isDepartmentDirector($a),
                fn ($a, $b) => strcasecmp((string) $a->full_name, (string) $b->full_name),
            ])
            ->values();
    }

    /**
     * Resolve a director for a save: an existing id, or a first/last name pair
     * that is looked up before being created so repeated saves of the same
     * person don't pile up duplicate rows.
     */
    public function resolve(?int $id, ?string $firstName, ?string $lastName, ?string $department): ?RequesterDirector
    {
        if ($id) {
            return RequesterDirector::find($id);
        }

        $firstName = trim((string) $firstName);
        $lastName  = trim((string) $lastName);

        if ($firstName === '' || $lastName === '') {
            return null;
        }

        return RequesterDirector::firstOrCreate(
            [
                'department' => $department,
                'first_name' => $firstName,
                'last_name'  => $lastName,
            ],
            ['full_name' => $firstName . ' ' . $lastName]
        );
    }

    protected function isDepartmentDirector(RequesterDirector $director): bool
    {
        return strcasecmp((string) $director->last_name, self::DIRECTOR_TITLE) === 0
            && strcasecmp(trim((string) $director->first_name), trim((string) $director->department)) === 0;
    }
}
