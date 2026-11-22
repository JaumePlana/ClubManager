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
    private const CLAU_MEMORIA_TOKEN = 'clubmanager_sidgad_token_v1';
    private const DURADA_LOCAL_TOKEN_SEGONS = 1700;
    private const MARGE_RENOVACIO_TOKEN_SEGONS = 300;

    private ?array $tokenEnMemoria = null;
    private int $renovarTokenAbansDe = 0;

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
        try {
            $html = $this->demanarAgenda($token);
        } catch (RuntimeException $error) {
            if (!in_array($error->getCode(), [401, 403], true)) {
                throw $error;
            }

            // Si la sessió ha caducat entre obtenir el token i llegir l'agenda,
            // demana'n un de nou i repeteix la consulta una sola vegada.
            $this->invalidarToken();
            $html = $this->demanarAgenda($this->obtenirToken());
        }

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

    /** Troba l'ID FECAPA del club cercant el seu nom als equips de l'agenda. */
    public function identificarIdClub(array $partits, string $nomClub): int
    {
        $nomCercat = self::normalitzarNomClub($nomClub);
        if ($nomCercat === '') {
            throw new InvalidArgumentException('Cal indicar el nom del club.');
        }

        $ids = [];
        foreach ($partits as $partit) {
            foreach ([['id_club_local', 'equip_local'], ['id_club_visitant', 'equip_visitant']] as [$campId, $campEquip]) {
                if (isset($partit[$campId], $partit[$campEquip])
                    && str_starts_with(self::normalitzarNomClub((string) $partit[$campEquip]), $nomCercat)) {
                    $ids[(int) $partit[$campId]] = true;
                }
            }
        }

        if (count($ids) !== 1) {
            throw new RuntimeException(count($ids) === 0
                ? 'No s’ha trobat cap club amb el nom configurat a l’agenda.'
                : 'El nom configurat coincideix amb més d’un club; cal concretar-lo.');
        }

        return (int) array_key_first($ids);
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
        $ara = time();
        if ($this->tokenEnMemoria !== null && $this->renovarTokenAbansDe > $ara) {
            return $this->tokenEnMemoria;
        }

        $tokenEnCache = $this->llegirTokenCompartit();
        if ($tokenEnCache !== null) {
            $this->tokenEnMemoria = $tokenEnCache['token'];
            $this->renovarTokenAbansDe = $tokenEnCache['renovar_abans_de'];
            return $this->tokenEnMemoria;
        }

        $json = $this->ferPeticio(self::URL_TOKEN, null, self::ORIGEN . '/');
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (!isset($data['t'], $data['e'])) {
            throw new RuntimeException('La resposta del token de lagenda FECAPA es incompleta.');
        }

        $token = ['token' => (string) $data['t'], 'caducitat' => (string) $data['e']];
        $renovarAbansDe = $ara
            + self::DURADA_LOCAL_TOKEN_SEGONS
            - self::MARGE_RENOVACIO_TOKEN_SEGONS;
        if (ctype_digit($token['caducitat'])) {
            $caducitat = (int) $token['caducitat'];
            if ($caducitat > $ara) {
                $renovarAbansDe = min(
                    $renovarAbansDe,
                    $caducitat - self::MARGE_RENOVACIO_TOKEN_SEGONS,
                );
            }
        }

        $this->tokenEnMemoria = $token;
        $this->renovarTokenAbansDe = $renovarAbansDe;
        $this->desarTokenCompartit($token, $renovarAbansDe);

        return $token;
    }

    /** Recupera un token de la memòria cau compartida de WordPress, si n'hi ha. */
    private function llegirTokenCompartit(): ?array
    {
        if (!function_exists('get_transient')) {
            return null;
        }

        $cache = get_transient(self::CLAU_MEMORIA_TOKEN);
        if (!is_array($cache)
            || !isset($cache['token'], $cache['renovar_abans_de'])
            || !is_array($cache['token'])
            || !isset($cache['token']['token'], $cache['token']['caducitat'])
            || (int) $cache['renovar_abans_de'] <= time()) {
            return null;
        }

        return [
            'token' => [
                'token' => (string) $cache['token']['token'],
                'caducitat' => (string) $cache['token']['caducitat'],
            ],
            'renovar_abans_de' => (int) $cache['renovar_abans_de'],
        ];
    }

    /** Desa el token fins al moment en què caldrà obtenir-ne un de nou. */
    private function desarTokenCompartit(array $token, int $renovarAbansDe): void
    {
        $tempsRestant = $renovarAbansDe - time();
        if ($tempsRestant <= 0 || !function_exists('set_transient')) {
            return;
        }

        set_transient(self::CLAU_MEMORIA_TOKEN, [
            'token' => $token,
            'renovar_abans_de' => $renovarAbansDe,
        ], $tempsRestant);
    }

    /** Elimina el token desat abans de forçar-ne la renovació. */
    private function invalidarToken(): void
    {
        $this->tokenEnMemoria = null;
        $this->renovarTokenAbansDe = 0;
        if (function_exists('delete_transient')) {
            delete_transient(self::CLAU_MEMORIA_TOKEN);
        }
    }

    /** Descarrega l'agenda amb el token indicat. */
    private function demanarAgenda(array $token): string
    {
        return $this->ferPeticio(self::URL_AGENDA . '?' . http_build_query([
            'st' => $token['token'],
            'se' => $token['caducitat'],
        ]), http_build_query([
            'cliente' => 'fecapa',
            'idm' => 1,
            'id_temp' => $this->seasonId,
        ]), self::ORIGEN . '/');
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
            CURLOPT_HTTPHEADER => [
                'Accept: */*',
                'Origin: ' . self::ORIGEN,
                'Sec-Fetch-Dest: empty',
                'Sec-Fetch-Mode: cors',
                'Sec-Fetch-Site: cross-site',
            ],
            CURLOPT_REFERER => $referer,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/157.0.0.0 Safari/537.36',
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
                CURLOPT_HTTPHEADER => array_merge($options[CURLOPT_HTTPHEADER], [
                    'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
                ]),
            ]);
        }
        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        curl_close($curl);
        if ($body === false || $status < 200 || $status >= 300) {
            throw new RuntimeException(
                sprintf('La peticio a FECAPA ha fallat (HTTP %d): %s', $status, $error),
                $status,
            );
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
                'id_competicio' => self::extreureIdCompeticio($row->getAttribute('param_game')),
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

    /** Extreu l'ID de competició, que és l'últim component numèric de param_game. */
    private static function extreureIdCompeticio(string $parametresPartit): ?int
    {
        if (preg_match('/(?:^|_)(\d+)$/', trim($parametresPartit), $coincidencia) !== 1) {
            return null;
        }

        return (int) $coincidencia[1];
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

    private static function normalitzarNomClub(string $nom): string
    {
        return mb_strtoupper(trim(preg_replace('/\s+/u', ' ', $nom) ?? ''), 'UTF-8');
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
