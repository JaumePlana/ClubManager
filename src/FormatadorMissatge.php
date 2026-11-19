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

        $linies = [trim($salutacio), 'Proposta per aquest cap de setmana.'];
        $seccioAfegida = false;
        foreach ([
            'casa' => ['*Partits a casa*', true],
            'fora' => ['*Partits fora*', false],
        ] as $tipus => [$titol, $aCasa]) {
            if ($grups[$tipus] === []) {
                continue;
            }

            $linies[] = '';
            if ($seccioAfegida) {
                $linies[] = '';
            }
            $linies[] = $titol;
            $this->afegirDies($linies, $grups[$tipus], $aCasa, $delegades);
            $seccioAfegida = true;
        }

        return rtrim(implode("\n", $linies));
    }

    private function afegirDies(array &$linies, array $partitsPerData, bool $aCasa, array $delegades): void
    {
        ksort($partitsPerData);
        $primerDia = true;
        foreach ($partitsPerData as $data => $partits) {
            $dia = DateTimeImmutable::createFromFormat('!Y-m-d', $data, new DateTimeZone('Europe/Madrid'));
            if ($dia === false) {
                continue;
            }
            usort($partits, static fn (array $a, array $b): int => strcmp($a['hora'], $b['hora']));
            if (!$primerDia) {
                $linies[] = '';
            }
            $linies[] = '_' . self::DIES[(int) $dia->format('N')] . ' ' . $dia->format('d/m') . '_';
            $primerDia = false;

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
        return (string) ($partit['nom_equip_club'] ?? 'Equip');
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
