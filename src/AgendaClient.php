<?php

declare(strict_types=1);

/**
 * Consulta i filtra l'agenda d'hoquei de la FECAPA.
 *
 * Requereix les extensions PHP cURL i DOM. L'ID de temporada es pot indicar
 * al constructor; el valor 41 s'ha observat al fitxer HAR adjunt. Si cal,
 * indiqueu la ruta dels certificats CA com a segon argument.
 */
final class ClientAgenda
{
    private const URL_TOKEN = 'https://www.server2.sidgad.es/sg_token.php';
    private const URL_AGENDA = 'https://server2.sidgad.es/fecapa/00_fecapa_agenda_1.php';
    private const ORIGEN = 'https://www.hoqueipatins.fecapa.cat';

    public function __construct(
        private readonly int $seasonId = 41,
        private readonly ?string $caBundlePath = null,
    )
    {
    }

    /** Descarrega tots els partits i els retorna en matrius associatives. */
    public function obtenirPartits(): array
    {
        $token = $this->obtenirToken();
        $html = $this->ferPeticio(self::URL_AGENDA . '?' . http_build_query([
            'st' => $token['token'],
            'se' => $token['caducitat'],
        ]), http_build_query([
            'cliente' => 'fecapa',
            'idm' => 1,
            'id_temp' => $this->seasonId,
        ]), self::ORIGEN . '/');

        return $this->analitzarPartits($html);
    }

