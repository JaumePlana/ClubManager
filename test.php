<?php
require_once __DIR__ . '/src/AgendaClient.php';

$agenda = new ClientAgenda(41);
$partits = $agenda->obtenirPartits();
$partitsSetmana = $agenda->partitsClubPerSetmana($partits, 657, '2026-11-18');
$partitsIdentificats = array_map(
    fn (array $partit): array => $agenda->identificarEquipClub($partit, 657),
    $partitsSetmana,
);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($partitsIdentificats, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
