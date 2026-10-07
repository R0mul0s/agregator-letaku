<?php

/**
 * Text akce na více kusů pro zobrazení (R101). Tesco posílá u menu e-shopu kódy verzálkami
 * bez diakritiky („PECIVO+NAPOJ“, „MENU PIZZA+COLA“) a karta je ukazuje na místě ceny — vypadalo
 * to jako chyba. Text jen verzálkami se převede na větu s velkým prvním písmenem, známá slova
 * dostanou diakritiku (`letaky.offers.promotion_text_words`) a kolem „+“ jsou mezery. Text, který
 * už malá písmena má („od 3 ks: 19,90 Kč“), zůstane, jen s mezerami kolem „+“. V databázi se
 * nic nemění — `promotion_text` zůstává, jak ho obchod poslal.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-07
 */

declare(strict_types=1);

namespace App\Domain\Offers;

final class PromotionText
{
    /**
     * Text pro kartu, řádek a nákupní seznam; prázdný null.
     */
    public function forDisplay(?string $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '') {
            return null;
        }

        if ($this->isUppercaseOnly($text)) {
            $text = $this->sentenceCase(strtr($text, $this->accentedWords()));
        }

        return (string) preg_replace('/\s*\+\s*/u', ' + ', $text);
    }

    /**
     * Má text písmena a žádné malé? („PECIVO+NAPOJ“ ano, „4+2 zdarma“ ne, „2+1“ ne.)
     */
    private function isUppercaseOnly(string $text): bool
    {
        return preg_match('/\p{L}/u', $text) === 1 && preg_match('/\p{Ll}/u', $text) === 0;
    }

    /**
     * Malá písmena, první velké.
     */
    private function sentenceCase(string $text): string
    {
        $lower = mb_strtolower($text);

        return mb_strtoupper(mb_substr($lower, 0, 1)).mb_substr($lower, 1);
    }

    /**
     * Slova od obchodu bez diakritiky a jejich správný tvar, obojí verzálkami.
     *
     * @return array<string, string>
     */
    private function accentedWords(): array
    {
        $words = [];
        foreach (config()->array('letaky.offers.promotion_text_words') as $plain => $accented) {
            $words[(string) $plain] = (string) $accented;
        }

        return $words;
    }
}
