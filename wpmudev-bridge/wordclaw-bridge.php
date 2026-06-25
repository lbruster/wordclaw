<?php
/**
 * Plugin Name: WordClaw Bridge
 * Description: Connects WordPress/WPMU DEV plugins to OpenClaw via secure REST endpoints.
 *              Required for all Phase 2 WordClaw skills.
 * Version: 1.0.0
 * Author: Your Agency
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// ─── CONFIG ──────────────────────────────────────────────────────────────────
// Set your secret key here AND in OpenClaw workspace memory as:
// wordclaw:bridge_secret = YOUR_SECRET_KEY
define( 'WORDCLAW_SECRET', getenv('WORDCLAW_SECRET') ?: 'CHANGE_THIS_TO_A_LONG_RANDOM_STRING' );

// ─── AUTH HELPER ─────────────────────────────────────────────────────────────
function wordclaw_auth( WP_REST_Request $request ): bool {
    $key = $request->get_header('X-WordClaw-Key');
    return hash_equals( WORDCLAW_SECRET, (string) $key );
}

function wordclaw_deny(): WP_REST_Response {
    return new WP_REST_Response( ['error' => 'Unauthorized'], 403 );
}

// ─── REGISTER ROUTES ─────────────────────────────────────────────────────────
add_action( 'rest_api_init', function() {

    $ns = 'wordclaw/v1';

    // ── DEFENDER ──────────────────────────────────────────────────────────────

    // GET  /wordclaw/v1/defender/scan-status
    register_rest_route( $ns, '/defender/scan-status', [
        'methods'             => 'GET',
        'callback'            => 'wordclaw_defender_scan_status',
        'permission_callback' => '__return_true',
    ]);

    // POST /wordclaw/v1/defender/scan-run
    register_rest_route( $ns, '/defender/scan-run', [
        'methods'             => 'POST',
        'callback'            => 'wordclaw_defender_scan_run',
        'permission_callback' => '__return_true',
    ]);

    // GET  /wordclaw/v1/defender/issues
    register_rest_route( $ns, '/defender/issues', [
        'methods'             => 'GET',
        'callback'            => 'wordclaw_defender_issues',
        'permission_callback' => '__return_true',
    ]);

    // ── SNAPSHOT ──────────────────────────────────────────────────────────────

    // POST /wordclaw/v1/snapshot/run
    register_rest_route( $ns, '/snapshot/run', [
        'methods'             => 'POST',
        'callback'            => 'wordclaw_snapshot_run',
        'permission_callback' => '__return_true',
    ]);

    // GET  /wordclaw/v1/snapshot/list
    register_rest_route( $ns, '/snapshot/list', [
        'methods'             => 'GET',
        'callback'            => 'wordclaw_snapshot_list',
        'permission_callback' => '__return_true',
    ]);

    // GET  /wordclaw/v1/snapshot/status
    register_rest_route( $ns, '/snapshot/status', [
        'methods'             => 'GET',
        'callback'            => 'wordclaw_snapshot_status',
        'permission_callback' => '__return_true',
    ]);

    // ── HUMMINGBIRD ───────────────────────────────────────────────────────────

    // GET  /wordclaw/v1/hummingbird/performance
    register_rest_route( $ns, '/hummingbird/performance', [
        'methods'             => 'GET',
        'callback'            => 'wordclaw_hummingbird_performance',
        'permission_callback' => '__return_true',
    ]);

    // POST /wordclaw/v1/hummingbird/clear-cache
    register_rest_route( $ns, '/hummingbird/clear-cache', [
        'methods'             => 'POST',
        'callback'            => 'wordclaw_hummingbird_clear_cache',
        'permission_callback' => '__return_true',
    ]);

    // ── FORMINATOR ────────────────────────────────────────────────────────────

    // GET  /wordclaw/v1/forminator/entries?form_id=X&since=Y
    register_rest_route( $ns, '/forminator/entries', [
        'methods'             => 'GET',
        'callback'            => 'wordclaw_forminator_entries',
        'permission_callback' => '__return_true',
    ]);

    // GET  /wordclaw/v1/forminator/forms
    register_rest_route( $ns, '/forminator/forms', [
        'methods'             => 'GET',
        'callback'            => 'wordclaw_forminator_forms',
        'permission_callback' => '__return_true',
    ]);

    // ── WP-CLI RUNNER ─────────────────────────────────────────────────────────

    // POST /wordclaw/v1/cli/run
    register_rest_route( $ns, '/cli/run', [
        'methods'             => 'POST',
        'callback'            => 'wordclaw_cli_run',
        'permission_callback' => '__return_true',
    ]);

    // GET  /wordclaw/v1/cli/plugins  (quick plugin list shortcut)
    register_rest_route( $ns, '/cli/plugins', [
        'methods'             => 'GET',
        'callback'            => 'wordclaw_cli_plugins',
        'permission_callback' => '__return_true',
    ]);

    // GET  /wordclaw/v1/cli/themes  (quick theme list shortcut)
    register_rest_route( $ns, '/cli/themes', [
        'methods'             => 'GET',
        'callback'            => 'wordclaw_cli_themes',
        'permission_callback' => '__return_true',
    ]);

    // GET  /wordclaw/v1/cli/updates  (combined update check)
    register_rest_route( $ns, '/cli/updates', [
        'methods'             => 'GET',
        'callback'            => 'wordclaw_cli_updates',
        'permission_callback' => '__return_true',
    ]);

});

// ─── DEFENDER CALLBACKS ──────────────────────────────────────────────────────

function wordclaw_defender_scan_status( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth($req) ) return wordclaw_deny();

    // Use WP-CLI result cached in a transient (set by the scan-run endpoint)
    $last = get_transient('wordclaw_defender_last_scan');
    if ( ! $last ) {
        return new WP_REST_Response([
            'status'    => 'no_scan',
            'message'   => 'No scan has been run yet via WordClaw. POST /defender/scan-run to start one.',
        ]);
    }
    return new WP_REST_Response( $last );
}

function wordclaw_defender_scan_run( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth($req) ) return wordclaw_deny();

    if ( ! class_exists('WP_Defender\Component\Scan') ) {
        // Fallback: trigger via WP-CLI shell if available
        $output = shell_exec('wp defender scan start --allow-root 2>&1');
        return new WP_REST_Response([
            'triggered' => true,
            'method'    => 'wp-cli',
            'output'    => $output,
            'note'      => 'Scan started. Poll /defender/scan-status in ~60s for results.',
        ]);
    }

    // If Defender is loaded as a class (Pro, same process)
    do_action('wp_defender_start_scan');
    return new WP_REST_Response([
        'triggered' => true,
        'method'    => 'action-hook',
        'note'      => 'Scan started via action hook. Poll /defender/scan-status in ~60s.',
    ]);
}

function wordclaw_defender_issues( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth($req) ) return wordclaw_deny();

    // Pull from Defender's own options/transients
    $issues = get_option('wp_defender_scan_result', []);

    // Normalise to a simple array
    $out = [];
    if ( is_array($issues) ) {
        foreach ( $issues as $issue ) {
            $out[] = [
                'type'     => $issue['type']    ?? 'unknown',
                'file'     => $issue['file']    ?? '',
                'severity' => $issue['severity'] ?? 'medium',
                'detail'   => $issue['detail']  ?? '',
            ];
        }
    }

    return new WP_REST_Response([
        'count'  => count($out),
        'issues' => $out,
    ]);
}

// ─── SNAPSHOT CALLBACKS ──────────────────────────────────────────────────────

function wordclaw_snapshot_run( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth($req) ) return wordclaw_deny();

    $body = $req->get_json_params();
    $name = sanitize_text_field( $body['name'] ?? 'WordClaw on-demand backup' );

    // Snapshot 4 exposes WP-CLI: wp snapshot backup run --name="X"
    $cmd    = sprintf('wp snapshot backup run --name=%s --allow-root 2>&1', escapeshellarg($name));
    $output = shell_exec($cmd);

    set_transient('wordclaw_snapshot_last_trigger', [
        'name'      => $name,
        'triggered' => current_time('mysql'),
        'output'    => $output,
    ], 3600);

    return new WP_REST_Response([
        'triggered' => true,
        'name'      => $name,
        'output'    => $output,
        'note'      => 'Poll /snapshot/status to check progress.',
    ]);
}

function wordclaw_snapshot_list( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth($req) ) return wordclaw_deny();

    $output = shell_exec('wp snapshot backup list --allow-root 2>&1');

    return new WP_REST_Response([
        'raw'  => $output,
        'note' => 'Parsed list from Snapshot WP-CLI',
    ]);
}

function wordclaw_snapshot_status( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth($req) ) return wordclaw_deny();

    $output = shell_exec('wp snapshot backup status --allow-root 2>&1');
    $last   = get_transient('wordclaw_snapshot_last_trigger');

    return new WP_REST_Response([
        'status'       => trim($output),
        'last_trigger' => $last ?: null,
    ]);
}

// ─── HUMMINGBIRD CALLBACKS ────────────────────────────────────────────────────

function wordclaw_hummingbird_performance( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth($req) ) return wordclaw_deny();

    // Hummingbird stores its last PageSpeed report in options
    $report = get_option('wphb_performance_report', null);

    if ( ! $report ) {
        return new WP_REST_Response([
            'available' => false,
            'message'   => 'No Hummingbird performance report found. Run a performance test in Hummingbird first.',
        ]);
    }

    // Extract the key fields
    $score      = $report['score']      ?? null;
    $score_m    = $report['score_mobile'] ?? null;
    $url        = $report['url']        ?? get_home_url();
    $time       = $report['time']       ?? null;

    return new WP_REST_Response([
        'available'     => true,
        'url'           => $url,
        'score_desktop' => $score,
        'score_mobile'  => $score_m,
        'last_run'      => $time,
        'raw'           => $report,
    ]);
}

function wordclaw_hummingbird_clear_cache( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth($req) ) return wordclaw_deny();

    $body    = $req->get_json_params();
    $page_id = isset($body['page_id']) ? (int)$body['page_id'] : null;

    // Uses Hummingbird's documented action: wphb_clear_page_cache
    do_action( 'wphb_clear_page_cache', $page_id );

    return new WP_REST_Response([
        'cleared' => true,
        'page_id' => $page_id ?? 'all',
        'note'    => $page_id ? "Cache cleared for page ID $page_id" : 'Full page cache cleared',
    ]);
}

// ─── FORMINATOR CALLBACKS ─────────────────────────────────────────────────────

function wordclaw_forminator_forms( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth($req) ) return wordclaw_deny();

    if ( ! class_exists('Forminator_API') ) {
        return new WP_REST_Response(['error' => 'Forminator not active'], 400);
    }

    Forminator_API::initialize();
    $forms = Forminator_API::get_forms( null, 1, 50 );
    $out   = [];

    foreach ( (array)$forms as $form ) {
        $out[] = [
            'id'   => $form->id,
            'name' => $form->name,
        ];
    }

    return new WP_REST_Response([ 'forms' => $out ]);
}

function wordclaw_forminator_entries( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth($req) ) return wordclaw_deny();

    if ( ! class_exists('Forminator_API') ) {
        return new WP_REST_Response(['error' => 'Forminator not active'], 400);
    }

    $form_id = (int) $req->get_param('form_id');
    $since   = sanitize_text_field( $req->get_param('since') ?? '' ); // e.g. "2026-06-20"

    Forminator_API::initialize();
    $entries = Forminator_API::get_entries( $form_id );
    $out     = [];

    foreach ( (array)$entries as $entry ) {
        // Filter by date if requested
        if ( $since && strtotime($entry->time_created) < strtotime($since) ) continue;

        $fields = [];
        foreach ( (array)($entry->meta_data ?? []) as $key => $val ) {
            $fields[$key] = is_array($val) ? ($val['value'] ?? '') : $val;
        }

        $out[] = [
            'entry_id'     => $entry->entry_id,
            'time_created' => $entry->time_created,
            'fields'       => $fields,
        ];
    }

    return new WP_REST_Response([
        'form_id'     => $form_id,
        'count'       => count($out),
        'entries'     => $out,
    ]);
}

// ─── WP-CLI RUNNER CALLBACKS ──────────────────────────────────────────────────

/**
 * Allowed WP-CLI command prefixes (whitelist approach — only these wp commands)
 */
