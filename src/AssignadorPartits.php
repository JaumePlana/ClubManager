<?php

declare(strict_types=1);

/** Afegeix les assignacions de Delegat i Taula als partits d'una setmana. */
final class AssignadorPartits
{
    private const SEPARACIO_DELEGAT_SEGONS = 7200;
    private const SEPARACIO_PISTES_DIFERENTS_SEGONS = 5400;

    public function __construct(private readonly ClientAgenda $agenda)
    {
    }

    /**
     * Identifica els equips del club i afegeix els camps "delegat" i "taula".
     * Els delegats es proven per ordre de preferència. Els partits de casa
     * són els que tenen el club configurat com a equip local.
     */
    public function afegirDelegatITaula(array $partits, array $config): array
    {
        if ($partits === []) {
            return [];
        }

        $nomClub = (string) ($config['club'] ?? '');
        $idClub = $this->agenda->identificarIdClub($partits, $nomClub);
        $configDelegats = $config['delegats'] ?? [];
        if (!is_array($configDelegats)) {
            throw new InvalidArgumentException('La configuració delegats ha de ser una matriu.');
        }
        $delegatsPerEquip = $configDelegats['equips'] ?? [];
        if (!is_array($delegatsPerEquip)) {
            throw new InvalidArgumentException('La configuració delegats.equips ha de ser una matriu.');
        }
        $absencies = $configDelegats['absencies'] ?? [];
        if (!is_array($absencies)) {
            throw new InvalidArgumentException('La configuració delegats.absencies ha de ser una matriu indexada per data.');
        }
        foreach ($absencies as $dataAbsencia => $nomsAbsents) {
            $dataValida = is_string($dataAbsencia)
                ? DateTimeImmutable::createFromFormat('!Y-m-d', $dataAbsencia)
                : false;
            $errorsData = DateTimeImmutable::getLastErrors();
            if ($dataValida === false
                || $dataValida->format('Y-m-d') !== $dataAbsencia
                || ($errorsData !== false && ($errorsData['warning_count'] > 0 || $errorsData['error_count'] > 0))) {
                throw new InvalidArgumentException('Cada absència ha de tenir una data vàlida en format yyyy-mm-dd.');
            }
            if (!is_array($nomsAbsents)) {
                throw new InvalidArgumentException('Cada data d’absència ha de contenir una llista de noms.');
            }
            foreach ($nomsAbsents as $nomAbsent) {
                if (!is_string($nomAbsent) || trim($nomAbsent) === '') {
                    throw new InvalidArgumentException('Els noms de la configuració delegats.absencies han de ser cadenes no buides.');
                }
            }
            $absencies[$dataAbsencia] = array_values(array_unique(array_map('trim', $nomsAbsents)));
        }
        $pistesConfigurades = $config['pistes_casa'] ?? [];
        $pistesCasa = is_array($pistesConfigurades)
            ? array_map([$this, 'normalitzarPista'], $pistesConfigurades)
            : [];

        $resultats = [];
        $inicis = [];
        $partitsACasa = [];
        foreach ($partits as $index => $partit) {
            $partit = $this->agenda->identificarEquipClub($partit, $idClub);
            $partit['delegat'] = null;
            $partit['taula'] = null;
            $resultats[$index] = $partit;
            $inicis[$index] = $this->interpretarInici($partit);
            $partitsACasa[$index] = $partit['id_club_local'] === $idClub
                && ($pistesCasa === [] || in_array($this->normalitzarPista((string) ($partit['pista'] ?? '')), $pistesCasa, true));
        }

        $ordreDelegats = array_keys($resultats);
        usort($ordreDelegats, function (int $a, int $b) use ($resultats, $inicis, $delegatsPerEquip, $absencies): int {
            $absentsA = $absencies[$resultats[$a]['data']] ?? [];
            $absentsB = $absencies[$resultats[$b]['data']] ?? [];
            $delegatsA = $this->delegatsDe($resultats[$a]['nom_equip_club'], $delegatsPerEquip, $absentsA);
            $delegatsB = $this->delegatsDe($resultats[$b]['nom_equip_club'], $delegatsPerEquip, $absentsB);
            $perDisponibilitat = count($delegatsA) <=> count($delegatsB);
            if ($perDisponibilitat !== 0) {
                return $perDisponibilitat;
            }

            return ($inicis[$a]?->getTimestamp() ?? PHP_INT_MAX)
                <=> ($inicis[$b]?->getTimestamp() ?? PHP_INT_MAX);
        });

        $ocupacions = [];
        foreach ($ordreDelegats as $index) {
            $inici = $inicis[$index];
            if ($inici === null) {
                continue;
            }
            $absents = $absencies[$resultats[$index]['data']] ?? [];
            foreach ($this->delegatsDe($resultats[$index]['nom_equip_club'], $delegatsPerEquip, $absents) as $delegat) {
                if ($this->potFerDeDelegat($delegat, $inici, $ocupacions)) {
                    $resultats[$index]['delegat'] = $delegat;
                    $ocupacions[$delegat][] = [
                        'inici' => $inici,
                        'tipus' => 'delegat',
                        'partit' => $index,
                        'es_casa' => $resultats[$index]['id_club_local'] === $idClub,
                        'pista' => $resultats[$index]['id_club_local'] === $idClub
                            ? $this->normalitzarPista((string) ($resultats[$index]['pista'] ?? ''))
                            : null,
                    ];
                    break;
                }
            }
        }

        $ordreTaules = array_keys($resultats);
        usort($ordreTaules, fn (int $a, int $b): int =>
            ($inicis[$a]?->getTimestamp() ?? PHP_INT_MAX)
            <=> ($inicis[$b]?->getTimestamp() ?? PHP_INT_MAX)
        );

        foreach ($ordreTaules as $index) {
            if (!$partitsACasa[$index] || $inicis[$index] === null) {
                continue;
            }

            $absents = $absencies[$resultats[$index]['data']] ?? [];
            $candidats = $this->candidatsTaula($index, $resultats, $inicis, $partitsACasa, $delegatsPerEquip, $absents);
            foreach ($candidats as $candidat) {
                if ($resultats[$index]['delegat'] === $candidat
                    || !$this->potFerDeTaula(
                        $candidat,
                        $inicis[$index],
                        $index,
                        $partits[$index],
                        $ocupacions,
                    )) {
                    continue;
                }

                $resultats[$index]['taula'] = $candidat;
                $ocupacions[$candidat][] = [
                    'inici' => $inicis[$index],
                    'tipus' => 'taula',
                    'partit' => $index,
                    'es_casa' => true,
                    'pista' => $this->normalitzarPista((string) ($resultats[$index]['pista'] ?? '')),
                ];
                break;
            }
        }

        return array_values($resultats);
    }

