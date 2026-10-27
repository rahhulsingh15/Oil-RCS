<?php

namespace App\Services\Import;

/** Collects row counts and data-quality warnings during an import. */
class ImportReport
{
    /** @var array<string, int> */
    public array $counts = [];

    /** @var list<string> */
    public array $warnings = [];

    public function count(string $table, int $by = 1): void
    {
        $this->counts[$table] = ($this->counts[$table] ?? 0) + $by;
    }

    public function warn(string $message): void
    {
        if (! in_array($message, $this->warnings, true)) {
            $this->warnings[] = $message;
        }
    }
}
