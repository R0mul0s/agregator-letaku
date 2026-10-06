/**
 * „Samozničující“ service worker pro starou subdoménu slevohlidka.rhsoft.cz (R93).
 *
 * Aplikace přidaná na plochu ze zkušební subdomény má v prohlížeči uložený service worker,
 * soubory webu i offline kopie Mých slev, seznamu a Hlídám s daty uživatele. Při další
 * kontrole verze si prohlížeč stáhne tenhle skript: smaže všechna uložená data, odregistruje
 * se a otevřená okna načte znovu — stará adresa je pak přesměruje na slevohlidka.cz.
 * Odběr upozornění v telefonu zanikne s ním, na nové doméně se zapíná znovu.
 *
 * @author Roman Hlaváček
 * @created 2026-10-06
 */

self.addEventListener('install', () => self.skipWaiting());

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const keys = await caches.keys();
            await Promise.all(keys.map((key) => caches.delete(key)));
            await self.registration.unregister();
            const windows = await self.clients.matchAll({ type: 'window' });
            windows.forEach((client) => client.navigate(client.url));
        })(),
    );
});
