<?php

declare(strict_types=1);

/** Converteix els partits assignats en un missatge llest per compartir. */
final class FormatadorMissatge
{
    private const DIES = [
        1 => 'Dilluns',
        2 => 'Dimarts',
        3 => 'Dimecres',
        4 => 'Dijous',
        5 => 'Divendres',
        6 => 'Dissabte',
        7 => 'Diumenge',
    ];

    public function __construct(private readonly ClientAgenda $agenda)
    {
    }

    /** Retorna el text de la proposta agrupat en partits a casa i fora. */
    public function generarMissatge(array $partits, array $config, string $salutacio = 'Bona nit!'): string
    {
        $idClub = $partits === []
            ? null
            : $this->agenda->identificarIdClub($partits, (string) ($config['club'] ?? ''));
        $delegades = $this->normalitzarNoms($config['delegades'] ?? []);
        $grups = ['casa' => [], 'fora' => []];

        foreach ($partits as $partit) {
            if (empty($partit['data']) || empty($partit['hora'])) {
                continue;
            }
            $tipus = $partit['id_club_local'] === $idClub ? 'casa' : 'fora';
            $grups[$tipus][$partit['data']][] = $partit;
        }

        $linies = [trim($salutacio), 'Proposta per aquest cap de setmana.', '', '*Partits a casa*'];
        $this->afegirDies($linies, $grups['casa'], true, $delegades);
        $linies[] = '';
        $linies[] = '*Partits fora*';
        $this->afegirDies($linies, $grups['fora'], false, $delegades);

        return implode("\n", $linies);
    }

    private function afegirDies(array &$linies, array $partitsPerData, bool $aCasa, array $delegades): void
    {
        ksort($partitsPerData);
        foreach ($partitsPerData as $data => $partits) {
            $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $data, new DateTimeZone('Europe/Madrid'));
            if ($dia === false) {
                continue;
            }
            usort($partits, static fn (array $a, array $b): int => strcmp($a['hora'], $b['hora']));
            $linies[] = '';
            $linies[] = '_' . self::DIES[(int) $dia->format('N')] . ' ' . $dia->format('d/m') . '_';

            foreach ($partits as $partit) {
                $delegat = $partit['delegat'] ?? null;
                $etiquetaDelegat = is_string($delegat) && isset($delegades[mb_strtolower($delegat, 'UTF-8')])
                    ? 'Delegada'
                    : 'Delegat';
                $nomDelegat = is_string($delegat) && trim($delegat) !== '' ? $delegat : 'pendent d’assignar';
                $linia = $partit['hora'] . ' ' . $this->nomEquip($partit) . ': ' . $etiquetaDelegat . ' ' . $nomDelegat;

                if ($aCasa) {
                    $taula = $partit['taula'] ?? null;
                    $nomTaula = is_string($taula) && trim($taula) !== '' ? $taula : 'pendent d’assignar';
                    $linia .= ', Taula ' . $nomTaula;
                }

                $linies[] = $linia;
            }
        }
    }

    private function nomEquip(array $partit): string
    {
        $categoria = (string) ($partit['categoria_edat'] ?? $partit['nom_equip_club'] ?? 'Equip');
        if (mb_strtolower($categoria, 'UTF-8') === 'prebenjami') {
            $categoria = 'Prebenjamí';
        }

        $lletra = $partit['equip_club'] ?? null;
        if (is_string($lletra) && $lletra !== '') {
            return $categoria . ' ' . $lletra;
        }

        if ($categoria === 'Prebenjamí' && !empty($partit['nivell'])) {
            return $categoria . ' ' . $partit['nivell'];
        }

        return $categoria;
    }

    private function normalitzarNoms(array $noms): array
    {
        $resultat = [];
        foreach ($noms as $nom) {
            if (is_string($nom) && trim($nom) !== '') {
                $resultat[mb_strtolower(trim($nom), 'UTF-8')] = true;
            }
        }

        return $resultat;
    }
}
