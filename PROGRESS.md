# Estat del projecte

## Objectiu

Construir una eina PHP per consultar l'agenda de partits d'hoquei patins de la
FECAPA i, més endavant, ajudar a assignar-hi Delegats i Taules. El club es
configurarà amb el seu identificador FECAPA.

## Fet fins ara

- `src/AgendaClient.php` implementa la consulta de l'agenda amb token SIDGAD,
  el filtratge per club i la selecció dels partits de la setmana de dilluns a
  diumenge que conté una data.
- Es pot identificar la categoria, el nivell, el grup i la lletra A/B/C de
  l'equip del club.
- S'han catalanitzat els noms públics de les funcions i els camps JSON. La
  sortida inclou camps com `competicio`, `data`, `hora`, `id_club_local`,
  `id_club_visitant`, `equip_local`, `equip_visitant`, `resultat`, `id_pista`
  i `pista`. `identificarEquipClub()` hi afegeix `categoria`,
  `categoria_edat`, `nivell`, `grup`, `equip_club` i `nom_equip_club`.
- S'ha tret `kickoff` del JSON. Les dates i hores es tracten com a hora local
  de `Europe/Madrid`.
- Els nivells es mostren com Bronze, Plata i Or; les categories FEM mantenen
  FEM en majúscules.
- S'han actualitzat `README.md` i `test.php` amb els noms nous.
- S'ha comprovat la sintaxi PHP de `src/AgendaClient.php` i `test.php`.

## Repositori Git

- El directori no tenia repositori Git; s'hi va inicialitzar un repositori
  local buit.
- El remot `origin` apunta a
  `https://github.com/JaumePlana/ClubManager.git` (afegit manualment per
  l'usuari).
- `git ls-remote` no ha pogut consultar GitHub perquè l'entorn no disposa de
  credencials HTTPS.
- Encara no s'ha fet cap commit. `git add -A` no ha pogut crear
  `.git/index.lock`: l'entorn de Codex té `.git` en mode només lectura.
- El nom i el correu d'autor de Git ja estan configurats com `JaumePlana` i
  `125899854+JaumePlana@users.noreply.github.com`.

## Següents passos

1. Habilitar escriptura de Codex a tot `.git` (índex, objectes i referències).
2. Revisar l'estat i el diff, preparar els fitxers i crear el commit demanat.
3. Revisar `src/AgendaClient.php` i tornar a executar l'exemple amb
   `C:\Program Files\PHP\current\php.exe`.
4. Continuar amb el disseny de la configuració del club, les pistes de casa,
   les preferències d'equip i l'assignació de Delegats i Taules.
