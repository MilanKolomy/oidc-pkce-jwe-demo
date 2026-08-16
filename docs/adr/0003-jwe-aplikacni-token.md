# ADR-0003: Šifrovaný aplikační token místo podepsaného

**Status:** přijato
**Datum:** 16. 8. 2026
**Souvisí s:** NFR-05, NFR-07, ADR-0001

---

## Kontext

Aplikace si po ověření identity vystavuje vlastní token (ADR-0001). Token putuje
opakovaně přes prohlížeč a opravňuje k přístupu k osobním údajům.

Podepsaný token je čitelný pro každého, kdo jej získá — podpis chrání integritu,
nikoli důvěrnost.

---

## Zvažované varianty

| | Popis | Proti |
|---|---|---|
| **A — JWS** | Podepsaný, čitelný token. | Obsah je viditelný v prohlížeči i v záznamech proxy. Prozrazuje interní strukturu dat. |
| **B — Neprůhledný token** | Náhodný řetězec, údaje se dohledávají v databázi. | Dotaz do databáze při každém požadavku. Vyžaduje tabulku relací. |
| **C — JWE** | Šifrovaný token, čitelný pouze pro aplikaci. | Nutná správa šifrovacího klíče. Obsah nelze zkontrolovat bez klíče. |

---

## Rozhodnutí

Zvolena je **varianta C**, schéma `alg=dir` se symetrickým klíčem a `enc=A256GCM`.

Token vydává i ověřuje tatáž aplikace, asymetrické šifrování by proto přidalo složitost
bez přínosu. Použité schéma zajišťuje zároveň důvěrnost i integritu, samostatný podpis
tedy není potřeba.

Varianta B byla vyloučena kvůli dotazu do databáze při každém volání rozhraní.

---

## Důsledky

**Kladné**

- Obsah tokenu není čitelný mimo aplikaci (NFR-05).
- Ověření nevyžaduje přístup k databázi.
- Vzniká názorný protiklad k tokenu od poskytovatele: ten chrání původ tvrzení, tento
  chrání jeho obsah.

**Záporné**

- Aplikace spravuje šifrovací klíč. Jeho ztráta zneplatní všechny vydané tokeny,
  jeho únik je zpřístupní.
- Obsah tokenu nelze zkontrolovat bez klíče, což ztěžuje ladění.
- Token nelze zneplatnit před uplynutím platnosti (převzato z ADR-0001).
