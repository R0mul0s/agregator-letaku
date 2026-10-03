<?php

/**
 * Krmivo pro zvířata (R50). Hlídané „hovězí maso“ by jinak našlo „Friskies hovězí v želé“
 * a kvůli nízké ceně za kilo ho dalo na první místo. Krmivo se proto ukáže jen u hlídání,
 * které je samo o zvířatech (krmivo, kočky, psi, Whiskas…). Pravidla jsou v konfiguraci
 * `letaky.pet_food`.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-03
 */

declare(strict_types=1);

namespace App\Domain\Matching;

final class PetFood
{
    /**
     * Je akce krmivo? Kategorie obchodu, nebo začátek slova v normalizovaném textu akce.
     *
     * @param  string  $normalizedText  Text akce z TextNormalizer (mezera na začátku i na konci)
     */
    public function isPetOffer(?string $sourceCategory, string $normalizedText): bool
    {
        if ($sourceCategory !== null && in_array($sourceCategory, config()->array('letaky.pet_food.categories'), true)) {
            return true;
        }

        foreach (config()->array('letaky.pet_food.offer_words') as $word) {
            if (is_string($word) && str_contains($normalizedText, ' '.$word)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Je hlídání o zvířatech? Některé hledané slovo (nebo jeho alternativa) začíná slovem
     * ze seznamu — „kočk“, „krmivo“, „whiskas“.
     */
    public function isPetRule(WatchRule $rule): bool
    {
        $prefixes = config()->array('letaky.pet_food.rule_words');
        foreach ($rule->keywords as $alternatives) {
            foreach ($alternatives as $alternative) {
                foreach ($prefixes as $prefix) {
                    if (is_string($prefix) && str_starts_with($alternative, $prefix)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
