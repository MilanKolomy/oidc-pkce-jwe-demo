# 01 — Analýza požadavků

**Projekt:** oidc-pkce-jwe-demo — přihlášení přes OpenID Connect a evidence certifikátů

---

## 1. Účel a kontext

Studie vznikla **z vlastní iniciativy autora** jako doprovodný materiál k přihlášce na
pozici IT analytika. Nejde o zadání od zákazníka ani o produkt určený k provozu —
zadavatelem, analytikem i realizátorem je tatáž osoba.

Autor má dlouholetou praxi v IT, oblast PKI, OAuth 2.0 a OpenID Connect však dosud nebyla jeho pracovní doménou.

**Cílem proto není demonstrovat expertízu**, ale doložit postup, jakým se autor orientuje
v neznámé problematice: od studia specifikací, přes ověření omezení cílového prostředí,
po návrh řešení a jeho dokumentaci v podobě, podle které může pracovat vývojář i auditor.
Implementace slouží jako důkaz realizovatelnosti návrhu, nikoli jako hlavní výstup.

**Zvolenou doménou je evidence certifikátů.** Tematicky odpovídá oblasti důvěryhodných
služeb, vyžaduje skutečnou práci s certifikátem — aplikace jej parsuje a čte z něj data —
a vede k netriviálnímu datovému modelu se sdílenými číselníky. Aplikace certifikáty
**nevydává, nepodepisuje ani neověřuje řetěz důvěry**; pracuje výhradně s veřejnou částí.

Požadavky jsou číslované, aby se na ně dalo odkazovat z návrhu i ze záznamů o rozhodnutích.

---

## 2. Aktéři

| Aktér | Popis |
|---|---|
| **Uživatel** | Vlastník evidovaných certifikátů. Přihlašuje se Google účtem, spravuje výhradně vlastní záznamy. |
| **Google (OpenID Provider)** | Ověřuje identitu a vydává podepsaný `id_token`. Aplikace nad ním nemá kontrolu a musí počítat s rotací klíčů i výpadkem. |
| **Aplikace** | Vystupuje ve dvou rolích současně: *Relying Party* vůči Googlu a *vydavatel vlastního tokenu* pro přístup k vlastnímu API. |

Role správce není definována — aplikace nemá administrační rozhraní ani systém oprávnění.
Všichni uživatelé mají stejné možnosti, liší se pouze rozsahem vlastních dat.

---

## 3. Funkční požadavky

| ID | Požadavek |
|---|---|
| **FR-01** | Uživatel se přihlásí Google účtem. Aplikace nespravuje ani neukládá hesla. |
| **FR-02** | Při prvním přihlášení se účet automaticky založí. Párování probíhá na neměnný identifikátor `sub`, nikoli na e-mail. |
| **FR-03** | Uživatel zobrazí svůj profil: jméno, e-mail, datum registrace, čas posledního přihlášení. |
| **FR-04** | Uživatel se odhlásí. Aplikace zneplatní lokální session i vydaný token. |
| **FR-05** | Uživatel vloží certifikát ve formátu PEM jako text. Aplikace jej rozparsuje a uloží strukturovaná data. |
| **FR-06** | Aplikace při vložení rozpozná vydávající autoritu a účely klíče. Neznámé hodnoty doplní do sdílených číselníků. |
| **FR-07** | Uživatel zobrazí stránkovaný seznam svých certifikátů s vyznačením stavu platnosti. |
| **FR-08** | Uživatel zobrazí detail certifikátu včetně autority, účelů klíče a historie kontrol. |
| **FR-09** | Uživatel vyžádá novou kontrolu platnosti. Výsledek se zapíše do historie. |
| **FR-10** | Uživatel přistupuje výhradně k vlastním certifikátům. Cizí identifikátor je nerozlišitelný od neexistujícího. |
| **FR-11** | Sdílené číselníky (autority, účely klíče) jsou čitelné pro všechny přihlášené. Pravidlo z FR-10 se na ně neuplatňuje. |
| **FR-12** | Vstup, který není platným certifikátem, je odmítnut popisnou chybou, nikoli neošetřenou výjimkou. |
| **FR-13** | Rozhraní API je popsáno strojově čitelnou specifikací a je z ní přímo vyzkoušitelné. |

**K FR-02:** párování na `sub` místo e-mailu je věcné rozhodnutí — uživatel si může
e-mailovou adresu u Googlu změnit a záznam musí zůstat spojený s toutéž osobou.

