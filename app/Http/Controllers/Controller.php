<?php

namespace App\Http\Controllers;

use App\Models\Block;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Blocks the user may see, narrowed by the top filters
     * (?basin=, ?block=, ?phase=) that every page shares.
     */
    protected function filteredBlocks(Request $request): Builder
    {
        return Block::visibleTo($request->user())
            ->when($request->filled('basin'), fn ($q) => $q->where('basin', $request->input('basin')))
            ->when($request->filled('block'), fn ($q) => $q->whereKey($request->integer('block')))
            ->when($request->filled('phase'), function ($q) use ($request) {
                $phase = $request->input('phase');

                $phase === 'Relinquished'
                    ? $q->where('status', 'Relinquished')
                    : $q->where('status', '!=', 'Relinquished')->whereHas('timeline', fn ($t) => $t->where('current_phase', $phase));
            });
    }

    /** IDs of the filtered blocks. */
    protected function filteredBlockIds(Request $request): array
    {
        return $this->filteredBlocks($request)->pluck('id')->all();
    }
}
