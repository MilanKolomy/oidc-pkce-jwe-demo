# Záznamy o rozhodnutích (ADR)

Každý záznam zachycuje jedno návrhové rozhodnutí: v jaké situaci vzniklo, jaké varianty
byly zvažovány, co bylo zvoleno a **jaké nevýhody z toho plynou**. Poslední bod je
záměrný — rozhodnutí bez uvedených nevýhod obvykle znamená, že alternativy nebyly zvážené.

Formát vychází z návrhu Michaela Nygarda. Záznamy se **nemění zpětně**; pokud pozdější
rozhodnutí to dřívější zruší, vzniká nový záznam a starý dostane status *nahrazeno*.

---

## Přehled

| # | Rozhodnutí | Status | Datum |
|---|---|---|---|
| [0001](0001-rozdeleni-roli.md) | Rozdělení rolí — Relying Party a vydavatel vlastního tokenu | přijato | 16. 8. 2026 |
| [0002](0002-pkce-s256.md) | PKCE i u klienta, který má tajemství | přijato | 16. 8. 2026 |
| [0003](0003-jwe-aplikacni-token.md) | Šifrovaný aplikační token místo podepsaného | přijato | 16. 8. 2026 |
| [0004](0004-samesite-lax.md) | Relační cookie s `SameSite=Lax` | přijato | 16. 8. 2026 |
| [0005](0005-bez-frameworku.md) | Bez aplikačního frameworku | přijato | 16. 8. 2026 |
| [0006](0006-404-misto-403.md) | Odpověď 404 místo 403 u cizích záznamů | přijato | 16. 8. 2026 |
| [0007](0007-verzovani-api.md) | Verzování rozhraní v cestě | přijato | 16. 8. 2026 |
| [0008](0008-minimalni-rozhrani.md) | Záměrně minimální uživatelské rozhraní | přijato | 16. 8. 2026 |

---

## Vazby mezi záznamy

Některá rozhodnutí na sebe navazují:

- **0001 → 0003** — rozdělení rolí zavádí vlastní token; teprve pak má smysl řešit, čím
  jej chránit.
- **0002 → 0004** — ochrana PKCE ukládá hodnoty do relace, čímž se nastavení relační
  cookie stává součástí bezpečnostního návrhu.
- **0006** rozvíjí autorizační model z kapitoly 5 návrhu řešení.
- **0005 → 0008** — rozhodnutí obejít se bez frameworku se v rozhraní opakuje: obyčejné
  šablony a hotové styly místo vlastního řešení.
