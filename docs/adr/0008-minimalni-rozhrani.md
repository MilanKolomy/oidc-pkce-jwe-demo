# ADR-0008: Záměrně minimální uživatelské rozhraní

**Status:** přijato
**Datum:** 16. 8. 2026
**Souvisí s:** FR-04 až FR-09, NFR-01, OMZ-02, ADR-0005

---

## Kontext

Těžištěm studie je dokumentace: analýza, ověření prostředí, návrh a záznamy
o rozhodnutích. Implementace slouží jako doklad, že návrh je realizovatelný.

Uživatelské rozhraní je v tomto uspořádání **prostředkem, nikoli výstupem**. Musí
umožnit projít celý tok — přihlášení, vložení certifikátu, detail, kontrola platnosti —
a tím doložit, že popsané řešení funguje. Cokoli nad rámec toho odčerpává čas z části,
která je hodnocena.

Prostředí navíc vylučuje krok sestavení (OMZ-02): nasazení je pouhé nahrání souborů,
na produkci není příkazová řádka ani správce závislostí.

---

## Zvažované varianty

| | Popis | Proti |
|---|---|---|
| **A — Vlastní vzhled** | Vlastní styly, barevné schéma, typografie. | Čas investovaný do vzhledu není investován do podstaty. Výsledek se navíc posuzuje jako design, tedy v disciplíně, která není předmětem studie. |
| **B — Utilitní rámec (Tailwind)** | Moderní přístup, malý výsledný soubor. | Produkční podoba vyžaduje krok sestavení, který prostředí vylučuje. Použití nesestavené verze z CDN je v rozporu s doporučeným postupem autora. |
| **C — Hotový rámec z CDN, bez vlastního stylu** | Jediný `<link>`, žádné vlastní CSS, žádný JavaScript. | Rozhraní vypadá obecně, „jako Bootstrap". Vzhled závisí na dostupnosti cizí sítě. |

---

## Rozhodnutí

Zvolena je **varianta C**: Bootstrap 5 přes CDN, jediný `<link>`, žádné vlastní barevné
schéma, žádné vlastní fonty, žádné animace a žádný JavaScriptový rámec. Stránky generuje
server, šablony jsou obyčejné soubory PHP s ošetřením výstupu.

Rozhodnutí je **vědomé přiznání priorit**, nikoli úspora úsilí. Obecný vzhled je zde
sdělením: čtenář má posuzovat návrh, ne vzhled.

Varianta B nebyla vyloučena kvůli kvalitě nástroje, ale kvůli prostředí. Je to týž důvod,
proč se závislosti sestavují mimo produkci — omezení OMZ-02 se promítá i sem.

---

## Důsledky

**Kladné**

- Rozhraní vzniklo v řádu hodin a nedrží čas, který patří dokumentaci.
- Nasazení zůstává nahráním souborů; není co sestavovat ani generovat.
- Absence vlastního JavaScriptu zmenšuje plochu, kterou je nutné obhájit.
- Šablony jsou čitelné bez znalosti šablonovacího jazyka (navazuje na ADR-0005).

**Záporné**

- Rozhraní je vizuálně nerozlišitelné od jiných projektů na témže rámci.
- Vzhled závisí na dostupnosti CDN. Při výpadku zůstane stránka funkční, ale neformátovaná.
- Bez JavaScriptu chybí okamžitá zpětná vazba u formuláře; každá akce je celý požadavek.
- Seznam certifikátů v rozhraní nestránkuje. Stránkování má rozhraní API, kde je
  součástí kontraktu; v uživatelském rozhraní by šlo o práci bez užitku pro cíl studie.
