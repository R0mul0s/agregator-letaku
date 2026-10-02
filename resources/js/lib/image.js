/**
 * Úprava obrázku v prohlížeči před nahráním — hosting nemusí mít knihovnu
 * na zpracování obrázků (R20, R40).
 *
 * @author Roman Hlaváček
 * @created 2026-10-02
 */

/** Formát výstupu; prohlížeč bez podpory WebP vrátí PNG. */
const OUTPUT_TYPE = 'image/webp';
const OUTPUT_QUALITY = 0.85;

/** Přípona souboru podle typu obrázku z canvasu. */
const EXTENSIONS = { 'image/webp': 'webp', 'image/png': 'png', 'image/jpeg': 'jpg' };

/**
 * Ořízne obrázek na čtverec ze středu a zmenší ho na nejvýš `size` px.
 *
 * @param {File} file Obrázek vybraný uživatelem
 * @param {number} size Strana výsledného čtverce
 * @returns {Promise<File>}
 * @throws {Error} Soubor nejde načíst jako obrázek
 */
export async function squareImage(file, size) {
    const bitmap = await createImageBitmap(file);
    const side = Math.min(bitmap.width, bitmap.height);
    const target = Math.min(side, size);

    const canvas = document.createElement('canvas');
    canvas.width = target;
    canvas.height = target;
    canvas.getContext('2d').drawImage(bitmap, (bitmap.width - side) / 2, (bitmap.height - side) / 2, side, side, 0, 0, target, target);
    bitmap.close();

    const blob = await new Promise((resolve, reject) => {
        canvas.toBlob((result) => (result ? resolve(result) : reject(new Error('canvas.toBlob'))), OUTPUT_TYPE, OUTPUT_QUALITY);
    });

    return new File([blob], `avatar.${EXTENSIONS[blob.type] ?? 'png'}`, { type: blob.type });
}
