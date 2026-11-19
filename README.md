# ClubManager

Integracio PHP inicial amb l'agenda d'hoquei patins de la FECAPA.

## Consulta de partits

`src/AgendaClient.php` descarrega l'agenda amb un token SIDGAD nou, interpreta
els partits i permet filtrar-los per club i per la setmana de dilluns a
diumenge que conté una data. Els identificadors de club són els codis
numèrics de FECAPA.

```php
require_once __DIR__ . '/src/AgendaClient.php';
require_once __DIR__ . '/src/AssignadorPartits.php';

$configuracio = require __DIR__ . '/config.php';
$agenda = new ClientAgenda($configuracio['temporada_id']);
$partits = $agenda->obtenirPartits();
$idClub = $agenda->identificarIdClub($partits, $configuracio['club']);
$partitsSetmana = $agenda->partitsClubPerSetmana($partits, $idClub, '2026-11-18');
$assignador = new AssignadorPartits($agenda);
$partitsAssignats = $assignador->afegirDelegatITaula($partitsSetmana, $configuracio);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($partitsAssignats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
```

`afegirDelegatITaula()` afegeix els camps `delegat` i `taula`. Tria el primer
delegat disponible segons l'ordre de preferència configurat, sense assignar
la mateixa persona com a delegat a partits amb menys de dues hores entre els
seus inicis. A les partides de casa, proposa primer els delegats del mateix
equip i després els dels equips que juguen a casa abans o després aquell dia.
Si no hi ha cap persona disponible, el camp corresponent queda a `null`.

`FormatadorMissatge::generarMissatge()` transforma aquests partits en un text
per compartir, agrupat per partits a casa i fora i ordenat per dia i hora.
Mostra el nom d'equip exactament com apareix al camp `nom_equip_club` del JSON.
Per generar-lo, carregueu també `src/FormatadorMissatge.php` i passeu-li els
partits assignats i la configuració. El camp `delegades` de `config.php` permet
indicar quins noms han d'aparèixer com a «Delegada»; els altres es mostren com
a «Delegat». `tests/test_missatge.php` executa aquest flux i imprimeix el missatge.

Els scripts d'exemple són a `tests/`. Executeu-los des de l'arrel del projecte:

```sh
php tests/test.php
php tests/test_missatge.php
```

Els camps dels partits són `competicio`, `data`, `hora`, `id_club_local`,
`id_club_visitant`, `equip_local`, `equip_visitant`, `resultat`, `gols_local`,
`gols_visitant`, `id_pista` i `pista`. El resultat conserva el format de
FECAPA (`gols local - gols visitant`) i els gols es retornen com a enters; si
el partit encara no s'ha jugat, `resultat`, `gols_local` i `gols_visitant` són
`null`. L'hora és local de `Europe/Madrid` i no es retorna cap camp `kickoff`.
`identificarEquipClub()` afegeix `categoria`, `categoria_edat`, `nivell`,
`grup`, `equip_club` i `nom_equip_club`. Els nivells es mostren com Bronze,
Plata i Or; les categories FEM es mantenen en majuscules.

## Configuració del club

Editeu `config.php` per indicar el nom del club, l'ID de temporada, les pistes
de casa, els delegats disponibles per equip i quins noms s'han de mostrar com
a delegades. Dins de cada equip, poseu els
delegats en ordre de preferència. Les claus de `delegats_per_equip` han de
coincidir amb `nom_equip_club`. Una persona pot aparèixer en diversos equips.
L'ID FECAPA del club es detecta a partir del nom configurat i dels noms dels
equips que apareixen a l'agenda. El fitxer és local i està exclòs de Git perquè
pot contenir dades internes.

## Certificats TLS a Windows

Si cURL informa `unable to get local issuer certificate`, configureu un paquet
de certificats CA de confiança en lloc de desactivar la verificacio TLS.
Descarregueu el paquet actual de `https://curl.se/ca/cacert.pem` i deseu-lo en
una ruta estable, per exemple `C:\\php\\extras\\ssl\\cacert.pem`. Despres,
configureu `curl.cainfo` al `php.ini` del servidor web:

```ini
curl.cainfo = "C:\\php\\extras\\ssl\\cacert.pem"
```

Reinicieu el servidor web. Tambe podeu indicar la ruta directament:

```php
$agenda = new ClientAgenda(41, 'C:\\php\\extras\\ssl\\cacert.pem');
```

També s'accepta la variable d'entorn `CURL_CA_BUNDLE`. La verificacio del
certificat i del nom de servidor roman activada.

El lector requereix les extensions PHP cURL, DOM i mbstring. L'ID de temporada
`41` s'ha observat al HAR proporcionat i cal actualitzar-lo quan FECAPA canviï
de temporada. Els errors de xarxa o els canvis a l'agenda es comuniquen a qui
crida la funcio.
