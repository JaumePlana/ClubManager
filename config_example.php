<?php

declare(strict_types=1);

/**
 * Exemple de configuració del club.
 * Copieu aquest fitxer com a config.php i substituïu les dades d'exemple.
 */
return [
    // Nom del club tal com apareix al començament del nom dels equips a FECAPA.
    'club' => 'Nom del club',

    // ID de temporada de FECAPA.
    'temporada_id' => 41,

    // Noms de pista tal com apareixen a l'agenda FECAPA.
    'pistes_casa' => [
        'Nom de la pista principal',
        'Nom de la pista alternativa',
    ],

    // Les claus han de coincidir amb el camp "nom_equip_club" del JSON.
    // Els delegats s'ordenen de més a menys preferit. Es poden posar tants
    // noms com calgui i una persona pot aparèixer en més d'un equip.
    'delegats_per_equip' => [
        'Aleví Plata A' => ['Maria', 'Pere', 'Joan'],
        'Aleví Plata B' => ['Pere', 'Laia'],
        'Benjamí Bronze' => ['Joan', 'Maria'],
        'FEM 13 Plata' => ['Laia', 'Maria'],
        'FEM 17 Plata' => ['Pere', 'Joan'],
        'Infantil Plata' => ['Maria', 'Laia'],
        'Prebenjami Bronze' => ['Joan', 'Pere'],
    ],

    // Noms que al missatge s'han de mostrar com a "Delegada".
    // Els noms que no apareguin aquí es mostraran com a "Delegat".
    'delegades' => [
        'Maria',
        'Laia',
    ],
];
