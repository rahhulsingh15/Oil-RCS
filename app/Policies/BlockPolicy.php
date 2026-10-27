<?php

namespace App\Policies;

use App\Models\Block;
use App\Models\User;

class BlockPolicy
{
    /** Needs the blocks.view permission and access to this particular block. */
    public function view(User $user, Block $block): bool
    {
        return $user->hasPermission('blocks.view') && $user->canAccessBlock($block);
    }
}
