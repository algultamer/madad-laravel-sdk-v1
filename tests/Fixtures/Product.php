<?php

namespace Madad\Sdk\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Madad\Sdk\Concerns\SyncsWithMadad;

/**
 * Fixture partner model. Exercises the trait, the payload builder (flat fields,
 * a date version, a category, and two 1:many relations), and soft deletes.
 */
class Product extends Model
{
    use SoftDeletes;
    use SyncsWithMadad;

    protected $guarded = [];

    protected $casts = ['amount' => 'float'];

    public function specs(): HasMany
    {
        // Explicit FK so subclasses (which change the inferred key) still resolve.
        return $this->hasMany(Spec::class, 'product_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class, 'product_id');
    }
}
