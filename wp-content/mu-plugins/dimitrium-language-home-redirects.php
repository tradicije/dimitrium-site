<?php
/**
 * Plugin Name: Dimitrium Language Home Redirects
 * Description: Keeps one canonical home URL per language.
 * License: AGPL-3.0-or-later
 */

add_action(
	'template_redirect',
	static function (): void {
		if ( is_admin() || wp_doing_ajax() ) {
			return;
		}

		$path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), PHP_URL_PATH );
		$path = trim( (string) $path, '/' );
		$path = '' === $path ? '/' : "/{$path}/";

		$redirects = array(
			'/'     => '/en/home/',
			'/en/'  => '/en/home/',
			'/sr/'  => '/sr/pocetna/',
			'/rs/'  => '/sr/pocetna/',
		);

		if ( isset( $redirects[ $path ] ) ) {
			wp_safe_redirect( home_url( $redirects[ $path ] ), 301, 'Dimitrium Language Home Redirects' );
			exit;
		}
	},
	0
);
