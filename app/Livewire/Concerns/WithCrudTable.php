<?php

namespace App\Livewire\Concerns;

use Livewire\WithPagination;

/**
 * Shared plumbing for the master-data CRUD screens.
 *
 * A "trait" is a bundle of properties/methods mixed into a class with `use`. This one
 * holds ONLY the parts that are identical on every paginated master screen — the
 * page-size control, the create/edit panel toggle, and browser notifications. Each
 * screen keeps its own model, fields, validation, query/filter, and delete guards;
 * those genuinely differ, so they stay explicit in the component (composition over
 * inheritance).
 *
 * Used by: Districts, Banks, Designations, Locations, Ddos.
 */
trait WithCrudTable
{
    use WithPagination;

    /** How many rows to show per page (user-selectable). */
    public int $perPage = 10;

    /** Whether the create/edit panel is open. */
    public bool $showForm = false;

    /** Changing the page size should start from page 1. */
    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    /** Fire a SweetAlert toast in the browser (see resources/js/app.js). */
    protected function notify(string $message, string $type = 'success'): void
    {
        $this->dispatch('notify', type: $type, message: $message);
    }
}
