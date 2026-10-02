<?php

/**
 * Oslovení křestním jménem v 5. pádě („Ahoj, Romane!“) podle koncovky jména (R47).
 *
 * Pravidla pokrývají běžná česká jména: Roman → Romane, Petr → Petře, Marek → Marku,
 * Zdeněk → Zdeňku, Lukáš → Lukáši, Pavel → Pavle, Daniel → Danieli, Jana → Jano,
 * Julia → Julie; jména na samohlásku (Jiří, Marie, Ivo) zůstávají. Ženská jména na
 * souhlásku (Dagmar, Ester) jsou výjimky. Co pravidlům neodpovídá (víc slov, číslice),
 * vrátí beze změny — raději 1. pád než zkomolené jméno.
 *
 * @author Roman Hlaváček
 *
 * @created 2026-10-02
 */

declare(strict_types=1);

namespace App\Support;

final class CzechVocative
{
    /** Jméno, které se skloňuje: jen písmena (i se spojovníkem uvnitř by pravidla nesedla). */
    private const NAME_PATTERN = '/^\p{L}+$/u';

    /** Jména, která pravidla skloňují špatně: jméno malými písmeny => 5. pád (null = beze změny). */
    private const EXCEPTIONS = [
        'dagmar' => null,
        'ester' => null,
        'miriam' => null,
        'ingrid' => null,
        'karin' => null,
        'carmen' => null,
        'ruth' => null,
        'nikol' => null,
        'agnes' => null,
        'isabel' => null,
        'marcel' => 'marceli',
    ];

    /**
     * Koncovka => náhrada; první shoda vyhrává, proto delší a zvláštní koncovky dřív.
     * Prázdná náhrada u samohlásek = jméno beze změny.
     *
     * @var list<array{string, string}>
     */
    private const RULES = [
        // Daniel, Michael, Samuel → Danieli
        ['iel', 'ieli'], ['ael', 'aeli'], ['uel', 'ueli'],
        // Zdeněk → Zdeňku, Marek → Marku
        ['něk', 'ňku'], ['ek', 'ku'],
        // Pavel, Karel → Pavle (pohyblivé e); Emil, Michal spadnou do obecného +e
        ['vel', 'vle'], ['rel', 'rle'],
        // Julia, Sofia → Julie
        ['ia', 'ie'],
        ['a', 'o'],
    ];

    /** Koncové souhlásky, za které se přidá -u (Dominik → Dominiku, Vojtěch → Vojtěchu). */
    private const ENDINGS_U = ['k', 'h', 'g'];

    /** Koncové souhlásky, za které se přidá -i (Lukáš → Lukáši, Ondřej → Ondřeji, Max → Maxi). */
    private const ENDINGS_I = ['š', 'ž', 'č', 'ř', 'j', 'c', 'ť', 'ď', 'ň', 'x', 's', 'z'];

    /** Koncovky, u kterých je 5. pád stejný jako 1. (Jiří, Marie, Ivo, Testovací). */
    private const VOWELS = ['a', 'á', 'e', 'é', 'ě', 'i', 'í', 'o', 'ó', 'u', 'ú', 'ů', 'y', 'ý'];

    /**
     * 5. pád křestního jména (prvního slova celého jména).
     */
    public function firstName(string $fullName): string
    {
        $first = explode(' ', trim($fullName))[0];

        return $this->of($first);
    }

    /**
     * 5. pád jednoho jména; velikost písmen se zachová (ROMAN → ROMANE).
     */
    public function of(string $name): string
    {
        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            return $name;
        }

        $lower = mb_strtolower($name);
        $vocative = array_key_exists($lower, self::EXCEPTIONS) ? (self::EXCEPTIONS[$lower] ?? $lower) : $this->byRules($lower);

        return $name === mb_strtoupper($name) && mb_strlen($name) > 1
            ? mb_strtoupper($vocative)
            : mb_substr($name, 0, 1).mb_substr($vocative, 1);
    }

    /**
     * 5. pád jména malými písmeny podle koncovky.
     */
    private function byRules(string $name): string
    {
        foreach (self::RULES as [$ending, $replacement]) {
            if (str_ends_with($name, $ending)) {
                return mb_substr($name, 0, mb_strlen($name) - mb_strlen($ending)).$replacement;
            }
        }

        $last = mb_substr($name, -1);
        $beforeLast = mb_substr($name, -2, 1);

        return match (true) {
            in_array($last, self::VOWELS, true) => $name,
            in_array($last, self::ENDINGS_U, true) => $name.'u',
            in_array($last, self::ENDINGS_I, true) => $name.'i',
            // Petr, Alexandr → Petře; Igor, Viktor → Igore
            $last === 'r' && ! in_array($beforeLast, self::VOWELS, true) => mb_substr($name, 0, -1).'ře',
            default => $name.'e',
        };
    }
}