    /** Retorna l agenda en JSON per a WordPress o un client. */
    public function obtenirPartitsJson(): string
    {
        return json_encode($this->obtenirPartits(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /** Filtra els partits pel codi FECAPA del club indicat. */
    public function filtrarPerClub(array $partits, int $idClub): array
    {
        return array_values(array_filter($partits, static fn (array $partit): bool =>
            $partit['id_club_local'] === $idClub || $partit['id_club_visitant'] === $idClub
        ));
    }

    /**
     * Filtra els partits del club a la setmana de dilluns a diumenge.
     * Accepta DateTimeInterface o una data ISO com ara 2026-11-18.
     */
    public function partitsClubPerSetmana(array $partits, int $idClub, string|DateTimeInterface $dia): array
    {
        $zona = new DateTimeZone('Europe/Madrid');
        $data = $dia instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($dia)->setTimezone($zona)
            : new DateTimeImmutable($dia, $zona);
        $dilluns = $data->modify('monday this week')->setTime(0, 0);
        $diumenge = $dilluns->modify('+6 days')->setTime(23, 59, 59);

        return array_values(array_filter($this->filtrarPerClub($partits, $idClub), static function (array $partit) use ($dilluns, $diumenge, $zona): bool {
            if ($partit['data'] === null || $partit['hora'] === null) {
                return false;
            }
            $inici = new DateTimeImmutable($partit['data'] . ' ' . $partit['hora'], $zona);
            return $inici >= $dilluns && $inici <= $diumenge;
        }));
    }

    /** Afegeix al partit la categoria i la lletra de lequip del club. */
    public function identificarEquipClub(array $partit, int $idClub): array
    {
        $esLocal = $partit['id_club_local'] === $idClub;
        $nomEquip = $esLocal ? $partit['equip_local'] : $partit['equip_visitant'];
        $partit += self::desglossarCompeticio($partit['competicio']);
        $partit['equip_club'] = self::lletraEquip($nomEquip);
        $partit['nom_equip_club'] = trim($partit['categoria_edat'] . ' ' . ($partit['nivell'] ?? '') . ' ' . ($partit['equip_club'] ?? ''));
        return $partit;
    }

    /** Separa la competicio en categoria, nivell i grup opcional. */
    public static function desglossarCompeticio(string $competicio): array
    {
        $label = self::netejar($competicio);
        $age = null;
        foreach ([
            'PREBENJAM[IÍ]', 'BENJAM[IÍ]', 'ALEV[IÍ]', 'INFANTIL', 'JUVENIL', 'J[ÚU]NIOR',
            'FEM(?:EN[IÍ])?\s*\d+', 'VETERANS?(?:\s+M[AÀ]STER\s*\d+)?', 'M[AÀ]STER\s*\d+',
        ] as $pattern) {
            if (preg_match('/(' . $pattern . ')/iu', $label, $match) === 1) {
                $edat = mb_convert_case(mb_strtolower($match[1], 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
                $age = str_starts_with(mb_strtoupper($edat, 'UTF-8'), 'FEM') ? mb_strtoupper($edat, 'UTF-8') : $edat;
                break;
            }
        }
        $level = null;
        if (preg_match('/\b(BRONZE|OR|PLATA|OR\s*\d+|PLATA\s*[-–]\s*BRONZE)\b/iu', $label, $match) === 1) {
            $level = mb_convert_case(mb_strtolower(self::netejar($match[1]), 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
        }
        $group = null;
        if (preg_match('/\b(?:GRUP|FASE)\s*([A-Z0-9]+)\b/iu', $label, $match) === 1) {
            $group = strtoupper($match[1]);
        } elseif (preg_match('/\b(?:OR|PLATA|BRONZE)\s+(\d+)\b/iu', $label, $match) === 1) {
            $group = $match[1];
        }
        return ['categoria' => $label, 'categoria_edat' => $age ?? $label, 'nivell' => $level, 'grup' => $group];
    }

    /** Extreu la lletra final de lequip A/B/C… team marker; Veterans and Màster 50 remain unmarked. */
    public static function lletraEquip(string $nomEquip): ?string
    {
        $name = trim(html_entity_decode($nomEquip, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('/(?:\b([A-Z])(?:\s+\1)?)$/u', $name, $match) === 1) {
            return $match[1];
        }
        return null;
    }

    private function obtenirToken(): array
    {
        $json = $this->ferPeticio(self::URL_TOKEN, null, self::ORIGEN . '/');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['t'], $data['e'])) {
            throw new RuntimeException('La resposta del token de lagenda FECAPA es incompleta.');
        }
        return ['token' => (string) $data['t'], 'caducitat' => (string) $data['e']];
    }

    private function ferPeticio(string $url, ?string $postBody, string $referer): string
    {
        $curl = curl_init($url);
        $caBundle = $this->caBundlePath
            ?? (getenv('CURL_CA_BUNDLE') ?: null)
            ?? (ini_get('curl.cainfo') ?: null);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Origin: ' . self::ORIGEN],
            CURLOPT_REFERER => $referer,
            CURLOPT_USERAGENT => 'ClubManager agenda reader/1.0',
        ];
        if ($caBundle !== null) {
            if (!is_file($caBundle) || !is_readable($caBundle)) {
                curl_close($curl);
                throw new RuntimeException('The configured CA bundle is missing or unreadable: ' . $caBundle);
            }
            $options[CURLOPT_CAINFO] = $caBundle;
        }
        curl_setopt_array($curl, $options);
        if ($postBody !== null) {
            curl_setopt_array($curl, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $postBody,
                CURLOPT_HTTPHEADER => [
                    'Origin: ' . self::ORIGEN,
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
            ],
            ]);
        }
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException(sprintf('La peticio a FECAPA ha fallat (HTTP %d): %s', $status, $error));
        }
        return $body;
    }

    private function analitzarPartits(string $html): array
    {
        if (!class_exists(DOMDocument::class)) {
            throw new RuntimeException('Cal lextensio PHP DOM per analitzar lagenda.');
        }
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new DOMXPath($document);
        $matches = [];
        foreach ($xpath->query('//tr[contains(concat(" ", normalize-space(@class), " "), " fila_agenda ")]') as $row) {
            if (!$row instanceof DOMElement) {
                continue;
            }
            $cells = $xpath->query('./td', $row);
            if ($cells->length < 9) {
                continue;
            }
            $date = self::netejar($cells->item(1)->textContent);
            $time = self::netejar($cells->item(2)->textContent);
            $kickoff = self::interpretarInici($date, $time);
            $resultat = self::netejar($cells->item(7)->textContent) ?: null;
            [$golsLocal, $golsVisitant] = self::desglossarResultat($resultat);
            $matches[] = [
                'competicio' => self::netejar($cells->item(0)->textContent),
                'data' => $kickoff?->format('Y-m-d'),
                'hora' => preg_match('/^\d{2}:\d{2}$/', $time) ? $time : null,
                'id_club_local' => (int) $row->getAttribute('club1'),
                'id_club_visitant' => (int) $row->getAttribute('club2'),
                'equip_local' => self::netejar($cells->item(4)->textContent),
                'equip_visitant' => self::netejar($cells->item(6)->textContent),
                'resultat' => $resultat,
                'gols_local' => $golsLocal,
                'gols_visitant' => $golsVisitant,
                'id_pista' => (int) $row->getAttribute('pista'),
                'pista' => self::netejar($cells->item(8)->textContent),
            ];
        }
        return $matches;
    }

    /** Separa els gols locals i visitants del resultat, o retorna nulls si no és vàlid. */
    private static function desglossarResultat(?string $resultat): array
    {
        if ($resultat !== null && preg_match('/^(\d+)\s*[-–]\s*(\d+)$/u', $resultat, $gols) === 1) {
            return [(int) $gols[1], (int) $gols[2]];
        }

        return [null, null];
    }

    private static function netejar(string $valor): string
    {
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode($valor, ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    private static function interpretarInici(string $date, string $time): ?DateTimeImmutable
    {
        $date = trim($date);
        if (!preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date) || $date === '00/00/0000') {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat('!d/m/Y H:i', $date . ' ' . $time, new DateTimeZone('Europe/Madrid'));
        return $parsed === false ? null : $parsed;
    }
}
