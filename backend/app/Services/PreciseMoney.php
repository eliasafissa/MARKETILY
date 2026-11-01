<?php

namespace App\Services;

/**
 * Exact decimal arithmetic for quoted USD provider prices.
 * Never use binary floating point for unit-price, quantity, or wallet calculations.
 * The scale is a finite 24 decimal places; do not convert to float before settlement.
 */
final class PreciseMoney
{
    public const SCALE = 24;

    public static function decimal(string|int $value): string
    {
        $raw = trim((string) $value);
        if (!preg_match('/^-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?$/D', $raw) || strlen($raw) > 70) {
            throw new \DomainException('Invalid decimal amount.');
        }
        if (strpos($raw, '.') !== false && strlen(explode('.', $raw, 2)[1]) > self::SCALE) {
            throw new \DomainException('Decimal amount exceeds supported precision.');
        }
        return bcadd($raw, '0', self::SCALE);
    }

    public static function add(string|int $a, string|int $b): string
    {
        return bcadd(self::decimal($a), self::decimal($b), self::SCALE);
    }

    public static function sub(string|int $a, string|int $b): string
    {
        return bcsub(self::decimal($a), self::decimal($b), self::SCALE);
    }

    public static function multiplyByQuantity(string|int $amount, int $quantity): string
    {
        if ($quantity < 1 || $quantity > 50000000) {
            throw new \DomainException('Invalid quantity.');
        }
        return bcmul(self::decimal($amount), (string) $quantity, self::SCALE);
    }

    public static function compare(string|int $a, string|int $b): int
    {
        return bccomp(self::decimal($a), self::decimal($b), self::SCALE);
    }

    public static function percentOf(string|int $price, string|int $percent): string
    {
        $p = self::decimal($percent);
        if (bccomp($p, '0', self::SCALE) < 0 || bccomp($p, '10000', self::SCALE) > 0) {
            throw new \DomainException('Invalid percentage.');
        }
        return bcdiv(bcmul(self::decimal($price), $p, 48), '100', self::SCALE);
    }

    /** Original provider unit cost + administrator markup, with no 4-decimal rounding. */
    public static function providerWithMarkup(string|int $providerCost, string|int $markupPercent): string
    {
        $base = self::decimal($providerCost);
        if (bccomp($base, '0', self::SCALE) <= 0) {
            throw new \DomainException('Invalid provider cost.');
        }
        return self::add($base, self::percentOf($base, $markupPercent));
    }

    /** Keep the provider cost floor when a VIP discount applies. */
    public static function providerVipPrice(string|int $regularPrice, string|int $providerCost, string|int $vipPercent): string
    {
        $vip = self::decimal($vipPercent);
        if (bccomp($vip, '0', self::SCALE) < 0 || bccomp($vip, '100', self::SCALE) > 0) {
            throw new \DomainException('Invalid VIP discount.');
        }
        $net = self::sub($regularPrice, self::percentOf($regularPrice, $vip));
        $floor = self::decimal($providerCost);
        return bccomp($net, $floor, self::SCALE) >= 0 ? $net : $floor;
    }
}
