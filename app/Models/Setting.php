<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Get a setting value by key, falling back to $default when missing.
     *
     * Returns $default when the settings table does not exist yet
     * (e.g. before migrations have run) so public pages keep working.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            $record = static::query()->where('key', $key)->first();
        } catch (QueryException) {
            return $default;
        }

        if ($record === null) {
            return $default;
        }

        return $record->value ?? $default;
    }

    /**
     * Get multiple setting values by key in a single query.
     *
     * Returns $defaults[$key] (or null) for missing keys, and an empty
     * defaults map when the settings table does not exist yet.
     *
     * @param  array<int, string>  $keys
     * @param  array<string, mixed>  $defaults
     * @return array<string, mixed>
     */
    public static function getMany(array $keys, array $defaults = []): array
    {
        try {
            $rows = static::query()->whereIn('key', $keys)->pluck('value', 'key')->all();
        } catch (QueryException) {
            $rows = [];
        }

        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $rows[$key] ?? $defaults[$key] ?? null;
        }

        return $result;
    }

    /**
     * Create or update a setting value by key.
     */
    public static function set(string $key, mixed $value): static
    {
        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value === null ? null : (string) $value],
        );
    }
}
