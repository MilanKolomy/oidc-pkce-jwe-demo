# ADR-0001: Rozdělení rolí — Relying Party a vydavatel vlastního tokenu

**Status:** přijato
**Datum:** 16. 8. 2026
**Souvisí s:** FR-01, FR-10, NFR-05

---

## Kontext

Aplikace řeší dvě otázky, které spolu souvisejí, ale nejsou totožné: **kdo je uživatel**
(jednorázově při přihlášení) a **smí tento požadavek přistoupit k těmto datům** (při každém
volání rozhraní). V praxi se často slévají do jediného mechanismu — aplikace použije token
od poskytovatele identity i pro své rozhraní. Vnitřní autorizace tím ale zůstane trvale
svázaná s externím systémem.

Provoz vlastního poskytovatele identity byl vyloučen již v analýze: cílové prostředí
neumožňuje bezpečnou správu a rotaci podpisových klíčů (OMZ-02). Rozhodnutí se tedy týká
pouze toho, **jak autorizovat volání vlastního rozhraní** po ověření identity zvenčí.

---

## Zvažované varianty

| | Popis | Proti |
|---|---|---|
| **A — token poskytovatele** | Token od Googlu se použije i pro vlastní rozhraní. | Závislost na externím systému při každém volání. Obsah ani platnost aplikace neurčuje. Token nese nevyužívaná oprávnění k cizímu rozhraní. |
| **B — serverová relace** | Autorizace relační cookie. | Rozhraní použitelné jen z prohlížeče se sdílenou relací. Autorizaci nelze popsat ve specifikaci jako bezpečnostní schéma. |
| **C — vlastní token** | Aplikace si po ověření identity vystaví vlastní token. | Dva tokeny s odlišnými pravidly. Správa vlastního klíče. Token nelze zneplatnit před expirací. |

---

## Rozhodnutí

Zvolena je **varianta C**. Aplikace vystupuje ve dvou rolích současně: jako Relying Party
vůči Googlu a jako vydavatel vlastního aplikačního tokenu.

Důvodem je **oddělení odpovědností**. Ověření identity je jednorázová událost a je
legitimní ji svěřit externímu poskytovateli. Autorizace přístupu k vlastním datům je
trvalou odpovědností aplikace a neměla by být svázána s cizím systémem.

---

## Důsledky

**Kladné**

- Po přihlášení aplikace s poskytovatelem nekomunikuje; jeho výpadek nepřeruší práci
  přihlášených uživatelů.
- Obsah tokenu je pod kontrolou aplikace a lze jej omezit na nezbytné minimum (NFR-07).
- Rozhraní lze autorizovat i mimo prohlížeč a popsat ve specifikaci.
- Vzniká prostor pro rozlišení podepsaného a šifrovaného tokenu — viz ADR-0003.

**Záporné**

- Dva tokeny s odlišnými pravidly ověřování. Dokumentace je musí jasně odlišit.
- Aplikace spravuje vlastní klíč a nese odpovědnost za jeho ochranu.
- **Vydaný token nelze zneplatnit před uplynutím platnosti.** Odhlášení odstraní cookie,
  ale dříve zachycený token zůstává použitelný. Riziko je zmírněno patnáctiminutovou
  platností a přijato vědomě; řešením by byl seznam zneplatněných tokenů, který přesahuje
  rozsah studie.
- Výpadek poskytovatele znemožní **nové** přihlášení. Náhradní poskytovatel není.
