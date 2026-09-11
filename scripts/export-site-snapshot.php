<?php
/**
 * Create a public, reviewable WordPress snapshot for this repository.
 *
 * Run from a trusted WordPress environment:
 * WP_LOAD_PATH=/var/www/html/wp-load.php \
 * DIMITRIUM_EXPORT_DIR=/path/to/dimitrium-site/exports \
 * php scripts/export-site-snapshot.php
 *
 * It is deliberately not a database dump. Published public content, templates,
 * navigation, custom post types, Polylang metadata, and selected safe settings
 * are exported. Credentials and obvious secret-bearing values are redacted.
 */

$wp_load = getenv( 'WP_LOAD_PATH' );
$output  = getenv( 'DIMITRIUM_EXPORT_DIR' );

if ( ! $wp_load || ! is_readable( $wp_load ) || ! $output ) {
	fwrite( STDERR, "Set WP_LOAD_PATH and DIMITRIUM_EXPORT_DIR before running this exporter.\n" );
	exit( 1 );
}

require_once $wp_load;
require_once ABSPATH . 'wp-admin/includes/export.php';

if ( ! wp_mkdir_p( $output ) ) {
	fwrite( STDERR, "Could not create export directory.\n" );
	exit( 1 );
}

/**
 * Recursively replace configuration values whose keys imply credentials or
 * session material. Retaining the key makes the export useful as a setup map.
 */
function dimitrium_snapshot_redact( $value, $key = '' ) {
	if ( is_string( $key ) && preg_match( '/(?:pass(?:word)?|secret|token|api[_-]?key|private[_-]?key|auth|cookie)/i', $key ) ) {
		return '[redacted]';
	}
	if ( is_array( $value ) ) {
		$clean = array();
		foreach ( $value as $child_key => $child_value ) {
			$clean[ $child_key ] = dimitrium_snapshot_redact( $child_value, (string) $child_key );
		}
		return $clean;
	}
	if ( is_object( $value ) ) {
		return dimitrium_snapshot_redact( (array) $value, $key );
	}
	return $value;
}

ob_start();
export_wp( array( 'content' => 'all' ) );
$xml = ob_get_clean();

// A public snapshot has no use for user e-mail addresses or protected posts.
$xml = preg_replace( '#<wp:author_email>.*?</wp:author_email>#s', '<wp:author_email><![CDATA[]]></wp:author_email>', $xml );
$xml = preg_replace( '#<wp:post_password>.*?</wp:post_password>#s', '<wp:post_password><![CDATA[]]></wp:post_password>', $xml );
$xml = preg_replace_callback(
	'#<item>.*?</item>#s',
	static function ( $match ) {
		$item = $match[0];
		$is_attachment = false !== strpos( $item, '<wp:post_type><![CDATA[attachment]]></wp:post_type>' );
		$is_published  = false !== strpos( $item, '<wp:status><![CDATA[publish]]></wp:status>' );
		$is_proprietary_asset = $is_attachment && preg_match(
			'#/wp-content/uploads/(?:fonts/Minicomputer-[^<]*\\.ttf|social-icons/)#i',
			$item
		);
		return ( $is_attachment || $is_published ) && ! $is_proprietary_asset ? $item : '';
	},
	$xml
);
$xml = preg_replace(
	'#https?://dimitrium\.org/wp-content/uploads/fonts/Minicomputer-[^\s"\'<>]+\.ttf#i',
	'',
	$xml
);
$xml = preg_replace(
	'#https?://dimitrium\.org/wp-content/uploads/social-icons/[^\s"\'<>]+\.svg#i',
	'',
	$xml
);
$xml = str_ireplace( 'Minicomputer', 'monospace', $xml );

$xml_path = trailingslashit( $output ) . 'dimitrium-content.xml';
if ( false === file_put_contents( $xml_path, $xml ) ) {
	fwrite( STDERR, "Could not write WXR export.\n" );
	exit( 1 );
}

$safe_options = array(
	'site' => array(
		'blogname'            => get_option( 'blogname' ),
		'blogdescription'     => get_option( 'blogdescription' ),
		'home'                => get_option( 'home' ),
		'siteurl'             => get_option( 'siteurl' ),
		'permalink_structure' => get_option( 'permalink_structure' ),
		'timezone_string'     => get_option( 'timezone_string' ),
		'date_format'         => get_option( 'date_format' ),
		'time_format'         => get_option( 'time_format' ),
		'start_of_week'       => get_option( 'start_of_week' ),
		'stylesheet'          => get_option( 'stylesheet' ),
		'template'            => get_option( 'template' ),
		'active_plugins'      => get_option( 'active_plugins' ),
	),
	'polylang'                  => get_option( 'polylang', array() ),
	'theme_mods_assembler-wpcom' => get_option( 'theme_mods_assembler-wpcom', array() ),
	'cptui_post_types'          => get_option( 'cptui_post_types', array() ),
	'cptui_taxonomies'          => get_option( 'cptui_taxonomies', array() ),
);

$settings_path = trailingslashit( $output ) . 'site-settings.json';
$settings_json = wp_json_encode(
	dimitrium_snapshot_redact( $safe_options ),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
);

if ( false === file_put_contents( $settings_path, $settings_json . "\n" ) ) {
	fwrite( STDERR, "Could not write settings export.\n" );
	exit( 1 );
}

echo "Wrote {$xml_path} and {$settings_path}.\n";
