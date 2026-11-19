# Estat del projecte

## Objectiu

Consultar l'agenda de partits de la FECAPA per al Club Hoquei Molins de Rei,
assignar Delegats i Taules segons la configuració del club i generar tant un
JSON com un missatge preparat per compartir.

## Fet fins ara

- `src/AgendaClient.php` consulta l'agenda de la FECAPA amb token SIDGAD i
  validació TLS, interpreta els partits i els filtra per club i per setmana
  (de dilluns a diumenge). També identifica l'ID FECAPA a partir del nom del
  club configurat.
- El JSON inclou el resultat i els gols locals i visitants per separat. Els
  camps dels gols són `null` si el partit encara no s'ha jugat.
- La identificació d'equip afegeix categoria, edat, nivell, grup, lletra i
  `nom_equip_club`.
- `src/AssignadorPartits.php` assigna Delegats segons l'ordre de preferència,
  respecta una separació mínima de dues hores entre partits com a Delegat i
  proposa Taula als partits de casa. Per a Taula prioritza els delegats del
  mateix equip i després els d'altres equips que juguen a casa abans o després
  aquell dia.
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
```

## Repositori Git i canvis locals

- El remot `origin` és `https://github.com/JaumePlana/ClubManager.git`.
- L'últim commit pujat és `3fb6cfc` (`Move example scripts into tests directory`).
- Els canvis actuals a `README.md`, `PROGRESS.md` i el nou `config_example.php`
  encara no estan commitats. `config.php` és ignorat i no s'ha d'incloure al
  repositori.

## Properes passes

1. Revisar l'assignació de Taules amb les preferències reals del club.
2. Decidir com gestionar equips nous o canvis de temporada a la configuració.
3. Integrar el JSON i el missatge en un punt d'entrada per a WordPress.
