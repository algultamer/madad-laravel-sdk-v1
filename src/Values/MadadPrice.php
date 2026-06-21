<?php

namespace Madad\Sdk\Values;

use InvalidArgumentException;
use JsonSerializable;

/**
 * A product price for Madad. Return one from your model's `madadPrice()` method.
 *
 * The named constructors make invalid states unrepresentable — you cannot build
 * a range without both bounds, a negative price, or a "from" price carrying a max.
 *
 * The UNIT is NOT part of this object — send it via the separate top-level
 * `price_unit` field (config map or a `madadPriceUnit()` method).
 *
 *   public function madadPrice(): MadadPrice
 *   {
 *       return MadadPrice::range($this->price_min, $this->price_max);
 *   }
 *
 * Serializes to the wire shape the API expects: {type, price, max_price}.
 */
final class MadadPrice implements JsonSerializable
{
    private function __construct(
        public readonly string $type,      // fixed | from | range
        public readonly float $price,      // the firm price, the "from" floor, or the range minimum
        public readonly ?float $maxPrice = null, // range maximum only
    ) {}

    /** A single firm price. */
    public static function fixed(float|int|string $price): self
    {
        return new self('fixed', self::num($price));
    }

    /** "Starts from" — the number is the floor (e.g. aluminium "from $50/m"). */
    public static function from(float|int|string $price): self
    {
        return new self('from', self::num($price));
    }

    /** A "from–to" range. */
    public static function range(float|int|string $min, float|int|string $max): self
    {
        $min = self::num($min);
        $max = self::num($max);

        if ($max < $min) {
            throw new InvalidArgumentException('MadadPrice::range — max must be greater than or equal to min.');
        }

        return new self('range', $min, $max);
    }

    /** @return array{type: string, price: float, max_price: float|null} */
    public function toArray(): array
    {
        return [
            'type'      => $this->type,
            'price'     => $this->price,
            'max_price' => $this->maxPrice,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    private static function num(float|int|string $value): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('MadadPrice expects a numeric price.');
        }

        $float = (float) $value;

        if ($float < 0) {
            throw new InvalidArgumentException('MadadPrice cannot be negative.');
        }

        return round($float, 2);
    }
}
