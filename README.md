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

```dotenv
MADAD_API_KEY=your_partner_key
MADAD_BASE_URL=https://madad-app.com/api/v1
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
