# 02 — Omezení cílového prostředí

**Projekt:** oidc-pkce-jwe-demo
**Navazuje na:** `01-analyza-pozadavku.md`
**Datum ověření:** 16. 8. 2026

---

## 1. Účel dokumentu

Omezení cílového prostředí nejsou detailem nasazení — jsou to **vstupní podmínky návrhu**.
Verze jazyka, zakázané systémové funkce a chování reverse proxy ovlivnily architekturu
dříve, než vznikl první řádek kódu. Tento dokument je proto zařazen před návrh řešení,
nikoli za něj.

**Metoda ověření.** Údaje pocházejí z výstupu `phpinfo()` spuštěného přímo na cílovém
hostingu, nikoli z dokumentace poskytovatele ani z předpokladů. Rozdíl je podstatný:
několik zjištění se od očekávání lišilo.

Jednotlivá omezení jsou číslována, aby se na ně dalo odkazovat z návrhu a ze záznamů
o rozhodnutích.

---

## 2. Přehled prostředí

| | Vývojové | Produkční |
|---|---|---|
| PHP | 8.2.30 | **8.1.33** |
| Rozhraní serveru | Apache s **mod_php** | **FPM/FastCGI** za reverse proxy |
| Operační systém | — | Debian |
| Databáze | MySQL 8.0.x | MySQL 8.0.34 |
| Přístup k příkazové řádce | ano | **ne** |
| Doména | `localhost:8080` | `monet.super-web.cz` |

Produkčním prostředím je sdílený hosting. **Konfiguraci nelze měnit** s výjimkou
souboru `.user.ini` v adresáři aplikace.

---

## 3. Zjištěná omezení

### OMZ-01 — Nižší verze PHP na produkci

Produkce běží na PHP 8.1.33, vývojové prostředí na 8.2.30. Cílem je tedy **nižší verze**,
což je opačně, než bývá zvykem.

**Dopad.** Nelze použít konstrukce zavedené v PHP 8.2. Riziko není v tom, že by je autor
použil vědomě, ale v tom, že je použije některá ze závislostí.

**Reakce návrhu.** Verze je uzamčena v konfiguraci správce závislostí, takže rozpor se
projeví už při sestavení, nikoli až při nasazení. Naplňuje NFR-01.

### OMZ-02 — Zakázané systémové funkce

Produkční konfigurace zakazuje mimo jiné `exec`, `shell_exec`, `system`, `passthru`,
`proc_open` a `popen`. Přístup k příkazové řádce serveru není k dispozici.

**Dopad.** Na produkci nelze spustit správce závislostí, nástroje pro práci s klíči ani
jakýkoli krok sestavení. Nasazení musí být pouhé nahrání hotových souborů.

**Reakce návrhu.** Závislosti se sestavují mimo produkční prostředí a nahrávají jako
součást aplikace. Šifrovací klíč se generuje mimo server. Návrh nesmí obsahovat žádný
krok, který by na produkci vyžadoval spuštění nástroje. Naplňuje NFR-01.

### OMZ-03 — Reverse proxy hlásí nešifrované spojení

Aplikace běží za reverse proxy. Proměnné prostředí obsahují protichůdné údaje: schéma
a port ukazují na nešifrované spojení, zatímco hlavičky od proxy a příznak zabezpečení
dokládají, že klient komunikuje přes HTTPS.

**Dopad.** Absolutní adresa sestavená ze schématu v proměnných prostředí by začínala
`http://`. Poskytovatel identity porovnává návratovou adresu na přesnou shodu, takže
přihlášení by selhalo. Chyba by se navíc projevila **až na produkci**, protože ve
vývojovém prostředí proxy není.

**Reakce návrhu.** Absolutní adresy sestavuje jediná komponenta, která čte hlavičku od
proxy s ověřeným záložním zdrojem. Nikde jinde v aplikaci se adresa neskládá.
Naplňuje NFR-02.

### OMZ-04 — Nedostupné provozní logy

Zobrazování chyb je na produkci vypnuto a chybový výstup směřuje do standardního
chybového proudu serveru. Bez přístupu k příkazové řádce se k němu nelze dostat.

