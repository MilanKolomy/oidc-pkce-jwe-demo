# CLAUDE.md — pokyny pro implementaci

Tento soubor popisuje **jak v tomto repozitáři pracovat**. Neobsahuje zadání — to je
v `docs/`. Přečti si ho celý před první změnou a řiď se jím i v dalších sezeních.

---

## 1. Východisko

Repozitář obsahuje **hotovou a schválenou dokumentaci**. Analýza, návrh, datový model
i specifikace rozhraní jsou dokončené. Tvým úkolem je je **naplnit kódem**, nikoli je
revidovat.

Dokumentace je jediným zdrojem pravdy. Kód se přizpůsobuje jí, ne naopak.

---

## 2. Co číst a kdy

### Před psaním jakéhokoli kódu (povinně)

| Soubor | Co z něj potřebuješ |
|---|---|
| `docs/02-omezeni-prostredi.md` | Tvrdá omezení prostředí. Návrh, který je poruší, je neplatný. |
| `docs/03-navrh-reseni.md` | Architektura, přihlašovací tok, tokeny, autorizační model. |
| `docs/04-datovy-model.md` | Proč je model takový, jaký je. |
| `docs/openapi.yaml` | Závazný kontrakt rozhraní. |
| `database/schema.sql` | Fyzické schéma včetně indexů a integritních omezení. |

### Při dotyku s danou oblastí

| Soubor | Kdy |
|---|---|
| `docs/adr/0003-jwe-aplikacni-token.md` | Vydávání a ověřování aplikačního tokenu. |
| `docs/adr/0004-samesite-lax.md` | Práce se session a cookie. |
| `docs/adr/0006-404-misto-403.md` | Autorizace přístupu k záznamům. |

### Nečíst, pokud se nezeptám

`docs/01-analyza-pozadavku.md`, `docs/05-mozna-rozsireni.md` a zbývající ADR jsou kontext
a zdůvodnění, nikoli instrukce.

---

## 3. Pravidla práce

### Minimalismus je závazný

**Implementuj pouze to, co je v `docs/`. Nic víc.**

Toto není doporučení, ale hlavní kritérium hodnocení. Kód navíc je horší než kód chybějící,
protože rozšiřuje plochu, kterou musím zkontrolovat a obhájit.

Konkrétně: žádné endpointy nad rámec `docs/openapi.yaml`, žádné sloupce nad rámec
`database/schema.sql`, žádné pomocné vrstvy „pro budoucí použití", žádné konfigurační
přepínače, které nikdo nepoužije, žádné ošetření případů, které nemohou nastat.

Pokud se ti zdá, že něco chybí, **napiš mi to a pokračuj bez toho**. Náměty patří do
`docs/05-mozna-rozsireni.md`, nikoli do kódu.

### Postupuj po krocích a čekej na mě

Práce probíhá v krocích podle kapitoly 9. **Po dokončení každého kroku se zastav** a:

1. shrň, co jsi udělal a jaká rozhodnutí jsi cestou učinil,
2. upozorni na cokoli, co ti přišlo sporné nebo nejasné,
3. **počkej na mou kontrolu** — nepokračuj dalším krokem sám.

Budu ti výstup procházet a připomínkovat. Připomínky zapracuj dřív, než půjdeš dál.

Ve fázích označených v kapitole 9 jako **[commit]** mi navrhni znění commit message
a vyzvi mě k provedení commitu. Commit provádím já, ne ty.

### Ostatní

- **Neupravuj dokumenty v `docs/`.** Pokud najdeš rozpor mezi dokumentací a realitou,
  **zastav se a řekni to** — neopravuj to potichu ani na jedné straně.
- **`README.md` uprav jen tam, kde je to nezbytné** (například sekce Stav). Stávající text
  neměň.
- **Ptej se místo hádání.** Rozhodnutí, která mají víc rozumných variant, patří mně.
- Commit messages anglicky, ve stylu stávající historie (`feat:`, `fix:`, `chore:`).

---

## 4. Tvrdá omezení (shrnutí)

Podrobně v `docs/02-omezeni-prostredi.md`. Nikdy je neobcházej:

- **PHP 8.1.33** na produkci. Nepoužívej syntaxi z 8.2+. Zafixuj: `composer config platform.php 8.1.33`
- **Bez příkazové řádky na produkci.** Nasazení je pouhé nahrání souborů. Žádný build krok,
  žádné generování za běhu.
- **Reverse proxy.** Absolutní adresy skládej výhradně přes jedinou komponentu, která čte
  `X-Forwarded-Proto`. Nikdy z `REQUEST_SCHEME`.
- **Vlastní log** do souboru mimo docroot. Nikdy do něj nezapisuj token, `code_verifier`
  ani tajemství.
- **MySQL 8.0**, `DATETIME` v UTC.

---

## 5. Struktura

