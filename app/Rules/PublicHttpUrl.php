<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * SSRF guard for camera stream URLs.
 *
 * All stream URLs are public domains — no local IPs exist in this fleet —
 * so blanket rejection of private/reserved IPs is safe and no allowlist
 * is needed.
 *
 * A URL is allowed only when it uses the http/https scheme, its host
 * resolves to at least one IP address, and every resolved IP is a
 * public address (no private or reserved ranges).
 */
class PublicHttpUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value) || !self::isAllowed($value)) {
            $fail('The :attribute must be a public http(s) URL that does not resolve to a private or reserved IP address.');
        }
    }

    public static function isAllowed(?string $url): bool
    {
        if (!is_string($url) || $url === '' || strlen($url) > 2048) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($scheme !== 'http' && $scheme !== 'https') {
            return false;
        }

        $host = $parts['host'] ?? '';
        if ($host === '') {
            return false;
        }

        // Literal IP host: must be a public address.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) !== false;
        }

        // Hostname: must resolve, and every resolved A/AAAA address
        // must be a public address. Unresolvable hosts are rejected.
        $ips = gethostbynamel($host) ?: [];

        $aaaaRecords = dns_get_record($host, DNS_AAAA);
        if (is_array($aaaaRecords)) {
            foreach ($aaaaRecords as $record) {
                if (!empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            ) === false) {
                return false;
            }
        }

        return true;
    }
}
