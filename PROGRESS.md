# Estat del projecte

## Objectiu

Consultar l'agenda de partits de la FECAPA per al Club Hoquei Molins de Rei,
assignar Delegats i Taules segons la configuració del club i generar tant un
JSON com un missatge preparat per compartir.

## Fet fins ara

- `src/AgendaClient.php` consulta l'agenda de la FECAPA amb token SIDGAD i
  validació TLS, interpreta els partits i els filtra per club i per setmana
  (de dilluns a diumenge). Desa el token amb un marge de renovació a la memòria
  cau de WordPress i, fora de WordPress, a la instància del client. Si la
  consulta respon HTTP 401 o 403, invalida el token, en demana un de nou i ho
  prova una vegada més. Les capçaleres cURL reprodueixen les observades al HAR
  del navegador; després d'afegir-les, `php tests/test.php` ha tornat a
  funcionar. També identifica l'ID FECAPA a partir del nom del club configurat.
- El JSON inclou el resultat i els gols locals i visitants per separat. Els
  camps dels gols són `null` si el partit encara no s'ha jugat.
- Cada partit també inclou `id_competicio`, extret de l'últim component numèric
  de `param_game`; a les captures analitzades correspon unívocament al nom
  complet de la competició, inclòs el grup quan n'hi ha.
- La identificació d'equip afegeix categoria, edat, nivell, grup, lletra i
  `nom_equip_club`.
- `src/AssignadorPartits.php` assigna Delegats segons l'ordre de preferència,
  respecta una separació mínima de dues hores entre partits com a Delegat i
  proposa Taula als partits de casa. Per a Taula prioritza els delegats del
  mateix equip i després els d'altres equips que juguen a casa aquell dia,
  ordenats segons la proximitat horària. No assigna una persona a dues tasques
  al mateix partit o a la mateixa hora; si dues tasques a casa són en pistes
  diferents, exigeix 90 minuts entre els seus inicis. Si fa de Taula abans d'un
  partit fora com a Delegat, exigeix dues hores entre els inicis.
- `src/FormatadorMissatge.php` genera el text per compartir, agrupat en partits
  a casa i fora, ordenat per data i hora. Utilitza `nom_equip_club` tal com
  apareix al JSON i omet les seccions buides. Deixa dues línies en blanc entre
  les seccions presents. La configuració permet indicar quins noms es mostren
  com a «Delegada».
- `config.php` conté la configuració privada local del club i està exclòs de
  Git. `config_example.php` és la plantilla amb noms d'exemple; es copia com a
  `config.php` i s'emplena amb les dades pròpies.
- Els scripts de prova són a `tests/`: `tests/test.php` imprimeix el JSON i
  `tests/test_missatge.php` imprimeix el missatge formatat. Tots dos llegeixen
  `src/` i `config.php` des de l'arrel del projecte.

## Execució dels exemples

Des de l'arrel del projecte:

```sh
php tests/test.php
php tests/test_missatge.php
php tests/test_agenda.php
```

## Auditoria d'IDs per temporada

- L'agenda pública (`00_fecapa_agenda_1.php`) rep `id_temp`, però les consultes
  amb els valors 35–41 van retornar exactament el mateix conjunt de 12.255
  partits, del 20/02/2026 al 30/06/2027. Per tant, aquest endpoint no ha servit
  per obtenir les temporades històriques. Els resultats de totes set peticions
  tenien el mateix hash SHA-256.
- El HAR de `sidgad.cloud` mostra l'endpoint administratiu
  `77_games_list.php`, que rep `temp` (no `temp_id`) i `idclub`. Hi ha partits
  del Molins per a les temporades 35 (2023/24), 37 (2024/25), 39 (2025/26) i 41
  (2026/27).
- L'ID del Club Hoquei Molins de Rei és 657 a les quatre peticions i coincideix
  amb l'ID de l'agenda pública. Les files històriques no inclouen els IDs dels
  clubs rivals.
- El filtre de pistes conté 168 parelles ID–nom, idèntiques a les quatre
  temporades. Els 140 IDs no nuls observats a l'agenda pública coincideixen
  amb aquest filtre. Les files dels partits històrics només mostren el nom de
  la pista.
- La resposta administrativa no inclou cap ID de competició, només el nom. No
  s'ha pogut comprovar si els IDs de competició es reutilitzen entre temporades.
- El selector del HAR també mostra les temporades 29 (2020/21), 31 (2021/22) i
  33 (2022/23). Per cobrir sis temporades fins a 2026/27, els IDs visibles són
  31, 33, 35, 37, 39 i 41.

## Repositori Git i canvis locals

- El remot `origin` és `https://github.com/JaumePlana/ClubManager.git`, branca
  `master`.
- `config.php` i `exports/` (incloent-hi els HAR) són locals i estan ignorats.
- `tests/test_agenda.php` imprimeix els partits del club per a la setmana
  configurada al codi. Els altres scripts imprimeixen el JSON assignat i el
  missatge per compartir.

## Properes passes

1. Trobar una petició que retorni els IDs de competició per a les temporades
   anteriors; l'endpoint administratiu del HAR només retorna noms.
2. Si es volen auditar sis temporades completes, consultar també els IDs 31 i
   33 que apareixen al selector de `sidgad.cloud`.
3. Integrar el JSON i el missatge en un punt d'entrada per a WordPress.
