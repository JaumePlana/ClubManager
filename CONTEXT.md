Sóc un dels administradors d'un Club d'Hoquei Patins.
Una de les meves tasques, és distribuir cada setmana qui seran els Delegats i "Taules" de cada partit que disputem.
A cada partit hi ha d'haver un Delegat. I hi ha una preferència de quins delegats van a cada equip.
A més, els partits que es juguen a casa, també necessitem una persona que faci de "Taula". Aquesta persona normalment és un dels delegats d'aquell equip, o un altre Delegat l'equip del qual juga també a casa abans o després d'aquell partit.
Trec la informació de partits d'aquesta web:
https://www.hoqueipatins.fecapa.cat/ag/

M'aniria molt bé poder muntar-lo a la pàgina web del Club (està creada amb WordPress sobre un Tema Personalitzat que té Divi com a pare). Per tant, el meu ideal seria crear amb codi PHP una funció que em retornin un JSON on indiqui la següent estructura, com a títol d'exemple:
- Partits a casa
    -  Dissabte 21/11
        - 10:00 Aleví A: Delegada Maria, Taula Pep
        - 11:15 Infantil: Delegat Pep, Taula Francesca
    - Diumenge 22/11
        - 12:30 FEM 17: Delegada Maria, Taula Francesca
- Partits fora
    - Divendres 20/11
        - 18:00 FEM 13: Delegat Alfred
    - Dissabte 21/11
        - 15:45 Benjamí: Delegada Laia
        - 16:00 Prebenjamí: Delegat Pablo

Com he dit, la informació de partits ve de la pàgina web, i jo podria configurar quins Delegats corresponen a cada equip.
Un Delegat pot ser Delegat de més d'un equip. I, per suposat, no pot estar a 2 partits al mateix temps. Per tant, en l'exemple anterior, la Laia no podria anar a fer de delegada del Prebenjamí, ni el Pablo de Benjamí.
Per poder estar de Delegat en 2 partits diferents (a part dels que es juguen a casa), ha d'haver un mínim de 2 hores de diferència entre els inicis de cada partit.

Coses a configurar:
- Quin Club vull gestionar.
- Nom de les Pistes quan es juga a casa (podríem tenir més d'una pista diferent).
- Relació Equips-Delegats.