function wordclaw_cli_allowed( string $cmd ): bool {
    // Must start with 'wp '
    if ( ! preg_match( '/^wp /', $cmd ) ) return false;

    // Block shell injection characters
    if ( preg_match( '/[;&|`$\(\)\{\}\[\]<>]/', $cmd ) ) return false;

    // Whitelist of allowed wp sub-commands for safety
    $allowed_prefixes = [
        'wp plugin', 'wp theme', 'wp core', 'wp user', 'wp option',
        'wp cache', 'wp transient', 'wp rewrite', 'wp cron', 'wp db check',
        'wp db optimize', 'wp db repair', 'wp db size', 'wp db export',
        'wp search-replace', 'wp maintenance-mode', 'wp post list',
        'wp post delete', 'wp export', 'wp site list', 'wp site create',
        'wp super-admin', 'wp language', 'wp media regenerate',
        'wp eval \'echo phpversion', 'wp eval \'echo WP_MEMORY_LIMIT',
        'wp config get WP_DEBUG', 'wp config set WP_DEBUG',
        'wp snapshot', 'wp defender',
    ];

    foreach ( $allowed_prefixes as $prefix ) {
        if ( str_starts_with( $cmd, $prefix ) ) return true;
    }

    return false;
}

function wordclaw_cli_run( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth( $req ) ) return wordclaw_deny();

    $body = $req->get_json_params();
    $cmd  = trim( sanitize_text_field( $body['cmd'] ?? '' ) );

    if ( empty( $cmd ) ) {
        return new WP_REST_Response( ['error' => 'No command provided'], 400 );
    }

    if ( ! wordclaw_cli_allowed( $cmd ) ) {
        return new WP_REST_Response( [
            'error'   => 'Command not allowed',
            'command' => $cmd,
            'note'    => 'Only whitelisted wp commands are permitted for security.',
        ], 400 );
    }

    $full_cmd = $cmd . ' --allow-root 2>&1';
    $output   = shell_exec( $full_cmd );

    return new WP_REST_Response( [
        'command'   => $cmd,
        'output'    => trim( (string) $output ),
        'success'   => ! str_contains( (string) $output, 'Error:' ),
    ] );
}

