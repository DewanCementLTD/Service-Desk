<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends LookupModel
{
    protected $table = 'ticket_types';

    protected $fillable = ['code', 'name', 'description', 'is_active', 'sort_order'];

    public function categories(): HasMany
    {
        return $this->hasMany(TicketCategory::class, 'type_id');
    }
}