**Dopad.** Selhání na produkci by se projevilo prázdnou stránkou bez jakékoli informace
o příčině.

**Reakce návrhu.** Aplikace zapisuje **vlastní log do souboru mimo veřejný adresář**.
V produkčním režimu odpověď obsahuje pouze obecnou hlášku a korelační identifikátor,
podle kterého lze záznam v logu dohledat. Do logu nesmí být zapsán žádný token ani
tajemství. Naplňuje NFR-06 a NFR-07.

### OMZ-05 — Nevhodné výchozí nastavení session

Výchozí konfigurace neoznačuje relační cookie jako zabezpečenou, nenastavuje omezení
odesílání mezi weby a nevynucuje striktní režim identifikátoru relace.

**Dopad.** V session jsou uloženy hodnoty chránící autorizační tok proti CSRF a proti
záměně kódu. Slabě nastavená cookie oslabuje ochranu, kterou návrh na těchto hodnotách
staví.

**Reakce návrhu.** Parametry cookie jsou nastaveny **dvojitě** — souborem `.user.ini`
v adresáři aplikace a zároveň explicitně v kódu při startu aplikace.

Dvojí nastavení není opatrnost, ale nutnost. Soubor `.user.ini` **není ve vývojovém
prostředí účinný vůbec** (viz OMZ-08), takže tam celou ochranu nese pouze kód. Na produkci
je naopak `.user.ini` účinný, ale jde o konfiguraci cizího stroje, kterou nelze vynutit.
Ani jedna cesta tedy sama o sobě nestačí pro obě prostředí.

Ověřeno měřením: po startu aplikace mají parametry relační cookie požadované hodnoty
v obou případech. Naplňuje NFR-03.

### OMZ-06 — Verze databáze

Produkční databází je MySQL 8.0.34.

**Dopad.** Volba znakové sady a řazení musí odpovídat této verzi. Vývojové prostředí
by nemělo běžet na výrazně novější řadě, aby nevznikly rozdíly v chování.

**Reakce návrhu.** Schéma používá jednotné nastavení znakové sady a řazení podporované
touto verzí, shodné ve vývoji i na produkci.

### OMZ-07 — Chybějící sdílená mezipaměť

Vyhrazená služba pro sdílenou mezipaměť není k dispozici. Dostupná je pouze mezipaměť
v operační paměti procesu, s omezenou kapacitou.

**Dopad.** Sada veřejných klíčů poskytovatele identity, kterou je vhodné cachovat, nemá
spolehlivé sdílené úložiště. Při souběžném běhu více procesů může být uložena vícekrát.

**Reakce návrhu.** Cachování je navrženo jako **volitelné zrychlení, nikoli podmínka
správné funkce**. Aplikace musí fungovat i tehdy, když je mezipaměť prázdná nebo
nedostupná. Naplňuje NFR-04.

### OMZ-08 — Odlišné rozhraní serveru ve vývoji a na produkci

Vývojové prostředí běží na Apache s modulem `mod_php`, produkce na FPM/FastCGI. Zjištěno
až při implementaci, nikoli při prvotním ověření prostředí.

**Dopad.** Rozdíl není pouze formální. Pod `mod_php` se **nepoužívají soubory `.user.ini`**
— ty jsou vlastností CGI a FastCGI rozhraní. Konfigurace, která na produkci platí, je
lokálně bez účinku, a chyba v ní se tedy neprojeví tam, kde se vyvíjí.

Rozdíl se týká i způsobu, jakým se uplatňují direktivy PHP v `.htaccess`, a obsluhy
souběžných požadavků.

**Reakce návrhu.** Nastavení, na kterých závisí bezpečnost, se provádějí **v kódu**.
Konfigurační soubory slouží jako doplněk, nikoli jako jediný nositel ochrany (viz OMZ-05).

**Zbytkové riziko.** Chování konfigurace na produkci nelze z vývojového prostředí ověřit.
Po prvním nasazení je proto namístě přeověřit, zda se soubory, které nemají být veřejně
čitelné, skutečně neservírují.

### OMZ-09 — Chybějící seznam kořenových autorit ve vývojovém prostředí

