# Madad Laravel SDK

Sync your product catalog with Madad in **4 steps**. The SDK pushes products on
every create/update/delete and ships a one-shot command for the initial load.

Targets Madad Partner API **v1**.

---

## 1. Install

```bash
composer require madad/laravel-sdk
php artisan vendor:publish --tag=madad-config
```

## 2. Configure (`config/madad.php` or `.env`)

The only thing you set is your partner key — the API endpoint is built into the SDK:

```dotenv
MADAD_API_KEY=your_partner_key
```

Then map Madad's fields to **your** columns (dot-paths into your model/relations):

```php
'product' => [
    'model'       => \App\Models\Product::class,
    'external_id' => 'id',
    'version'     => 'updated_at',
    'map' => [
        'name'  => 'listing_name',   // Madad 'name' ← your 'listing_name'
        'price' => 'unit_price',
    ],
    'category' => [
        'external_id' => 'category.code',
        'path'        => ['category.name'],
    ],
    'specifications' => ['relation' => 'attributes', 'key' => 'name', 'value' => 'value'],
    'images'         => ['relation' => 'photos', 'url' => 'url', 'sort' => 'position'],
],
```

Need a computed value? Add a `madad{Field}()` method to your model — the SDK
prefers it over the map:

```php
public function madadPrice(): float { return $this->cents / 100; }
```

## 3. Add the trait

```php
use Madad\Sdk\Concerns\SyncsWithMadad;

class Product extends Model
{
    use SyncsWithMadad;
}
```

From now on every create/update/delete syncs to Madad automatically (queued).

## 4. Initial load

```bash
php artisan madad:sync-all
```

Pushes your whole catalog (idempotent — safe to re-run; lock-guarded;
self-paced to the rate limit). With queueing enabled, run a worker:
`php artisan queue:work`.

---

### Sync only part of your catalog (optional)

By default **every** product syncs. If your store sells things Madad doesn't
list (e.g. a general store with paint *and* cleaning supplies), add a
`shouldSyncToMadad()` method to your model — return `true` only for what belongs
on Madad. You own the rule; you know your category tree:

```php
public function shouldSyncToMadad(): bool
{
    // Only sync products under your "Building Materials" branch.
    return $this->category && $this->category->isDescendantOf($buildingMaterialsId);
}
```

The same gate is honored everywhere: create, update, delete, and
`madad:sync-all`. If you don't define it, everything syncs.

> **Note:** if a product *leaves* scope (e.g. you move it from Paint to
> Cleaning so `shouldSyncToMadad()` now returns `false`), it is no longer pushed
> but is **not** auto-removed from Madad. Delete the record (or call
> `$product->madadDelete()` while it's still in scope) to remove it.

> **Large catalogs:** to avoid loading out-of-scope rows during `madad:sync-all`,
> you may also add a query scope `scopeMadadSyncable($query)` — the command uses
> it as a pre-filter (the per-row check still applies).

### Verify the connection
```php
use Madad\Sdk\Facades\Madad;

Madad::ping(); // returns your account + inherited defaults
```

### Notes
- **Queued by default** (recommended). Set `MADAD_QUEUE=false` for inline sync.
- Madad controls `type`, `brand`, and `delivery_type` from your partner account
  — they are not sent from your data.
- Rate limit: **300 requests/minute per partner**; the client backs off on `429`.
- SDK `v1` targets API `v1`.
