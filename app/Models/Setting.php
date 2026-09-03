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
