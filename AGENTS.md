# Slevohlídka — instrukce pro AI agenty

Instrukce pro tento projekt jsou v **[CLAUDE.md](CLAUDE.md)**, závazná pravidla
v [docs/CODING_GUIDELINES.md](docs/CODING_GUIDELINES.md), zadání s logem
rozhodnutí v [docs/PLAN.md](docs/PLAN.md), technické detaily zdrojů dat
obchodů v [docs/ZDROJE_DAT.md](docs/ZDROJE_DAT.md) a nasazení v
[deploy/DEPLOYMENT.md](deploy/DEPLOYMENT.md).

Tento soubor je jen rozcestník, aby existoval jeden zdroj pravdy.

> **Pozor:** Projekt běží výhradně v Dockeru. PHP, Composer ani Node se lokálně
> neinstalují. Příkazy se pouštějí přes `docker compose exec app …`,
> viz [CLAUDE.md](CLAUDE.md).
>
> Data obchodů pocházejí z **neveřejných rozhraní, která se mění**. Testy nikdy
> nesahají na síť (fixtures v `tests/Fixtures/<obchod>/`), ceny jsou v haléřích
> jako `int` a akce bez původní ceny není sleva. Viz CLAUDE.md, sekce
> *Nejčastější zdroje chyb*.
>
> Produkce je sdílený hosting bez SSH a fronty: každá migrace potřebuje SQL skript
> v `deploy/` a nic nesmí implementovat `ShouldQueue`.
