<?php

namespace App\Models;

/**
 * Base for the configurable master tables (statuses, priorities, types,
 * categories). They all share the same code/name/active/sort shape.
 */
abstract class LookupModel extends BaseModel
{
    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** id => name pairs for select inputs. */
    public static function options(): array
    {
        return static::query()->where('is_active', 1)
            ->orderBy('sort_order')->orderBy('name')
            ->pluck('name', 'id')->all();
    }

    public static function idByCode(string $code): ?int
    {
        return static::query()->where('code', $code)->value('id');
    }
}
