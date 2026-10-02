<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
    ];

    public const GROUP_VIP = 'vip';

    public const GROUP_PAYMENT = 'payment';

    public const GROUP_GENERAL = 'general';

    public const GROUP_COMPANY = 'company';

    public const GROUP_LEGAL = 'legal';

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->castValue() : $default;
    }

    public static function set(string $key, mixed $value, string $group = 'general'): void
    {
        // Allow empty strings so admin can clear fields.
        // Null is coerced to empty string; existing behaviour of skipping on truly absent values removed.
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value ?? '', 'group' => $group]
        );
    }

    public function castValue(): mixed
    {
        return match ($this->type) {
            'float' => (float) $this->value,
            'int', 'integer' => (int) $this->value,
            'bool', 'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }
}
