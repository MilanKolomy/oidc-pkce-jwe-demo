# ADR-0007: Verzování rozhraní v cestě

**Status:** přijato
**Datum:** 16. 8. 2026
**Souvisí s:** FR-13, NFR-09

---

## Kontext

Rozhraní bude popsáno veřejnou specifikací a volané klienty, jejichž úpravu aplikace
neřídí. Nekompatibilní změna proto nemůže být provedena tichou úpravou existujících
koncových bodů.

Způsob verzování je nutné určit **před vydáním první verze** — zavést jej dodatečně
znamená rozbít všechny existující klienty právě tou změnou, které má verzování předcházet.

---

## Zvažované varianty

| | Popis | Proti |
|---|---|---|
| **A — v cestě** | `/api/v1/certificates` | Verze je součástí identifikátoru zdroje, ačkoli jde o vlastnost jeho reprezentace. Puristicky nekorektní. |
| **B — v hlavičce** | Vlastní typ obsahu v hlavičce `Accept`. | Verzi nelze zjistit z adresy ani ze záznamů. Zkoušení vyžaduje nástroj, který umí nastavit hlavičku — v prohlížeči nefunguje. Komplikuje mezipaměti. |
| **C — parametrem dotazu** | `?version=1` | Snadno se vynechá; chybějící hodnota vyžaduje výchozí verzi, čímž se problém vrací. Zasahuje do prostoru parametrů zdroje. |

---

## Rozhodnutí

Zvolena je **varianta A**, tedy hlavní číslo verze v cestě.

Rozhoduje **zjistitelnost**. Verze je viditelná v adresním řádku, v provozních záznamech
i v příkazu pro ruční volání. Nesprávná verze se projeví jako neexistující cesta, nikoli
jako nečekaná odpověď. Souběžný provoz dvou verzí je otázkou směrování, nikoli větvení
uvnitř obsluhy požadavku.

Teoretická výhrada k variantě A je uznána: verze skutečně popisuje reprezentaci, nikoli
zdroj. V praxi převažuje čitelnost.

Ve specifikaci se verze uvádí v adrese serveru, nikoli u jednotlivých cest — jinak by
se prefix v odvozených adresách zdvojil.

---

## Důsledky

**Kladné**

- Verzi lze zjistit z adresy bez znalosti hlaviček; rozhraní je vyzkoušitelné z prohlížeče.
- Souběžný provoz více verzí je možný bez zásahu do obsluhy požadavků.
- Chování mezipamětí zůstává standardní.

**Záporné**

- Změna hlavní verze mění adresy všech zdrojů, i těch, které se nezměnily.
- Verzuje se rozhraní jako celek, nikoli jednotlivé zdroje. Drobná nekompatibilní změna
  v jednom místě vynutí novou verzi celku.