Vývojové prostředí nemělo nastavený seznam důvěryhodných kořenových autorit. Distribuce
PHP pro Windows jej neobsahuje a příslušné direktivy byly prázdné. Zjištěno až při
implementaci, ověřeno výpisem hledaných cest — žádná z nich neexistovala.

**Dopad.** Jakékoli zabezpečené spojení selhalo, včetně stažení discovery dokumentu
a sady klíčů poskytovatele identity. Bez nich nelze ověřit podpis tvrzení o identitě,
tedy nelze se přihlásit.

Riziko není v samotném selhání, ale v nabízejícím se řešení: vypnout ověřování certifikátu
protistrany. V aplikaci, jejíž účelem je ověřovat důvěryhodnost tvrzení, by šlo o protimluv —
důvěra v obsah zprávy nemůže stavět na neověřeném kanálu.

**Reakce návrhu.** Žádná. Jde o mezeru v konfiguraci vývojového prostředí, nikoli o vlastnost,
na kterou by se měl návrh přizpůsobovat. Seznam byl do vývojového prostředí doplněn;
produkční Debian jej má systémově. Ověřování certifikátů zůstává zapnuté v obou prostředích
a kód se nezměnil.

---

## 4. Ověřená dostupnost rozšíření

Dostupnost následujících rozšíření byla ověřena a návrh na ní staví:

| Rozšíření | Využití v návrhu |
|---|---|
| `openssl` | Parsování certifikátů, kryptografické operace |
| `sodium` | Symetrické šifrování vlastního tokenu |
| `gmp`, `bcmath` | Podpora operací s velkými čísly v knihovnách |
| `PDO` s ovladačem pro MySQL | Přístup k databázi |
| `mbstring`, `json` | Zpracování textu a formátu JSON |
| `curl` | Komunikace s poskytovatelem identity |

**Návrh nesmí předpokládat nic nad rámec tohoto seznamu.** Naplňuje NFR-03 v analýze
požadavků.

---

## 5. Příznivá zjištění

Ne všechna zjištění byla omezující. Následující skutečnosti návrh naopak usnadnily:

- **Podpora souboru `.user.ini`** — konfiguraci PHP lze v omezeném rozsahu upravit pro
  adresář aplikace, bez zásahu do systémového nastavení. Řeší OMZ-05.
- **Velkorysé provozní limity** — dostupná paměť a maximální doba běhu výrazně převyšují
  potřeby aplikace. Výkon není omezujícím faktorem.
- **Kompletní kryptografická výbava** — obě potřebná rozšíření jsou přítomna, takže
  podepisování i šifrování tokenů lze realizovat bez kompromisů.

---

## 6. Neověřené skutečnosti a zbytková rizika

| Skutečnost | Riziko | Ošetření |
|---|---|---|
| Chování `.user.ini` v konkrétním adresáři aplikace | Nastavení se nemusí projevit | Parametry se nastavují i v kódu |
| Skutečná dostupnost a kvóty databáze | Nelze ověřit před vytvořením | Ověřit při prvním nasazení |
| Stabilita konfigurace hostingu v čase | Poskytovatel může nastavení změnit | Ověření zopakovat před odevzdáním |
| Chování při souběžném běhu více procesů | Mezipaměť se může chovat nepředvídatelně | Návrh na mezipaměti nezávisí (OMZ-07) |

---

## 7. Shrnutí

Prostředí neobsahuje žádnou překážku, která by znemožnila realizaci návrhu. Všechna
zjištěná omezení jsou **provozní povahy** — ovlivňují způsob sestavení, nasazení
a konfigurace, nikoli proveditelnost samotného řešení.

Tři zjištění by se bez předchozího ověření projevila až selháním na produkci:
rozdíl ve verzi jazyka (OMZ-01), chování reverse proxy (OMZ-03) a nedostupnost
provozních logů (OMZ-04). Právě proto bylo ověření prostředí zařazeno před návrh.

Dvě omezení (OMZ-08 a OMZ-09) byla nalezena až během implementace. Dokument byl doplněn
zpětně — zjištění o prostředí patří sem, nikoli do poznámek u kódu. Obě se týkají rozdílu
mezi vývojovým a produkčním prostředím, tedy právě té kategorie, která se při prvotním
ověřování odhaluje nejhůř.
