<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/AgendaClient.php';
require_once dirname(__DIR__) . '/src/AssignadorPartits.php';
require_once dirname(__DIR__) . '/src/FormatadorMissatge.php';

$configuracio = require dirname(__DIR__) . '/config.php';
$agenda = new ClientAgenda((int) $configuracio['temporada_id']);
$partits = $agenda->obtenirPartits();
$idClub = $agenda->identificarIdClub($partits, $configuracio['club']);
$partitsSetmana = $agenda->partitsClubPerSetmana($partits, $idClub, '2026-11-18');
$assignador = new AssignadorPartits($agenda);
$partitsAssignats = $assignador->afegirDelegatITaula($partitsSetmana, $configuracio);
$formatador = new FormatadorMissatge($agenda);

header('Content-Type: text/plain; charset=utf-8');
echo $formatador->generarMissatge($partitsAssignats, $configuracio), PHP_EOL;