```
src/                    PSR-4, namespace App\
├── Config/             načtení a validace konfigurace
├── Http/               Request, Response, Router, UrlBuilder
├── Oidc/               Discovery, JwksCache, TokenClient, IdTokenValidator, Pkce
├── Token/              vydání a ověření aplikačního tokenu (JWE)
├── Certificate/        parsování PEM, extrakce údajů, kontrola platnosti
├── Persistence/        PDO, repozitáře
├── Api/                controllery rozhraní
└── Exception/          vlastní hierarchie výjimek

public/                 docroot
├── .htaccess
├── .user.ini
├── index.php           front controller
├── assets/             vlastní CSS, pokud bude potřeba
└── swagger/            Swagger UI, statické

database/               schema.sql, migrate.php
keys/                   mimo docroot, v .gitignore, jen README.md
var/                    log, v .gitignore
```

---

## 6. Konvence kódu

- `declare(strict_types=1);` v každém souboru, PSR-12, PSR-4
- **Bez aplikačního frameworku** (ADR-0005). Router je rozvětvení podle cesty a metody.
- **Kryptografii neimplementuj vlastními silami** — použij `web-token/jwt-framework`.
  Před instalací ověř, která major verze podporuje PHP 8.1.
- Konstruktorová property promotion, typované vlastnosti, `readonly` u hodnotových objektů
- Výjimky místo návratových kódů
- **Veškerý přístup k databázi přes připravené dotazy**, nikdy konkatenací
- **Repozitáře vlastněných dat nemají metodu „najdi podle identifikátoru"** — pouze „najdi
  podle identifikátoru a vlastníka" (ADR-0006). Kontrolu vlastnictví nelze vynechat.
- Komentáře jen tam, kde vysvětlují *proč*
- Kód a komentáře anglicky

---

## 7. Uživatelské rozhraní

Rozhraní je **záměrně minimální**. Jde o princip, nikoli o vizuální stránku — každá minuta
strávená vzhledem je minuta neinvestovaná do podstaty.

- **Bootstrap 5 přes CDN**, jeden `<link>`. Tailwind nepřipadá v úvahu: produkční podoba
  vyžaduje build krok, který prostředí vylučuje.
- Bez JavaScriptového frameworku. Serverem generované HTML, minimum vlastního skriptu.
- Šablony jako obyčejné PHP soubory, **vždy s ošetřením výstupu**.
- Stránky: přihlášení, seznam certifikátů, detail certifikátu, vložení certifikátu, profil.
- Žádné vlastní barevné schéma, vlastní fonty ani animace.

Toto rozhodnutí zaznamenej jako **ADR-0008** ve stávajícím formátu (viz `docs/adr/README.md`)
a doplň jej do rejstříku.

---

## 8. Bezpečnostní minimum

Vychází z `docs/03-navrh-reseni.md`, kapitola 3 a 5:

- `state` — 32 náhodných bajtů, v session, ověřen na callbacku, po použití smazán
- `nonce` — generován, poslán, ověřen v `id_token`
- PKCE `S256`, nikdy `plain`
- ověření `id_token`: podpis proti JWKS, `iss`, `aud`, `exp`, `iat` (tolerance do 60 s), `nonce`
- **při neznámém `kid` znovu načti JWKS** — rotace klíčů nesmí způsobit výpadek
- cache JWKS je zrychlení, **nikoli podmínka funkce**
- aplikační token: `dir` + `A256GCM`, platnost 15 minut, cookie `HttpOnly` + `Secure` + `SameSite=Lax`
- `session_regenerate_id(true)` po úspěšném přihlášení
- vstup obsahující soukromý klíč **odmítni a nikam nezaznamenávej**
- žádné tajemství v Gitu, v URL ani v logu

---

## 9. Postup

Po každém kroku se zastav a počkej na mou kontrolu (viz kapitola 3). U kroků označených
**[commit]** navrhni znění commit message.

1. `composer.json`, `.gitignore`, `.env.example`, kostra adresářů **[commit]**
2. Konfigurace, front controller, router, logování, chybové odpovědi (RFC 7807) **[commit]**
3. Vrstva databáze a repozitáře **[commit]**
4. Přihlašovací tok (OIDC + PKCE) až po vydání aplikačního tokenu **[commit]**
5. Ověření tokenu a autorizace rozhraní **[commit]**
6. Parsování certifikátu a kontrola platnosti **[commit]**
7. Endpointy podle `docs/openapi.yaml` **[commit]**
8. Šablony rozhraní **[commit]**
9. Swagger UI, ADR-0008, aktualizace sekce Stav v `README.md` **[commit]**

---

## 10. Hotovo, když

- [ ] přihlášení přes Google funguje lokálně
- [ ] `id_token` se ověřuje včetně `nonce` a rotace JWKS
- [ ] aplikační token je skutečně JWE a bez klíče je nečitelný
- [ ] všech šest endpointů odpovídá `docs/openapi.yaml`
- [ ] cizí záznam vrací 404, ne 403
- [ ] chybové odpovědi mají formát RFC 7807
- [ ] rozhraní projde celý tok: přihlášení → vložení certifikátu → detail → kontrola
- [ ] v repozitáři není žádné tajemství
- [ ] `composer.json` cílí na PHP 8.1.33
- [ ] existuje ADR-0008 a je v rejstříku
- [ ] **v kódu není nic, co by neplynulo z `docs/`**
