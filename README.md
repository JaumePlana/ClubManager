# ClubManager

Integracio PHP inicial amb l'agenda d'hoquei patins de la FECAPA.

## Consulta de partits

`src/AgendaClient.php` descarrega l'agenda amb un token SIDGAD nou, interpreta
els partits i permet filtrar-los per club i per la setmana de dilluns a
diumenge que conté una data. Els identificadors de club són els codis
numèrics de FECAPA.

El client desa el token en un transient de WordPress per reutilitzar-lo entre
peticions i el renova amb marge, seguint la política de `portales.js`. Fora de
WordPress, el conserva mentre viu l'objecte `ClientAgenda`. Si la consulta de
l'agenda respon amb HTTP 401 o 403, elimina el token desat, en demana un de nou
i repeteix la consulta una vegada. Si també falla, retorna l'error perquè qui
crida el client pugui gestionar-lo. En PHP la renovació es comprova quan es fa
una petició; no hi ha cap temporitzador de fons com al navegador.

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
equip i després els dels altres equips que juguen a casa aquell dia, començant
pel partit més proper en horari. Una persona no pot tenir dues tasques al mateix
partit ni a la mateixa hora. Si les tasques són en partits de casa en pistes
diferents, cal que els inicis estiguin separats per almenys 90 minuts. Si no hi
ha cap persona disponible, el camp corresponent queda a `null`. Quan una persona
fa de Taula abans del seu partit fora com a Delegat, es respecten dues hores
entre els inicis.

`FormatadorMissatge::generarMissatge()` transforma aquests partits en un text
per compartir, agrupat per partits a casa i fora i ordenat per dia i hora.
Mostra el nom d'equip exactament com apareix al camp `nom_equip_club` del JSON.
Per generar-lo, carregueu també `src/FormatadorMissatge.php` i passeu-li els
partits assignats i la configuració. `delegats.delegades` permet indicar quins
noms han d'aparèixer com a «Delegada»; els altres es mostren com a «Delegat».
`tests/test_missatge.php` executa aquest flux i imprimeix el missatge.

Els scripts d'exemple són a `tests/`. Executeu-los des de l'arrel del projecte:

```sh
php tests/test.php
php tests/test_missatge.php
```

Els camps dels partits són `competicio`, `data`, `hora`, `id_club_local`,
`id_club_visitant`, `id_competicio`, `equip_local`, `equip_visitant`, `resultat`,
`gols_local`, `gols_visitant`, `id_pista` i `pista`. `id_competicio` s'extreu
del component final de `param_game` i identifica la competició, inclòs el grup
quan forma part del nom. El resultat conserva el format de
FECAPA (`gols local - gols visitant`) i els gols es retornen com a enters; si
el partit encara no s'ha jugat, `resultat`, `gols_local` i `gols_visitant` són
`null`. L'hora és local de `Europe/Madrid` i no es retorna cap camp `kickoff`.
`identificarEquipClub()` afegeix `categoria`, `categoria_edat`, `nivell`,
`grup`, `equip_club` i `nom_equip_club`. Els nivells es mostren com Bronze,
Plata i Or; les categories FEM es mantenen en majuscules.

## Configuració del club

Copieu `config_example.php` com a `config.php` i substituïu les dades d'exemple
pel nom del club, l'ID de temporada i les pistes de casa. Tota la configuració
de persones queda agrupada a `delegats`: `equips` conté els delegats per equip
en ordre de preferència, `delegades` indica quins noms es mostren com a
«Delegada», i `absencies` relaciona dates (`yyyy-mm-dd`) amb els noms absents.
Per exemple, `'absencies' => ['2026-11-28' => ['Maria', 'Pere']]`. Les claus de
`delegats.equips` han de coincidir amb `nom_equip_club`; una persona pot aparèixer
en diversos equips. Una persona absent no es considera per a cap assignació de
Delegat o Taula en cap partit d'aquella data.
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

## Integració amb WordPress

Carregueu `src/IntegracioWordPress.php` des del `functions.php` del tema fill o des del connector del club. Aquest fitxer registra el shortcode `[assignacio_delegats]`, que mostra el missatge de la setmana actual. També es pot fixar una data, per exemple `[assignacio_delegats data="2026-11-18"]`. `config.php` s'ha de mantenir a l'arrel del projecte i accessible al servidor.

El punt d'entrada exposa funcions PHP perquè el tema pugui presentar les dades amb el seu propi disseny:

```php
require_once '/ruta/al/ClubManager/src/IntegracioWordPress.php';

$partits = clubmanager_obtenir_partits_setmanals('2026-11-18');
$json = clubmanager_obtenir_partits_setmanals_json('2026-11-18');
```

Si no es passa una data, es fa servir el dia actual a `Europe/Madrid`. El shortcode mostra el missatge pla dins d'un element `<pre>`.

### API REST de WordPress

La ruta `GET /wp-json/clubmanager/v1/assignacio-delegats` retorna el missatge en text per defecte. Es pot indicar `?data=2026-11-18` per seleccionar la setmana que conté aquella data (format obligatori `yyyy-mm-dd`), i `&content-type=json` perquè retorni els partits assignats en JSON. Per exemple:

- `/wp-json/clubmanager/v1/assignacio-delegats`
- `/wp-json/clubmanager/v1/assignacio-delegats?data=2026-11-18`
- `/wp-json/clubmanager/v1/assignacio-delegats?data=2026-11-18&content-type=json`

`assignacio-delegats` és el nom de la ruta dins l'API REST. El shortcode `[assignacio_delegats]` continua disponible per inserir el missatge de text en una pàgina de WordPress.
