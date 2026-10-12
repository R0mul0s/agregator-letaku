/**
 * Čerstvost dat: „Aktualizováno dnes v 6:30“ / „včera v …“ / „11. 10. v …“ podle konce posledního
 * stažení — úvodní stránka a Všechny akce (R137, R141). Texty jsou v ui.updated.
 *
 * @author Roman Hlaváček
 * @created 2026-10-12
 */
import { formatShortDate, formatTime, localDateOf } from '@/lib/format';

/** Den v milisekundách — „včera“. */
const DAY_MS = 24 * 60 * 60 * 1000;

/**
 * Text čerstvosti dat, nebo null bez stažení.
 *
 * @param {string|null} isoDateTime Konec posledního stažení (ISO 8601)
 * @param {string} locale
 * @param {string} timeZone Zóna zobrazení (sdílená vlastnost `timezone`)
 * @param {(key: string, replacements?: object) => string} t Překlad (useTranslations)
 * @param {number} [now] Teď v milisekundách
 * @returns {string|null}
 */
export function updatedLabel(isoDateTime, locale, timeZone, t, now = Date.now()) {
    if (!isoDateTime) {
        return null;
    }
    const day = localDateOf(isoDateTime, timeZone);
    const time = formatTime(isoDateTime, locale, timeZone);
    if (day === localDateOf(new Date(now).toISOString(), timeZone)) {
        return t('updated.today', { time });
    }
    if (day === localDateOf(new Date(now - DAY_MS).toISOString(), timeZone)) {
        return t('updated.yesterday', { time });
    }

    return t('updated.date', { date: formatShortDate(day, locale), time });
}
