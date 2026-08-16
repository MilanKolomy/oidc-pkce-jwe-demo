# keys/

Symetrický klíč pro šifrování aplikačního tokenu (`alg=dir`, `enc=A256GCM` — viz
[ADR-0003](../docs/adr/0003-jwe-aplikacni-token.md)).

Adresář je **mimo veřejný adresář** (`public/`), takže obsah není dosažitelný přes web.
Do Gitu patří pouze tento soubor; klíč sám je vyloučen v `.gitignore`.

---

## Soubor

| Soubor | Obsah |
|---|---|
| `app-token.key` | 32 náhodných bajtů kódovaných v Base64, na jednom řádku |

Délka je dána schématem: `A256GCM` vyžaduje klíč o velikosti přesně 256 bitů.

---

## Vytvoření klíče

Klíč se generuje **mimo produkční server**. Produkční hosting nemá přístup k příkazové
řádce a zakazuje spouštění systémových funkcí (OMZ-02), generování za běhu tedy nepřipadá
v úvahu. Vytvořený soubor se na produkci nahraje spolu s aplikací.

```
php -r "file_put_contents('keys/app-token.key', base64_encode(random_bytes(32)) . PHP_EOL);"
```

Případně bez PHP:

```
openssl rand -base64 32 > keys/app-token.key
```

---

## Pravidla

- **Vývoj a produkce mají různé klíče.** Vývojový klíč se na produkci nikdy nepoužije.
- **Klíč se nikam nezaznamenává** — ani do logu, ani do chybové hlášky, ani do Gitu.
- **Výměna klíče zneplatní všechny vydané tokeny.** Uživatelé se musí přihlásit znovu.
  Při patnáctiminutové platnosti tokenu jde o krátkodobý dopad.