    private function delegatsDe(string $equip, array $delegatsPerEquip, array $absents = []): array
    {
        $delegats = $delegatsPerEquip[$equip] ?? [];
        if (!is_array($delegats)) {
            return [];
        }

        return array_values(array_unique(array_filter($delegats, static fn ($nom): bool =>
            is_string($nom) && trim($nom) !== '' && !in_array(trim($nom), $absents, true)
        )));
    }

    private function candidatsTaula(
        int $index,
        array $partits,
        array $inicis,
        array $partitsACasa,
        array $delegatsPerEquip,
        array $absents,
    ): array {
        $candidats = $this->delegatsDe($partits[$index]['nom_equip_club'], $delegatsPerEquip, $absents);
        $altresPartits = [];
        foreach ($partits as $altreIndex => $partit) {
            if ($altreIndex === $index || !$partitsACasa[$altreIndex]
                || $inicis[$altreIndex] === null || $inicis[$altreIndex]->format('Y-m-d') !== $inicis[$index]->format('Y-m-d')) {
                continue;
            }
            if ($inicis[$altreIndex] < $inicis[$index] || $inicis[$altreIndex] > $inicis[$index]) {
                $altresPartits[] = $altreIndex;
            }
        }

        usort($altresPartits, fn (int $a, int $b): int =>
            abs($inicis[$a]->getTimestamp() - $inicis[$index]->getTimestamp())
            <=> abs($inicis[$b]->getTimestamp() - $inicis[$index]->getTimestamp())
        );
        foreach ($altresPartits as $altreIndex) {
            $candidats = array_merge(
                $candidats,
                $this->delegatsDe($partits[$altreIndex]['nom_equip_club'], $delegatsPerEquip, $absents),
            );
        }

        return array_values(array_unique($candidats));
    }

    private function potFerDeDelegat(string $nom, DateTimeImmutable $inici, array $ocupacions): bool
    {
        foreach ($ocupacions[$nom] ?? [] as $ocupacio) {
            if ($ocupacio['tipus'] === 'delegat'
                && abs($ocupacio['inici']->getTimestamp() - $inici->getTimestamp()) < self::SEPARACIO_DELEGAT_SEGONS) {
                return false;
            }
            if ($ocupacio['inici']->getTimestamp() === $inici->getTimestamp()) {
                return false;
            }
        }

        return true;
    }

    private function potFerDeTaula(
        string $nom,
        DateTimeImmutable $inici,
        int $index,
        array $partit,
        array $ocupacions,
    ): bool
    {
        $pista = $this->normalitzarPista((string) ($partit['pista'] ?? ''));
        foreach ($ocupacions[$nom] ?? [] as $ocupacio) {
            if ($ocupacio['partit'] === $index) {
                return false;
            }

            $separacio = abs($ocupacio['inici']->getTimestamp() - $inici->getTimestamp());
            if ($separacio === 0) {
                return false;
            }

            $pistesDiferents = ($ocupacio['es_casa'] ?? false)
                && $pista !== ''
                && ($ocupacio['pista'] ?? '') !== ''
                && $ocupacio['pista'] !== $pista;
            if ($pistesDiferents && $separacio < self::SEPARACIO_PISTES_DIFERENTS_SEGONS) {
                return false;
            }

            $properPartitFora = ($ocupacio['tipus'] ?? null) === 'delegat'
                && !($ocupacio['es_casa'] ?? false)
                && $ocupacio['inici']->getTimestamp() > $inici->getTimestamp();
            if ($properPartitFora && $separacio < self::SEPARACIO_DELEGAT_SEGONS) {
                return false;
            }
        }

        return true;
    }

    private function interpretarInici(array $partit): ?DateTimeImmutable
    {
        if (empty($partit['data']) || empty($partit['hora'])) {
            return null;
        }

        $inici = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i',
            $partit['data'] . ' ' . $partit['hora'],
            new DateTimeZone('Europe/Madrid'),
        );

        return $inici === false ? null : $inici;
    }

    private function normalitzarPista(string $pista): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $pista) ?? ''), 'UTF-8');
    }
}