function wordclaw_cli_plugins( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth( $req ) ) return wordclaw_deny();

    $status = sanitize_text_field( $req->get_param('status') ?? 'all' );
    $flag   = in_array( $status, ['active','inactive','must-use','dropin'] )
              ? "--status={$status}"
              : '';

    $output = shell_exec( "wp plugin list {$flag} --format=json --allow-root 2>&1" );
    $parsed = json_decode( (string) $output, true );

    return new WP_REST_Response( [
        'plugins' => $parsed ?? [],
        'raw'     => $parsed ? null : $output,
    ] );
}

function wordclaw_cli_themes( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth( $req ) ) return wordclaw_deny();

    $output = shell_exec( 'wp theme list --format=json --allow-root 2>&1' );
    $parsed = json_decode( (string) $output, true );

    return new WP_REST_Response( [
        'themes' => $parsed ?? [],
        'raw'    => $parsed ? null : $output,
    ] );
}

function wordclaw_cli_updates( WP_REST_Request $req ): WP_REST_Response {
    if ( ! wordclaw_auth( $req ) ) return wordclaw_deny();

    $core    = shell_exec( 'wp core check-update --format=json --allow-root 2>&1' );
    $plugins = shell_exec( 'wp plugin list --update=available --format=json --allow-root 2>&1' );
    $themes  = shell_exec( 'wp theme list --update=available --format=json --allow-root 2>&1' );

    return new WP_REST_Response( [
        'core'    => json_decode( (string) $core, true )    ?? [],
        'plugins' => json_decode( (string) $plugins, true ) ?? [],
        'themes'  => json_decode( (string) $themes, true )  ?? [],
    ] );
}
