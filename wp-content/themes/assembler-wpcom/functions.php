<?php
/**
 * Assembler functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package Assembler
 * @since Assembler 1.0
 */

declare( strict_types = 1 );

if ( ! function_exists( 'assembler_unregister_patterns' ) ) :
	/**
	 * Unregister Jetpack patterns and core patterns bundled in WordPress.
	 */
	function assembler_unregister_patterns() {
		$pattern_names = array(
			// Jetpack form patterns.
			'contact-form',
			'newsletter-form',
			'rsvp-form',
			'registration-form',
			'appointment-form',
			'feedback-form',
			// Patterns bundled in WordPress core.
			// These would be removed by remove_theme_support( 'core-block-patterns' )
			// if it's called on the init action with priority 9 from a plugin, not from a theme.
			'core/query-standard-posts',
			'core/query-medium-posts',
			'core/query-small-posts',
			'core/query-grid-posts',
			'core/query-large-title-posts',
			'core/query-offset-posts',
			'core/social-links-shared-background-color',
		);
		foreach ( $pattern_names as $pattern_name ) {
			$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( $pattern_name );
			if ( $pattern ) {
				unregister_block_pattern( $pattern_name );
			}
		}
	}

endif;

if ( ! function_exists( 'assembler_setup' ) ) :
	/**
	 * Sets up theme defaults and registers support for various WordPress features.
	 *
	 * @since Assembler 1.0
	 *
	 * @return void
	 */
	function assembler_setup() {

		// Make theme available for translation.
		load_theme_textdomain( 'assembler', get_template_directory() . '/languages' );

		// Enqueue editor styles.
		add_editor_style( 'style.css' );
		// Unregister Jetpack form patterns and core patterns bundled in WordPress.
		// Simple sites
		assembler_unregister_patterns();
		add_filter( 'wp_loaded', function () {
			// Atomic sites
			assembler_unregister_patterns();
		} );
		// Remove theme support for the core and featured patterns coming from the Dotorg pattern directory.
		remove_theme_support( 'core-block-patterns' );
	}

endif;

add_action( 'after_setup_theme', 'assembler_setup' );

if ( ! function_exists( 'assembler_styles' ) ) :
	/**
	 * Enqueue styles.
	 *
	 * @since Assembler 1.0
	 *
	 * @return void
	 */
	function assembler_styles() {

		// Register theme stylesheet.
		wp_register_style(
			'assembler-style',
			get_stylesheet_directory_uri() . '/style.css',
			array(),
			wp_get_theme()->get( 'Version' )
		);

		// Enqueue theme stylesheet.
		wp_enqueue_style( 'assembler-style' );

	}

endif;

add_action( 'wp_enqueue_scripts', 'assembler_styles' );

