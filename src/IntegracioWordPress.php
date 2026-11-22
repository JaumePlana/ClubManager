<?php

declare(strict_types=1);

require_once __DIR__ . '/AgendaClient.php';
require_once __DIR__ . '/AssignadorPartits.php';
require_once __DIR__ . '/FormatadorMissatge.php';

/**
 * Obté els partits assignats del club per a la setmana que conté la data.
 * Si no s'indica cap data, fa servir el dia d'avui.
 */
function clubmanager_obtenir_partits_setmanals(?string $data = null): array
{
    static $configuracio;

    if ($configuracio === null) {
        $rutaConfiguracio = dirname(__DIR__) . '/config.php';
        if (!is_file($rutaConfiguracio)) {
            throw new RuntimeException('No s’ha trobat config.php a l’arrel de ClubManager.');
        }

        $configuracio = require $rutaConfiguracio;
    }

    $data ??= (new DateTimeImmutable('now', new DateTimeZone('Europe/Madrid')))->format('Y-m-d');
    $agenda = new ClientAgenda((int) $configuracio['temporada_id']);
    $partits = $agenda->obtenirPartits();
    $idClub = $agenda->identificarIdClub($partits, (string) $configuracio['club']);
    $partitsSetmana = $agenda->partitsClubPerSetmana($partits, $idClub, $data);

    return (new AssignadorPartits($agenda))->afegirDelegatITaula($partitsSetmana, $configuracio);
}

/** Retorna el JSON dels partits assignats de la setmana indicada. */
function clubmanager_obtenir_partits_setmanals_json(?string $data = null): string
{
    return json_encode(
        clubmanager_obtenir_partits_setmanals($data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
}

/** Retorna el missatge setmanal preparat per compartir. */
function clubmanager_obtenir_missatge_setmanal(?string $data = null): string
{
    static $configuracio;
    if ($configuracio === null) {
        $configuracio = require dirname(__DIR__) . '/config.php';
    }

    return FormatadorMissatge::generarMissatge(
        clubmanager_obtenir_partits_setmanals($data),
        $configuracio
    );
}

/** Registra [assignacio_delegats] per mostrar el missatge setmanal en una pàgina. */
function clubmanager_registrar_shortcode(): void
{
    if (!function_exists('add_shortcode')) {
        return;
    }

    add_shortcode('assignacio_delegats', static function (array $atributs = []): string {
        $atributs = shortcode_atts(['data' => ''], $atributs, 'assignacio_delegats');
        $data = $atributs['data'] !== '' ? (string) $atributs['data'] : null;

        try {
            $missatge = clubmanager_obtenir_missatge_setmanal($data);
        } catch (Throwable $error) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                return '<p class="clubmanager-error">' . esc_html($error->getMessage()) . '</p>';
            }
            return '<p class="clubmanager-error">No s’ha pogut carregar l’agenda aquesta setmana.</p>';
        }

        return '<pre class="clubmanager-agenda">' . esc_html($missatge) . '</pre>';
    });
}

/** Registra l'API REST /wp-json/clubmanager/v1/assignacio-delegats. */
function clubmanager_registrar_api(): void
{
    if (!function_exists('register_rest_route')) {
        return;
    }

    register_rest_route('clubmanager/v1', '/assignacio-delegats', [
        'methods' => 'GET',
        'permission_callback' => '__return_true',
        'args' => [
            'data' => ['required' => false, 'type' => 'string'],
            'content-type' => ['required' => false, 'type' => 'string', 'enum' => ['text', 'json']],
        ],
        'callback' => static function ($request) {
            $data = $request->get_param('data');
            if ($data !== null) {
                $data = (string) $data;
                $dataValidada = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
                $errors = DateTimeImmutable::getLastErrors();
                if ($dataValidada === false
                    || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                    || $dataValidada->format('Y-m-d') !== $data) {
                    return new WP_Error(
                        'clubmanager_data_invalida',
                        'El paràmetre data ha de tenir el format yyyy-mm-dd.',
                        ['status' => 400]
                    );
                }
            }

            try {
                if ($request->get_param('content-type') === 'json') {
                    return rest_ensure_response(clubmanager_obtenir_partits_setmanals($data));
                }

                $response = new WP_REST_Response(clubmanager_obtenir_missatge_setmanal($data));
                $response->header('Content-Type', 'text/plain; charset=utf-8');
                return $response;
            } catch (Throwable $error) {
                return new WP_Error(
                    'clubmanager_assignacio_delegats_error',
                    defined('WP_DEBUG') && WP_DEBUG
                        ? $error->getMessage()
                        : 'No s’ha pogut carregar l’agenda.',
                    ['status' => 500]
                );
            }
        },
    ]);
}

if (function_exists('add_action')) {
    add_action('init', 'clubmanager_registrar_shortcode');
    add_action('rest_api_init', 'clubmanager_registrar_api');
}
