# ClubManager

Integracio PHP inicial amb l'agenda d'hoquei patins de la FECAPA.

## Consulta de partits

`src/AgendaClient.php` descarrega l'agenda amb un token SIDGAD nou, interpreta
els partits i permet filtrar-los per club i per la setmana de dilluns a
diumenge que conté una data. Els identificadors de club són els codis
numèrics de FECAPA.

```php
require_once __DIR__ . '/src/AgendaClient.php';

$agenda = new ClientAgenda(41); // ID de temporada observat al fitxer HAR.
$partits = $agenda->obtenirPartits();
$partitsSetmana = $agenda->partitsClubPerSetmana($partits, 657, '2026-11-18');
$partitsIdentificats = array_map(
    fn (array $partit): array => $agenda->identificarEquipClub($partit, 657),
    $partitsSetmana,
);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($partitsIdentificats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
```

Els camps dels partits són `competicio`, `data`, `hora`, `id_club_local`,
`id_club_visitant`, `equip_local`, `equip_visitant`, `resultat`, `id_pista` i
`pista`. L'hora és local de `Europe/Madrid` i no es retorna cap camp `kickoff`.
`identificarEquipClub()` afegeix `categoria`, `categoria_edat`, `nivell`,
`grup`, `equip_club` i `nom_equip_club`. Els nivells es mostren com Bronze,
Plata i Or; les categories FEM es mantenen en majuscules.

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
