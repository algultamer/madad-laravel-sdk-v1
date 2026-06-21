<?php

namespace Madad\Sdk\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Madad\Sdk\Values\MadadPrice;

/**
 * Builds a Madad product payload from a partner model using the config map.
 *
 * Resolution order for every field: a `madad{Field}()` method on the model
 * wins (the escape hatch for computed values); otherwise the configured
 * dot-path is read with data_get(). Output matches PartnerProductRequest.
 */
class PayloadBuilder
{
    public static function build(Model $model, array $cfg): array
    {
        $payload = [
            'external_id' => (string) data_get($model, $cfg['external_id'] ?? 'id'),
        ];

        foreach (($cfg['map'] ?? []) as $field => $path) {
            $value = self::field($model, $field, $path);
            if ($value !== null) {
                $payload[$field] = $value;
            }
        }

        if (! empty($cfg['version'])) {
            $v = self::field($model, 'version', $cfg['version']);
            if ($v !== null) {
                $payload['version'] = self::toVersion($v);
            }
        }

        $category = self::category($model, $cfg['category'] ?? []);
        if ($category !== null) {
            $payload['category'] = $category;
        }

        $specs = self::specifications($model, $cfg['specifications'] ?? []);
        if ($specs !== null) {
            $payload['specifications'] = $specs;
        }

        $images = self::images($model, $cfg['images'] ?? []);
        if ($images !== null) {
            $payload['images'] = $images;
        }

        return $payload;
    }

    /** Prefer a madad{Field}() override on the model, else read the dot-path. */
    protected static function field(Model $model, string $field, mixed $path): mixed
    {
        $method = 'madad'.Str::studly($field);
        if (method_exists($model, $method)) {
            $value = $model->{$method}();

            // A MadadPrice value object serializes to the wire shape {type, price, max_price}.
            return $value instanceof MadadPrice ? $value->toArray() : $value;
        }

        return is_string($path) ? data_get($model, $path) : null;
    }

    protected static function category(Model $model, array $cfg): ?array
    {
        if (method_exists($model, 'madadCategory')) {
            return $model->madadCategory();
        }

        $category = [];

        if (! empty($cfg['external_id'])) {
            $ext = data_get($model, $cfg['external_id']);
            if ($ext !== null) {
                $category['external_id'] = (string) $ext;
            }
        }

        $path = [];
        foreach (($cfg['path'] ?? []) as $segmentPath) {
            $segment = data_get($model, $segmentPath);
            if ($segment !== null && $segment !== '') {
                $path[] = (string) $segment;
            }
        }
        if ($path !== []) {
            $category['path'] = $path;
        }

        return $category === [] ? null : $category;
    }

    protected static function specifications(Model $model, array $cfg): ?array
    {
        if (method_exists($model, 'madadSpecifications')) {
            return $model->madadSpecifications();
        }
        if (empty($cfg['relation']) || empty($cfg['key'])) {
            return null;
        }

        return collect(data_get($model, $cfg['relation']) ?? [])
            ->map(fn ($item) => [
                'key' => (string) data_get($item, $cfg['key']),
                'value' => data_get($item, $cfg['value'] ?? 'value'),
            ])
            ->filter(fn ($s) => $s['key'] !== '')
            ->values()
            ->all();
    }

    protected static function images(Model $model, array $cfg): ?array
    {
        if (method_exists($model, 'madadImages')) {
            return $model->madadImages();
        }
        if (empty($cfg['relation']) || empty($cfg['url'])) {
            return null;
        }

        return collect(data_get($model, $cfg['relation']) ?? [])
            ->map(function ($item) use ($cfg) {
                $img = ['url' => (string) data_get($item, $cfg['url'])];
                if (! empty($cfg['sort'])) {
                    $img['sort_order'] = (int) data_get($item, $cfg['sort']);
                }

                return $img;
            })
            ->filter(fn ($i) => $i['url'] !== '')
            ->values()
            ->all();
    }

    /** Dates → integer timestamp; numeric strings → int; else passthrough. */
    protected static function toVersion(mixed $value): int
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        try {
            return Carbon::parse((string) $value)->getTimestamp();
        } catch (\Throwable) {
            return 0;
        }
    }
}
