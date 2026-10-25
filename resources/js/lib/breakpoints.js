/**
 * Zlomy rozvržení pro JavaScript a atributy obrázků (sizes) — stejné hodnoty jako
 * $breakpoint-* v resources/scss/abstracts/_variables.scss (R113, dřív natvrdo v šablonách).
 *
 * @author Roman Hlaváček
 * @created 2026-10-09
 */

/** Střední displej a víc ($breakpoint-md). */
export const MEDIA_FROM_MD = '(min-width: 768px)';

/** Telefon — pod středním displejem ($breakpoint-md, mixin below-md). */
export const MEDIA_BELOW_MD = '(max-width: 767px)';

/** Telefon a tablet — pod širokým displejem ($breakpoint-lg, mixin below-lg). */
export const MEDIA_BELOW_LG = '(max-width: 1023px)';

/*
 * Šířka maskota v červeném pruhu stránky pro atribut `sizes` (výběr velikosti obrázku ze srcset,
 * R170 — dřív natvrdo v šablonách). Odpovídá vykreslené šířce podle tokenů v _tokens.scss;
 * 0px = na telefonu se obrázek neukazuje a nestáhne se větší varianta.
 */

/** Úvodní stránka nepřihlášeného (--landing-art-mascot-width z kruhu pruhu). */
export const LANDING_MASCOT_SIZES = `${MEDIA_FROM_MD} 284px, 160px`;

/** Moje slevy (--home-art-circle), na telefonu skrytý. */
export const HOME_MASCOT_SIZES = `${MEDIA_FROM_MD} 160px, 0px`;

/** Všechny akce, na telefonu skrytý. */
export const OFFERS_MASCOT_SIZES = `${MEDIA_FROM_MD} 150px, 0px`;

/** Kontakt (--contact-mascot-size a -mobile). */
export const CONTACT_MASCOT_SIZES = `${MEDIA_FROM_MD} 112px, 73px`;
