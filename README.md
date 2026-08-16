# oidc-pkce-jwe-demo

Analytická studie a ukázková implementace přihlášení přes OpenID Connect s ochranou PKCE
a s vlastním šifrovaným aplikačním tokenem (JWE).

---

## O co jde

Aplikace umožňuje uživateli přihlásit se účtem Google a evidovat vlastní X.509
certifikáty. Přihlášením to nekončí — aplikace si po ověření identity vystavuje **vlastní
token**, kterým autorizuje volání svého REST API.

Toto rozdělení je jádrem celého návrhu. Aplikace vystupuje ve dvou rolích současně:

- **Relying Party** vůči Googlu — přijímá a ověřuje tvrzení o identitě, které vydal někdo jiný
- **vydavatel aplikačního tokenu** — po ověření identity si vystaví vlastní token pro
  přístup k vlastním datům

Rozdíl mezi oběma tokeny je zároveň hlavním odborným tématem studie: první je **podepsaný**,
protože chrání původ tvrzení, druhý je **šifrovaný**, protože chrání jeho obsah.

---

## Kontext vzniku

Studie vznikla **z vlastní iniciativy** jako doprovodný materiál k přihlášce na pozici
IT analytika. Nejde o zadání od zákazníka ani o produkt určený k provozu.

Autor má dlouholetou praxi v IT, oblast PKI, OAuth 2.0 a OpenID Connect však dosud nebyla
jeho pracovní doménou. **Cílem proto není demonstrovat expertízu**, ale doložit postup,
jakým se autor orientuje v neznámé problematice: od studia specifikací, přes ověření
omezení cílového prostředí, po návrh řešení a jeho dokumentaci.

Hlavním výstupem je proto **dokumentace**, nikoli kód. Implementace slouží jako důkaz
realizovatelnosti návrhu.

Dokumentaci v `docs/` napsal autor. Implementaci psal Claude Code podle zadání
v [`CLAUDE.md`](CLAUDE.md) a podle této dokumentace — v devíti krocích, z nichž každý
autor procházel a schvaloval. Rozhodnutí zaznamenaná v `docs/adr/` a rozsah projektu
jsou prací autora; historie commitů odpovídá skutečnému postupu.

---

## Kudy začít

Dokumenty jsou číslované v pořadí, ve kterém vznikaly — a v tomtéž pořadí dávají smysl
při čtení.

| Dokument | Obsah |
|---|---|
| [01 — Analýza požadavků](docs/01-analyza-pozadavku.md) | Účel, aktéři, funkční a nefunkční požadavky, hranice rozsahu |
| [02 — Omezení prostředí](docs/02-omezeni-prostredi.md) | Co bylo na cílovém hostingu ověřeno a jak to ovlivnilo návrh |
| [03 — Návrh řešení](docs/03-navrh-reseni.md) | Architektura, přihlašovací tok, tokeny, autorizační model |
| [04 — Datový model](docs/04-datovy-model.md) | Entity, klíče, indexy a zdůvodnění zvolených typů |
| [Záznamy o rozhodnutích](docs/adr/README.md) | Osm rozhodnutí včetně zvažovaných variant a přijatých nevýhod |
| [Specifikace rozhraní](docs/openapi.yaml) | OpenAPI 3.1 |
| [05 — Možná rozšíření](docs/05-mozna-rozsireni.md) | Kudy by šlo pokračovat a co je nejbližší krok |

**Máte pět minut?** Přečtěte si kapitolu 1 a 4 návrhu řešení a záznam
[ADR-0001](docs/adr/0001-rozdeleni-roli.md).

**Zajímá vás způsob uvažování, ne výsledek?** Jděte rovnou do
[záznamů o rozhodnutích](docs/adr/README.md). Každý obsahuje i to, co bylo zvolenou
variantou ztraceno.

---

## Rozhraní

| Metoda | Cesta | Popis |
|---|---|---|
| GET | `/api/v1/me` | Profil přihlášeného uživatele |
| GET | `/api/v1/certificates` | Seznam vlastních certifikátů |
| POST | `/api/v1/certificates` | Vložení certifikátu ve formátu PEM |
| GET | `/api/v1/certificates/{id}` | Detail včetně historie kontrol |
| POST | `/api/v1/certificates/{id}/checks` | Vyžádání nové kontroly platnosti |
| GET | `/api/v1/authorities` | Seznam evidovaných certifikačních autorit |

Chybové odpovědi používají formát RFC 7807 (`application/problem+json`).

---

## Technické prostředí

- PHP 8.1, bez aplikačního frameworku ([proč](docs/adr/0005-bez-frameworku.md))
- MySQL 8.0
- Google jako poskytovatel identity
- Sdílený hosting bez přístupu k příkazové řádce — nasazení je pouhé nahrání souborů

---

## Co aplikace nedělá

Uvedeno záměrně, aby to nebylo objeveno jako překvapení:

- **nevydává ani nepodepisuje certifikáty** — pracuje výhradně s veřejnou částí
- **neověřuje odvolání ani řetěz důvěry** — platnost posuzuje pouze podle časového rozsahu
- **nepřijímá soukromé klíče** — vstup obsahující soukromý klíč je odmítnut
- **neruší přihlášení u Googlu** — odhlášení ukončí pouze relaci v této aplikaci
- **neumí zneplatnit vydaný token před jeho expirací** — riziko je zmírněno krátkou
  platností ([podrobněji](docs/adr/0001-rozdeleni-roli.md))

Náměty na pokračování jsou v [05 — Možná rozšíření](docs/05-mozna-rozsireni.md).

---

## Struktura repozitáře

```
├── docs/
│   ├── 01-analyza-pozadavku.md
│   ├── 02-omezeni-prostredi.md
│   ├── 03-navrh-reseni.md
│   ├── 04-datovy-model.md
│   ├── 05-mozna-rozsireni.md
│   ├── openapi.yaml
│   └── adr/                  záznamy o rozhodnutích
├── src/                      implementace
├── templates/                šablony rozhraní
├── database/                 schéma a migrace
├── public/                   veřejný adresář
└── CLAUDE.md                 zadání pro nástroj, podle kterého vznikl kód
```

Těžištěm je `docs/`. Kód slouží jako doklad realizovatelnosti návrhu.

---

## Stav

Dokumentace i implementace jsou dokončeny.

Aplikace běží lokálně v celém rozsahu: přihlášení účtem Google, vložení certifikátu,
detail, kontrola platnosti, všech šest endpointů rozhraní a Swagger UI. Na produkci
zatím nasazena není.

Vývojové prostředí je popsáno v [02 — Omezení prostředí](docs/02-omezeni-prostredi.md).
Nasazení spočívá v nahrání souborů včetně adresáře `vendor/`, vytvoření schématu podle
[`database/schema.sql`](database/schema.sql), doplnění `.env` podle `.env.example`
a vytvoření šifrovacího klíče podle [`keys/README.md`](keys/README.md).

**Poznámka ke Swagger UI.** Specifikace uvádí jako první server produkci, takže se
Swagger UI implicitně ptá jí. Při zkoušení proti jinému prostředí je nutné přepnout
volbu serveru **na dvou místech**: v hlavičce stránky a ještě zvlášť u každé operace,
kde má přednost volba operace nad globální. Není to chyba nasazení.

---

## Licence

MIT