**K FR-09:** kontrola je samostatnou operací s historií proto, že její výsledek závisí
na čase. Certifikát platný při vložení může být při pozdější kontrole po expiraci.

---

## 4. Nefunkční požadavky

| ID | Požadavek |
|---|---|
| **NFR-01** | Aplikace běží na PHP 8.1 na sdíleném hostingu bez přístupu k příkazové řádce. Nasazení nevyžaduje build krok ani spuštění nástroje na serveru. |
| **NFR-02** | Komunikace probíhá přes HTTPS. Aplikace správně určuje schéma a hostitele i za reverse proxy. |
| **NFR-03** | Autorizační tok je chráněn proti záměně kódu (PKCE S256), proti CSRF (`state`) a proti přehrání (`nonce`). |
| **NFR-04** | Aplikace ověřuje podpis `id_token` proti aktuální sadě klíčů poskytovatele. Rotace klíčů nezpůsobí výpadek. |
| **NFR-05** | Token opravňující k přístupu k osobním údajům je šifrovaný, nikoli pouze podepsaný, a má krátkou platnost. |
| **NFR-06** | Chybové odpovědi mají jednotný strojově zpracovatelný formát a v produkčním režimu neodhalují interní detaily. |
| **NFR-07** | Provozní log ani repozitář neobsahují žádné tajemství. Konfigurace je oddělena od kódu. |
| **NFR-08** | Dokumentace je česky, kód a komentáře anglicky. Každé podstatné rozhodnutí je zaznamenáno včetně zvažovaných alternativ. |
| **NFR-09** | Specifikace rozhraní a datový model v dokumentaci odpovídají skutečné implementaci. Rozpor je považován za vadu. |

---

## 5. Předpoklady a závislosti

**Předpoklady**

- Uživatel má funkční Google účet a je ochoten jej k přihlášení použít.
- Uživatel vkládá **veřejnou část** certifikátu. Aplikace nikdy nepřijímá ani neukládá
  soukromé klíče a v dokumentaci na to výslovně upozorňuje.
- Aplikace je určena pro jednotky až desítky uživatelů. Návrh neřeší škálování.

**Závislosti**

- **Google jako OpenID Provider** — výpadek znemožní přihlášení, záložní poskytovatel není.
- **Registrovaný OAuth klient u Googlu** — změna návratové adresy vyžaduje zásah v konzoli.
- **Knihovna pro práci s JOSE** — musí podporovat cílovou verzi PHP i formát JWE.
- **Sdílený hosting** — PHP 8.1.33, MySQL 8.0.34, HTTPS přes reverse proxy. Prostředí nelze měnit.

**Omezení prostředí, která přímo ovlivnila požadavky** (podrobně v `02-omezeni-prostredi.md`):
nižší verze PHP na produkci než ve vývoji, zakázané systémové funkce (odtud NFR-01),
reverse proxy hlásící nešifrované spojení (odtud NFR-02) a nedostupné systémové logy.

---

## 6. Mimo rozsah

Následující oblasti byly zváženy a vědomě vyloučeny. Důvody jsou uvedeny záměrně —
nevyřčený rozsah je hlavním zdrojem nedorozumění.

| Oblast | Důvod |
|---|---|
| **Vlastní Identity Provider** | Prostředí neumožňuje bezpečnou správu a rotaci podpisových klíčů. Externí poskytovatel navíc dává skutečné chování protokolu, ne simulaci. |
| **Podepisování a ověřování řetězu důvěry** | Vyžaduje správu soukromých klíčů a přístup k seznamům odvolaných certifikátů. Rozsahem přesahuje studii. |
| **Obnovovací tokeny, role a oprávnění** | Krátká platnost tokenu stačí; bez role správce nemá systém oprávnění co rozlišovat. |
| **Vícefaktorová autentizace, WebAuthn, passkeys** | Zajišťuje poskytovatel identity. Aplikace do síly ověření nezasahuje. |
| **SAML, CIBA, výměna tokenů** | Alternativní protokoly bez vazby na zvolený scénář. |
| **Automatizované testy a průběžná integrace** | Vědomé rozhodnutí zadavatele. Uvedeno, aby absence nebyla vykládána jako opomenutí. |

Náměty na pokračování jsou v dokumentu `09-mozna-rozsireni.md`.