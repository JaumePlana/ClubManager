<?php
require_once dirname(__DIR__) . '/src/AgendaClient.php';
require_once dirname(__DIR__) . '/src/AssignadorPartits.php';

$configuracio = require dirname(__DIR__) . '/config.php';
$agenda = new ClientAgenda((int) $configuracio['temporada_id']);
$partits = $agenda->obtenirPartits();
$idClub = $agenda->identificarIdClub($partits, $configuracio['club']);
$partitsSetmana = $agenda->partitsClubPerSetmana($partits, $idClub, '2026-11-18');
$assignador = new AssignadorPartits($agenda);
$partitsAssignats = $assignador->afegirDelegatITaula($partitsSetmana, $configuracio);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($partitsAssignats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

