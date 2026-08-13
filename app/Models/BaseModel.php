<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared configuration for every application model.
 *
 * All tables live in the SDESK Oracle schema and use the same NUMBER(19)
 * surrogate key populated by a sequence-backed BEFORE INSERT trigger.
 */
abstract class BaseModel extends Model
{
    protected $connection = 'oracle';

    protected $primaryKey = 'id';

    protected $keyType = 'int';

    public $incrementing = true;

    public const CREATED_AT = 'created_at';

    public const UPDATED_AT = 'updated_at';
}
