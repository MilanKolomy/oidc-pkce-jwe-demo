# ADR-0004: Relační cookie s `SameSite=Lax`

**Status:** přijato
**Datum:** 16. 8. 2026
**Souvisí s:** NFR-03, OMZ-05, ADR-0002

---

## Kontext

V serverové relaci jsou uloženy hodnoty `state`, `nonce` a `code_verifier`, na kterých
stojí ochrana přihlašovacího toku (ADR-0002). Relace musí přetrvat odchod uživatele
k poskytovateli identity a jeho návrat.

Návrat probíhá jako **nadřazený přesměrovaný požadavek metodou GET z cizí domény**.
Nastavení atributu `SameSite` tím určuje, zda se relační cookie vůbec odešle — a je tedy
součástí bezpečnostního návrhu, nikoli provozním detailem.

Výchozí konfigurace hostingu atribut nenastavuje (OMZ-05).

---

## Zvažované varianty

| | Popis | Proti |
|---|---|---|
| **A — `Strict`** | Cookie se neodešle při žádném požadavku z cizí domény. | Cookie se neodešle ani při návratu od poskytovatele. Relace se jeví jako nová, ověření `state` selže a **přihlášení nefunguje**. |
| **B — nenastaveno** | Ponechání výchozího stavu. | Chování závisí na prohlížeči a mění se v čase. Návrh by spoléhal na okolnost, kterou neurčuje. |
| **C — `Lax`** | Cookie se odešle při nadřazené navigaci metodou GET, nikoli u ostatních požadavků z cizí domény. | Nechrání proti útokům vedeným přes navigaci GET; ty musí pokrýt jiná ochrana. |

---

## Rozhodnutí

Zvolena je **varianta C**, doplněná o atributy `Secure` a `HttpOnly` a o striktní režim
identifikátoru relace.

Hodnota `Strict` je pro tento tok nepoužitelná — nejde o kompromis mezi bezpečností
a pohodlím, ale o technickou nutnost. Zbytkové riziko pokrývá parametr `state`, který se
ověřuje právě proto, že cookie u navigace GET odeslána bude.

Nastavení se provádí **dvojitě**: konfiguračním souborem v adresáři aplikace a zároveň
v kódu při startu. Druhá cesta je nutná, protože vývojové prostředí konfigurační soubor
nemusí načítat.

---

## Důsledky

**Kladné**

- Přihlašovací tok funguje ve všech prohlížečích bez závislosti na jejich výchozím chování.
- Cookie není čitelná skriptem ani přenositelná nešifrovaným spojením.
- Nastavení je součástí repozitáře, nikoli konfigurace serveru — nasazení je opakovatelné.

**Záporné**

- Ochrana proti CSRF stojí a padá s parametrem `state`. Jeho vynechání by nebylo zachyceno
  nastavením cookie.
- Dvojí nastavení znamená dvě místa, která se mohou rozejít. Hodnoty musí být shodné
  a rozdíl je nutné považovat za vadu.
