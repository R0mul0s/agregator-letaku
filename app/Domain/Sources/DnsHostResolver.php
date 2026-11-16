<?php

/**
 * Převod jména serveru přes systémový resolver (R186): IPv4 přes `gethostbynamel` — ten
 * přečte i /etc/hosts a zkrácené zápisy IP („127.1“, „2130706433“) převede stejně jako curl —
 * a IPv6 ze záznamů AAAA.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-11-16
 */

declare(strict_types=1);

namespace App\Domain\Sources;

use ErrorException;

final class DnsHostResolver implements HostResolver
{
    /**
     * IP adresy jména; nepřeložené jméno = prázdný seznam (požadavek pak selže sám).
     *
     * @return list<string>
     */
    public function addresses(string $host): array
    {
        $addresses = gethostbynamel($host) ?: [];

        try {
            $records = dns_get_record($host, DNS_AAAA);
        } catch (ErrorException) {
            // Neúspěšný dotaz DNS hlásí PHP varováním, Laravel z něj dělá výjimku
            $records = false;
        }
        foreach ($records ?: [] as $record) {
            if (is_string($record['ipv6'] ?? null)) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
