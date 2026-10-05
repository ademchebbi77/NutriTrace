<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin-edited overrides of config/footprint.php and config/trust.php.
 */
class Setting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
