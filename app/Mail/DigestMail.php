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
use App\Support\CzechVocative;
use App\Support\PriceFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class DigestMail extends Mailable
{
    /** Formát data konce platnosti v e-mailu („8. 10.“). */
    private const DATE_FORMAT = 'j.'.PriceFormatter::NO_BREAK_SPACE.'n.';

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
        $prices = app(PriceFormatter::class);

        return new Content(markdown: 'mail.digest', with: [
            // Oslovení v 5. pádě („Ahoj, Romane!“, R47)
            'firstName' => app(CzechVocative::class)->firstName($this->user->name),
            'items' => array_map(fn (array $item): array => [
                'name' => $item['name'],
                'offers' => array_map(fn (array $offer): array => [
                    'name' => $offer['name'],
                    'chain' => $offer['chain'],
                    'price' => $offer['price'] === null ? null : $prices->format($offer['price']),
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
}
