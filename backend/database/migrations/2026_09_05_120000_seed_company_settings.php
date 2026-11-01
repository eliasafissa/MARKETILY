<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed company info + legal pages into the existing settings table.
     * Uses updateOrCreate so re-running on existing data is a no-op for the
     * values already set; defaults land in place if the keys don't exist.
     */
    public function up(): void
    {
        $defaults = [
            ['key' => 'company_name',     'value' => 'Marketly',                    'group' => 'company', 'type' => 'string'],
            ['key' => 'support_email',    'value' => 'support@example.com',         'group' => 'company', 'type' => 'string'],
            ['key' => 'phone',            'value' => '+1 (555) 000-0000',           'group' => 'company', 'type' => 'string'],
            ['key' => 'address',          'value' => '123 Main Street, City, Country', 'group' => 'company', 'type' => 'string'],
            ['key' => 'facebook_url',     'value' => '',                            'group' => 'company', 'type' => 'string'],
            ['key' => 'instagram_url',    'value' => '',                            'group' => 'company', 'type' => 'string'],
            ['key' => 'twitter_url',      'value' => '',                            'group' => 'company', 'type' => 'string'],
            ['key' => 'telegram_url',     'value' => '',                            'group' => 'company', 'type' => 'string'],
            ['key' => 'legal_terms',      'value' => '',                            'group' => 'legal',   'type' => 'string'],
            ['key' => 'legal_privacy',    'value' => '',                            'group' => 'legal',   'type' => 'string'],
            ['key' => 'legal_refund',     'value' => '',                            'group' => 'legal',   'type' => 'string'],
        ];

        foreach ($defaults as $row) {
            Setting::updateOrCreate(
                ['key' => $row['key']],
                ['value' => $row['value'], 'group' => $row['group'], 'type' => $row['type']]
            );
        }
    }

    public function down(): void
    {
        Setting::whereIn('key', [
            'company_name', 'support_email', 'phone', 'address',
            'facebook_url', 'instagram_url', 'twitter_url', 'telegram_url',
            'legal_terms', 'legal_privacy', 'legal_refund',
        ])->delete();
    }
};
