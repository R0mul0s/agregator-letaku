<?php

/**
 * E-mail se souhrnem nových akcí hlídaných položek (R42) — Markdown šablona
 * resources/views/mail/digest.blade.php, styly dodá Laravel (inline do HTML e-mailu).
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Mail;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DigestMail extends Mailable
{
    /** Haléřů v koruně (ceny jsou v haléřích, R7). */
    private const HALERS_PER_CROWN = 100;

    private const PRICE_DECIMALS = 2;

    private const CURRENCY = 'Kč';

    /** Nezlomitelná mezera — částka a měna ani tisíce se nerozdělí na dva řádky. */
    private const NO_BREAK_SPACE = "\u{00A0}";

    private const THOUSANDS_SEPARATOR = self::NO_BREAK_SPACE;

    /** Formát data konce platnosti v e-mailu („8. 10.“). */
    private const DATE_FORMAT = 'j.'.self::NO_BREAK_SPACE.'n.';

    /**
     * @param  list<array{name: string, offers: list<array{name: string, chain: string, price: int|null, discountPercent: int|null, validTo: CarbonImmutable}>}>  $groups  Hlídané položky s novými akcemi
     */
    public function __construct(
        public readonly User $user,
        public readonly array $groups,
    ) {}

    /**
     * Předmět s počtem nových akcí.
     */
    public function envelope(): Envelope
    {
        $count = array_sum(array_map(fn (array $item): int => count($item['offers']), $this->groups));

        return new Envelope(subject: trans_choice('app.digest.subject', $count, ['count' => $count]));
    }

    /**
     * Obsah: položky s nejvýš `letaky.digest.max_offers_per_item` akcemi, odkaz na Moje slevy a na nastavení.
     */
    public function content(): Content
    {
        $limit = config()->integer('letaky.digest.max_offers_per_item');

        return new Content(markdown: 'mail.digest', with: [
            'firstName' => explode(' ', trim($this->user->name))[0],
            'items' => array_map(fn (array $item): array => [
                'name' => $item['name'],
                'offers' => array_map(fn (array $offer): array => [
                    'name' => $offer['name'],
                    'chain' => $offer['chain'],
                    'price' => $offer['price'] === null ? null : $this->formatPrice($offer['price']),
                    'discountPercent' => $offer['discountPercent'],
                    'validTo' => $offer['validTo']->format(self::DATE_FORMAT),
                ], array_slice($item['offers'], 0, $limit)),
                'total' => count($item['offers']),
                'more' => max(0, count($item['offers']) - $limit),
            ], $this->groups),
            'frequency' => $this->user->digest_frequency->label(),
            'homeUrl' => route('home'),
            'accountUrl' => route('account').'#souhrn',
        ]);
    }

    /**
     * Cena v haléřích jako „39,90 Kč“ — stejně jako formatPrice() na webu. Nezávisí na datech
     * ICU (vývojový kontejner má jen angličtinu a Intl by vrátil „CZK 39.90“).
     */
    private function formatPrice(int $halers): string
    {
        return number_format($halers / self::HALERS_PER_CROWN, self::PRICE_DECIMALS, ',', self::THOUSANDS_SEPARATOR).self::NO_BREAK_SPACE.self::CURRENCY;
    }
}
