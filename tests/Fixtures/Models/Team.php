<?php

declare(strict_types=1);

namespace Orion\NotifyRules\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Team extends Model
{
    protected $guarded = [];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
