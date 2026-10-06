<?php

namespace App\Observers;

use App\Models\Production;

class ProductionObserver
{
    /**
     * Called after a production record is saved.
     * Lot creation and traceability recording have been removed (not part of this module).
     */
    public function created(Production $production): void
    {
        // Production created — no automatic lot creation in this scope.
    }

    public function updated(Production $production): void
    {
        // Production updated.
    }
}
