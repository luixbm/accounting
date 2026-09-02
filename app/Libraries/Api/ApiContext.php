<?php

namespace App\Libraries\Api;

/**
 * Request-scoped holder for the authenticated API token. Set by the ApiAuth
 * filter, read by controllers and by active_company_id() so tenant-scoped
 * models resolve to the token's company instead of the session.
 */
final class ApiContext
{
    /** @var array<string,mixed>|null */
    private static ?array $token = null;

    /** @param array<string,mixed> $token */
    public static function set(array $token): void
    {
        self::$token = $token;
    }

    public static function clear(): void
    {
        self::$token = null;
    }

    public static function active(): bool
    {
        return self::$token !== null;
    }

    /** @return array<string,mixed>|null */
    public static function token(): ?array
    {
        return self::$token;
    }

    public static function companyId(): ?int
    {
        return self::$token !== null ? (int) self::$token['company_id'] : null;
    }

    /** @return list<string> */
    public static function abilities(): array
    {
        if (self::$token === null) {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(',', (string) self::$token['abilities']))));
    }

    public static function can(string $ability): bool
    {
        $have = self::abilities();

        return in_array('*', $have, true) || in_array($ability, $have, true);
    }
}
