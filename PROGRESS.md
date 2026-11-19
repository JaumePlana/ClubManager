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
  a casa i fora, ordenat per data i hora. Utilitza el valor de `nom_equip_club`
  tal com apareix al JSON, omet les seccions buides i separa les seccions amb
  dues línies en blanc. `config.php` permet indicar quins noms es mostren com
  a «Delegada».
- `config.php` conté el nom del club, l'ID de temporada, les pistes de casa,
  els delegats per equip i la llista de delegades. És un fitxer local ignorat
  per Git.
- Els scripts de prova són a `tests/`: `tests/test.php` imprimeix el JSON i
  `tests/test_missatge.php` imprimeix el missatge formatat. Les rutes de tots
  dos scripts apunten a `src/` i `config.php` a l'arrel del projecte.

## Execució dels exemples

Des de l'arrel del projecte:

```sh
php tests/test.php
php tests/test_missatge.php
```

## Repositori Git

- El remot `origin` apunta a `https://github.com/JaumePlana/ClubManager.git`.
- L'últim commit pujat és `14196c3` (`Format weekly match assignment message`).
- Els canvis locals posteriors (inclòs el trasllat dels scripts a `tests/`)
  encara no estan commitats.

## Properes passes

1. Revisar l'assignació de Taules amb els noms i les preferències reals del club.
2. Decidir com es gestionen equips nous o canvis de temporada a `config.php`.
3. Integrar el JSON i el missatge en una funció o punt d'entrada per a WordPress.
