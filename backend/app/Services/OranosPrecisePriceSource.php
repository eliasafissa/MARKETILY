<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Read-only Oranos price feed. Never passes numeric `price` JSON tokens through a float. */
class OranosPrecisePriceSource
{
    public function all(): array
    {
        $base = rtrim((string)config('services.oranos.url'), '/');
        $token = config('services.oranos.token');
        if (!$base || !$token) throw new RuntimeException('Oranos connection not configured.');
        $http = Http::withHeaders(['api-token' => $token, 'Accept' => 'application/json'])
            ->connectTimeout(15)->timeout(50);
        $ca = config('services.oranos.ca_bundle') ?: storage_path('app/certs/cacert.pem');
        if (is_file($ca)) $http = $http->withOptions(['verify' => $ca]);
        $res = $http->get($base.'/client/api/products');
        if (!$res->successful()) throw new RuntimeException('Oranos HTTP '.$res->status());
        return self::decodeProductList($res->body());
    }

    /** Expand raw JSON numeric lexemes without passing through float. */
    public static function normalizeRawPrice(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/\A(-?)(0|[1-9][0-9]*)(?:\.([0-9]+))?(?:[eE]([+-]?[0-9]+))?\z/D', $value, $m)) {
            throw new \DomainException('Invalid supplier numeric token.');
        }
        $digits = $m[2] . ($m[3] ?? '');
        $exponent = isset($m[4]) ? (int) $m[4] : 0;
        if (abs($exponent) > 150) throw new \DomainException('Supplier exponent unsupported.');
        $position = strlen($m[2]) + $exponent;
        $expanded = $position <= 0 ? '0.' . str_repeat('0', -$position) . $digits
            : ($position >= strlen($digits) ? $digits . str_repeat('0', $position - strlen($digits))
                : substr($digits, 0, $position) . '.' . substr($digits, $position));
        [$whole, $fraction] = array_pad(explode('.', $expanded, 2), 2, '');
        $whole = ltrim($whole, '0');
        if ($whole === '') $whole = '0';
        $fraction = rtrim($fraction, '0');
        return PreciseMoney::decimal(($m[1] === '-' ? '-' : '') . $whole . ($fraction === '' ? '' : '.' . $fraction));
    }

    /** Rewrites only JSON number *values* belonging to price keys into strings.
     * Quote/escape aware: a key-like phrase inside a JSON string is not transformed.
     */
    public static function decodeProductList(string $body): array
    {
        $modified = '';
        $n = strlen($body);
        for ($i=0; $i<$n;) {
            if ($body[$i] !== '"') { $modified .= $body[$i++]; continue; }
            $start = $i++;
            while ($i<$n) {
                if ($body[$i] === '\\') { $i += 2; continue; }
                if ($body[$i++] === '"') break;
            }
            if ($i>$n || $body[$i-1] !== '"') throw new RuntimeException('Unterminated Oranos JSON string');
            $token = substr($body, $start, $i-$start);
            $modified .= $token;
            $key = json_decode($token, true);
            if ($key !== 'price') continue;
            $j = $i;
            while ($j<$n && ctype_space($body[$j])) $j++;
            if ($j >= $n || $body[$j] !== ':') continue;
            $j++;
            while ($j<$n && ctype_space($body[$j])) $j++;
            if (preg_match('/\G-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?(?=\s*[,}\]])/A', $body, $m, 0, $j)) {
                $modified .= substr($body, $i, $j-$i) . '"' . $m[0] . '"';
                $i = $j + strlen($m[0]);
            }
        }
        $data = json_decode($modified, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        if (!is_array($data) || !array_is_list($data) || !$data || !isset($data[0]['id'])) {
            throw new RuntimeException('Oranos product price response missing product list.');
        }
        $map = [];
        foreach ($data as $row) {
            if (!is_array($row) || !isset($row['id']) || !ctype_digit((string)$row['id']) || (int)$row['id'] < 1) {
                throw new RuntimeException('Invalid Oranos product ID.');
            }
            $id = (int)$row['id'];
            if (array_key_exists($id, $map)) throw new RuntimeException('Duplicate Oranos product ID.');
            $raw = $row['price'] ?? null;
            // A disabled/invalid product must not poison the entire valid feed.
            // Null means "not safely priced"; the refresh skips the product and it expires.
            if (!is_string($raw) && !is_int($raw)) { $map[$id] = null; continue; }
            try {
                $cost = self::normalizeRawPrice((string) $raw);
                $map[$id] = PreciseMoney::compare($cost, '0') > 0 ? $cost : null;
            } catch (\DomainException $e) {
                $map[$id] = null;
            }
        }
        return $map;
    }
}
