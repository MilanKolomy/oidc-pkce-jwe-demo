# ADR-0005: Bez aplikačního frameworku

**Status:** přijato
**Datum:** 16. 8. 2026
**Souvisí s:** NFR-01, cíl studie (kap. 1.3 analýzy)

---

## Kontext

Aplikace potřebuje směrování požadavků, přístup k databázi a serializaci odpovědí — tedy
právě to, co běžný framework poskytuje. Rozsah je malý: čtyři veřejné cesty a šest
koncových bodů rozhraní.

Pro rozhodnutí je určující **účel studie**. Hlavním výstupem není běžící aplikace, ale
doložení postupu a srozumitelnost návrhu. Kód slouží jako důkaz realizovatelnosti a čtenář
jej otevře na několik minut.

Prostředí přidává praktické omezení: závislosti se sestavují mimo produkci a nahrávají
jako součást aplikace (OMZ-02).

---

## Zvažované varianty

| | Popis | Proti |
|---|---|---|
| **A — plný framework** | Laravel, Symfony a podobné. | Hotová rozšíření vyřeší přihlášení několika řádky. Tím zmizí právě to, co má studie ukázat. Objem závislostí komplikuje nasazení bez příkazové řádky. |
| **B — mikroframework** | Slim a podobné. | Přináší osvědčené rozhraní požadavku a mezivrstev, autentizaci ale neřeší. Cestu požadavku zčásti skrývá za vlastní vrstvu. |
| **C — čisté PHP** | Vlastní směrování nad rámec autoloadingu. | Ruční řešení toho, co je jinde vyřešené. Riziko vlastních chyb v základní infrastruktuře. |

---

## Rozhodnutí

Zvolena je **varianta C**.

Rozhodující je čitelnost pro recenzenta: kde se ověřuje `state`, kde se kontroluje podpis,
kde se dešifruje token. Každá vrstva mezi vstupním bodem a touto logikou hraje proti účelu
dokumentu.

Riziko vlastního směrování je při šesti koncových bodech malé — nejde o směrovací
mechanismus, ale o rozvětvení podle cesty a metody. Kryptografické operace se **neimplementují
vlastními silami**; používá se zavedená knihovna pro práci s tokeny.

---

## Důsledky

**Kladné**

- Cesta požadavku je čitelná od vstupního bodu až k odpovědi bez znalosti cizí konvence.
- Malý objem závislostí zjednodušuje nasazení bez příkazové řádky (NFR-01).
- Návrh není vázán na životní cyklus a verzování frameworku.

**Záporné**

- Infrastrukturní kód, který framework poskytuje zdarma, je nutné napsat a udržovat.
- Chybí zavedené konvence — strukturu musí definovat dokumentace, jinak ji další vývojář
  neodhadne.
- **Rozhodnutí neplatí obecně.** Při větším rozsahu, více vývojářích nebo delší životnosti
  by volba frameworku byla namístě. Zde je odůvodněna účelem studie, nikoli obecnou
  nadřazeností.