if ( ! function_exists( 'assembler_preload_fonts' ) ) :
	/**
	 * Preload the base Inter variable font to reduce FOUT.
	 *
	 * @since Assembler 1.0
	 *
	 * @return void
	 */
	function assembler_preload_fonts() {
		$font_url = get_stylesheet_directory_uri() . '/assets/fonts/inter/InterVariable.woff2';
		echo '<link rel="preload" href="' . esc_url( $font_url ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
	}

endif;

add_action( 'wp_head', 'assembler_preload_fonts', 1 );

if ( ! function_exists( 'assembler_is_woocommerce_active' ) ) :
	/**
	 * Check whether WooCommerce is active.
	 *
	 * @since Assembler 1.0
	 *
	 * @return bool
	 */
	function assembler_is_woocommerce_active(): bool {
		return class_exists( 'WooCommerce' );
	}

endif;

if ( ! function_exists( 'assembler_get_woocommerce_template_slugs' ) ) :
	/**
	 * Get WooCommerce block template slugs bundled with the theme.
	 *
	 * @since Assembler 1.0
	 *
	 * @return string[]
	 */
	function assembler_get_woocommerce_template_slugs(): array {
		return array(
			'archive-product',
			'coming-soon',
			'order-confirmation',
			'page-cart',
			'page-checkout',
			'product-search-results',
			'single-product',
			'taxonomy-product_attribute',
			'taxonomy-product_cat',
			'taxonomy-product_tag',
		);
	}

endif;

if ( ! function_exists( 'assembler_get_woocommerce_template_part_slugs' ) ) :
	/**
	 * Get WooCommerce block template part slugs bundled with the theme.
	 *
	 * @since Assembler 1.0
	 *
	 * @return string[]
	 */
	function assembler_get_woocommerce_template_part_slugs(): array {
		return array(
			'checkout-header',
			'coming-soon-social-links',
			'external-product-add-to-cart-with-options',
			'grouped-product-add-to-cart-with-options',
			'mini-cart',
			'simple-product-add-to-cart-with-options',
			'variable-product-add-to-cart-with-options',
		);
	}

endif;

if ( ! function_exists( 'assembler_get_woocommerce_pattern_slugs' ) ) :
	/**
	 * Get WooCommerce block pattern slugs bundled with the theme.
	 *
	 * @since Assembler 1.0
	 *
	 * @return string[]
	 */
	function assembler_get_woocommerce_pattern_slugs(): array {
		return array(
			'assembler/hidden-cart-page-title',
			'assembler/hidden-cross-sells',
			'assembler/hidden-empty-cart-block',
			'assembler/hidden-empty-mini-cart',
			'assembler/hidden-no-product-search-results',
			'assembler/hidden-order-confirmation-additional-information-title',
			'assembler/hidden-order-confirmation-billing-address-title',
			'assembler/hidden-order-confirmation-downloads-title',
			'assembler/hidden-order-confirmation-order-details-title',
			'assembler/hidden-order-confirmation-shipping-address-title',
			'assembler/hidden-related-products',
			'assembler/hidden-upsells',
		);
	}

endif;

if ( ! function_exists( 'assembler_filter_woocommerce_block_templates' ) ) :
	/**
	 * Hide WooCommerce block templates and template parts when WooCommerce is inactive.
	 *
	 * @since Assembler 1.0
	 *
	 * @param \WP_Block_Template[] $templates     The list of templates.
	 * @param array                $query         Arguments to retrieve templates.
	 * @param string               $template_type Template type.
	 *
	 * @return \WP_Block_Template[]
	 */
	function assembler_filter_woocommerce_block_templates( array $templates, array $query, string $template_type ): array {
		if ( assembler_is_woocommerce_active() ) {
			return $templates;
		}

		$slugs = 'wp_template_part' === $template_type
			? assembler_get_woocommerce_template_part_slugs()
			: assembler_get_woocommerce_template_slugs();

		foreach ( $templates as $key => $template ) {
			if ( in_array( $template->slug, $slugs, true ) ) {
				unset( $templates[ $key ] );
			}
		}

		return array_values( $templates );
	}

endif;

add_filter( 'get_block_templates', 'assembler_filter_woocommerce_block_templates', 99, 3 );

if ( ! function_exists( 'assembler_filter_woocommerce_theme_json' ) ) :
	/**
	 * Hide WooCommerce templates and template parts from theme.json when WooCommerce is inactive.
	 *
	 * @since Assembler 1.0
	 *
	 * @param \WP_Theme_JSON_Data $theme_json Theme JSON data.
	 *
	 * @return \WP_Theme_JSON_Data
	 */
	function assembler_filter_woocommerce_theme_json( $theme_json ) {
		if ( assembler_is_woocommerce_active() ) {
			return $theme_json;
		}

		$data = $theme_json->get_data();

		$data['customTemplates'] = array_values(
			array_filter(
				$data['customTemplates'] ?? array(),
				function ( $template ) {
					return ! in_array( $template['name'], assembler_get_woocommerce_template_slugs(), true );
				}
			)
		);

		$data['templateParts'] = array_values(
			array_filter(
				$data['templateParts'] ?? array(),
				function ( $part ) {
					return ! in_array( $part['name'], assembler_get_woocommerce_template_part_slugs(), true );
				}
			)
		);

		/*
		 * Return a fresh data object rather than update_with(): WP_Theme_JSON::merge()
		 * unions sequential arrays such as customTemplates and templateParts by index, so
		 * update_with() can add entries but never remove them. The resolver consumes the
		 * returned object's data directly, so rebuilding from the reduced sets drops the
		 * WooCommerce templates and parts as intended.
		 */
		return new WP_Theme_JSON_Data( $data, 'theme' );
	}

endif;

add_filter( 'wp_theme_json_data_theme', 'assembler_filter_woocommerce_theme_json' );

if ( ! function_exists( 'assembler_unregister_woocommerce_patterns' ) ) :
	/**
	 * Unregister WooCommerce block patterns when WooCommerce is inactive.
	 *
	 * @since Assembler 1.0
	 *
	 * @return void
	 */
	function assembler_unregister_woocommerce_patterns() {
		if ( assembler_is_woocommerce_active() ) {
			return;
		}

		foreach ( assembler_get_woocommerce_pattern_slugs() as $pattern_slug ) {
			$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( $pattern_slug );
			if ( $pattern ) {
				unregister_block_pattern( $pattern_slug );
			}
		}
	}

endif;

add_action( 'init', 'assembler_unregister_woocommerce_patterns', 20 );
add_action( 'wp_loaded', 'assembler_unregister_woocommerce_patterns' );

if ( ! function_exists( 'assembler_remove_upsells' ) ) :
	/**
	 * Remove upsells from product description.
	 *
	 * @since Assembler 1.0
	 *
	 * @return void
	 */
	function assembler_remove_upsells() {
		if ( ! assembler_is_woocommerce_active() ) {
			return;
		}

		remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );
	}

endif;

add_action( 'init', 'assembler_remove_upsells' );


// updater for WordPress.com themes
if ( is_admin() )
	include dirname( __FILE__ ) . '/inc/updater.php';
