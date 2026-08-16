# 05 — Možná rozšíření

Náměty na pokračování. Nejde o nedodělky — vše zde uvedené bylo vědomě vyloučeno
z rozsahu studie (kapitola 6 analýzy požadavků).

---

## Nejblíž k dokončení

**Ověření odvolání certifikátu.** Nejpřirozenější pokračování domény. Znamená stáhnout
seznam odvolaných certifikátů nebo se dotázat na stav v reálném čase a rozšířit výsledek
kontroly o další stav. Teprve tím se z posouzení platnosti stane skutečné ověření.

**Zneplatnění vydaného tokenu.** Odstraňuje jedinou skutečnou slabinu bezpečnostního
návrhu (ADR-0001). Vyžaduje evidenci zneplatněných identifikátorů tokenů a její kontrolu
při každém volání.

**Automatizované testy.** Především přihlašovacího toku a autorizačního pravidla
z ADR-0006 — tedy tam, kde je chyba nejdražší a nejhůř viditelná.

---

## Dál od jádra

| Námět | Poznámka |
|---|---|
| **Řetěz důvěry** | Rozlišení kořenových a mezilehlých autorit, ověření cesty k důvěryhodnému kořeni. Znamená zavést do modelu hierarchii. |
| **Upozornění na blížící se expiraci** | Praktická hodnota evidence roste, jakmile sama upozorní. Vyžaduje pravidelně spouštěnou úlohu, což prostředí komplikuje. |
| **Další poskytovatel identity** | Model je připraven — párování probíhá na identifikátor od poskytovatele, stačí doplnit jeho označení. |
| **Model hrozeb podle OWASP ASVS** | Bezpečnostní požadavky existují, chybí jejich systematické ověření proti uznávanému katalogu. |

---

## Mimo dosah studie

Skutečné podepisování dokumentů a vzdálené kvalifikované podepisování podle eIDAS
vyžadují správu soukromých klíčů v hardwarovém modulu a splnění regulatorních požadavků.
Nejde o rozšíření tohoto projektu, ale o jiný projekt.
