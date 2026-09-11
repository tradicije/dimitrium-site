<?php
/**
 * Plugin Name: Dimitrium SEO
 * Description: Lightweight multilingual metadata, social previews, schema and sitemap for dimitrium.org.
 * Version: 1.2.0
 * Author: Dimitrium
 * License: AGPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/includes/schema/class-dimitrium-schema-registry.php';
require_once __DIR__ . '/includes/schema/class-dimitrium-schema-context.php';
require_once __DIR__ . '/includes/schema/class-dimitrium-schema-builder.php';

// Run before Polylang's language redirect and before the sitemap response.
function dimitrium_seo_redirects() {
	if ( is_admin() || wp_doing_ajax() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
		return;
	}
	$request = wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' );
	$path = wp_parse_url( $request, PHP_URL_PATH );
	$aliases = array( '/en/music' => '/en/musique/', '/en/music/' => '/en/musique/' );
	$target = $aliases[ $path ] ?? null;
	// The tunnel forwards the visitor protocol; never infer it from the origin port.
	$proto = strtolower( trim( explode( ',', $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '' )[0] ) );
	if ( ! $target && 'http' !== $proto ) {
		return;
	}
	$query = wp_parse_url( $request, PHP_URL_QUERY );
	$url = 'https://dimitrium.org' . '/' . ltrim( $target ?? $path, '/' );
	if ( null !== $query && false !== $query && '' !== $query ) {
		$url .= '?' . $query;
	}
	wp_safe_redirect( $url, 301, 'Dimitrium SEO' );
	exit;
}
add_action( 'template_redirect', 'dimitrium_seo_redirects', -20 );

/** Do not let WordPress guess a surviving page for deliberately retired URLs. */
function dimitrium_seo_disable_404_guess_for_retired_pages( $allow_guess ) {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	$path = trailingslashit( $path );
	$retired_paths = array( '/en/flex/', '/sr/flex/', '/en/cat/', '/sr/macka/' );
	return in_array( $path, $retired_paths, true ) ? false : $allow_guess;
}
add_filter( 'do_redirect_guess_404_permalink', 'dimitrium_seo_disable_404_guess_for_retired_pages' );

/**
 * Let two translated pages share a slug when Polylang distinguishes them by
 * the language directory (for example /en/dimitrium/ and /sr/dimitrium/).
 *
 * WordPress checks slug uniqueness before it knows the request language. This
 * filter keeps the core protection for every other case: a slug is shared only
 * when every colliding page belongs to this page's Polylang translation group.
 */
function dimitrium_seo_shared_translation_page_slug( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
	if ( 'page' !== $post_type || ! function_exists( 'pll_get_post_translations' ) ) {
		return $slug;
	}

	$wanted_slug = sanitize_title( $original_slug );
	if ( '' === $wanted_slug || $slug === $wanted_slug ) {
		return $slug;
	}

	$translations = pll_get_post_translations( $post_id );
	if ( count( $translations ) < 2 ) {
		return $slug;
	}

	global $wpdb;
	$colliding_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' AND post_parent = %d AND ID != %d",
			$wanted_slug,
			(int) $post_parent,
			(int) $post_id
		)
	);
	if ( empty( $colliding_ids ) ) {
		return $slug;
	}

	$translation_ids = array_map( 'intval', array_values( $translations ) );
	foreach ( $colliding_ids as $colliding_id ) {
		if ( ! in_array( (int) $colliding_id, $translation_ids, true ) ) {
			return $slug;
		}
	}

	return $wanted_slug;
}
add_filter( 'wp_unique_post_slug', 'dimitrium_seo_shared_translation_page_slug', 20, 6 );

/**
 * WordPress resolves a page path before Polylang filters the main query. When
 * translated pages share a slug, it would otherwise select the first matching
 * page (usually English) and Polylang would canonically redirect to it.
 */
function dimitrium_seo_resolve_shared_translation_page_slug( $query_vars ) {
	if ( is_admin() || empty( $query_vars['pagename'] ) || empty( $query_vars['lang'] ) || ! function_exists( 'pll_get_post_language' ) ) {
		return $query_vars;
	}

	$language = sanitize_key( $query_vars['lang'] );
	$page_path = trim( (string) $query_vars['pagename'], '/' );
	$segments  = explode( '/', $page_path );
	$slug      = sanitize_title( end( $segments ) );
	if ( '' === $slug ) {
		return $query_vars;
	}

	global $wpdb;
	$candidate_ids = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'page' AND post_status NOT IN ('auto-draft', 'trash')",
			$slug
		)
	);
	foreach ( $candidate_ids as $candidate_id ) {
		$candidate_id = (int) $candidate_id;
		if ( $page_path === get_page_uri( $candidate_id ) && $language === pll_get_post_language( $candidate_id, 'slug' ) ) {
			$query_vars['page_id'] = $candidate_id;
			unset( $query_vars['pagename'] );
			return $query_vars;
		}
	}

	return $query_vars;
}
add_filter( 'request', 'dimitrium_seo_resolve_shared_translation_page_slug', 20 );

function dimitrium_seo_news_archive() {
	return is_post_type_archive( 'dimitrium_news' );
}

add_filter( 'get_the_archive_title', function ( $title ) {
	if ( dimitrium_seo_news_archive() ) {
		return function_exists( 'pll_current_language' ) && 'sr' === pll_current_language() ? 'Vesti' : 'News';
	}
	return $title;
} );

function dimitrium_seo_archive_url() {
	$lang = function_exists( 'pll_current_language' ) ? pll_current_language() : 'en';
	$url = home_url( '/' . ( 'sr' === $lang ? 'sr' : 'en' ) . '/news/' );
	$page = max( 1, (int) get_query_var( 'paged' ) );
	return $page > 1 ? $url . 'page/' . $page . '/' : $url;
}

/**
 * The one canonical URL source for metadata and structured data.
 */
function dimitrium_seo_canonical_url( $post_id = 0 ) {
	if ( dimitrium_seo_news_archive() ) {
		return dimitrium_seo_archive_url();
	}
	return get_permalink( $post_id ?: get_queried_object_id() );
}

// Promote only the page's own title, never titles in related-post/query lists.
add_filter( 'render_block_data', function ( $block, $source, $parent ) {
	if ( is_admin() ) {
		return $block;
	}
	if ( 'core/post-title' === $block['blockName'] && is_singular() && empty( $block['attrs']['isLink'] ) ) {
		$id = $parent->context['postId'] ?? get_queried_object_id();
		if ( (int) $id === get_queried_object_id() && ! preg_match( '/<h1\b/i', get_post_field( 'post_content', $id ) ) ) {
			$block['attrs']['level'] = 1;
		}
	}
	if ( 'core/query-title' === $block['blockName'] && dimitrium_seo_news_archive() ) {
		$block['attrs']['level'] = 1;
	}
	return $block;
}, 10, 3 );

function dimitrium_seo_description( $post_id = 0 ) {
	if ( dimitrium_seo_news_archive() ) {
		return function_exists( 'pll_current_language' ) && 'sr' === pll_current_language()
			? 'Najnovije vesti iz Dimitrium sveta: nova muzika Alex Dietricha, album Unendlich, lični projekti, sajt i mačka Nala.'
			: 'The latest from Dimitrium: new music by Alex Dietrich, the Unendlich album, personal projects, website updates and Nala the cat.';
	}
	$post_id = $post_id ?: get_queried_object_id();
	$custom  = trim( (string) get_post_meta( $post_id, '_dimitrium_seo_description', true ) );
	if ( $custom ) {
		return $custom;
	}

	$curated = array(
		122 => 'Aleksa Dimitrijević’s personal corner of the internet—original music, WordPress, self-hosted servers, projects and Nala the cat.',
		123 => 'Lični kutak Alekse Dimitrijevića na internetu — originalna muzika, WordPress, self-hosted serveri, projekti i mačka Nala.',
		142 => 'Listen to original music by Alex Dietrich, explore the Unendlich album and use the custom Dimitrium music player.',
		143 => 'Poslušaj originalnu muziku Alex Dietricha, istraži album Unendlich i koristi prilagođeni Dimitrium muzički plejer.',
	);
	if ( isset( $curated[ $post_id ] ) ) {
		return $curated[ $post_id ];
	}

	$post = get_post( $post_id );
	if ( ! $post ) {
		return 'Dimitrium — music, projects, servers and a cat.';
	}
	$text = has_excerpt( $post ) ? $post->post_excerpt : $post->post_content;
	$text = preg_replace( '/<!--.*?-->/s', ' ', strip_shortcodes( $text ) );
	$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $text ) ) );
	if ( ! $text ) {
		$text = get_the_title( $post ) . ' — Dimitrium.';
	}
	return wp_html_excerpt( $text, 155, '…' );
}

function dimitrium_seo_title() {
	if ( dimitrium_seo_news_archive() ) {
		$sr = function_exists( 'pll_current_language' ) && 'sr' === pll_current_language();
		$page = max( 1, (int) get_query_var( 'paged' ) );
		return ( $sr ? 'Vesti' : 'News' ) . ( $page > 1 ? ( $sr ? ' — Strana ' : ' — Page ' ) . $page : '' ) . ' | Dimitrium';
	}
	$post_id = get_queried_object_id();
	$custom  = trim( (string) get_post_meta( $post_id, '_dimitrium_seo_title', true ) );
	if ( $custom ) {
		return $custom;
	}
	if ( 122 === $post_id ) {
		return 'Dimitrium | Aleksa Dimitrijević: Music, Projects & Self-Hosted Experiments';
	}
	if ( 123 === $post_id ) {
		return 'Dimitrium | Aleksa Dimitrijević: Muzika, projekti i self-hosted eksperimenti';
	}
	if ( is_singular() ) {
		return get_the_title( $post_id ) . ' | Dimitrium';
	}
	return wp_get_document_title();
}

function dimitrium_seo_document_title( $title ) {
	return is_singular() || dimitrium_seo_news_archive() ? dimitrium_seo_title() : $title;
}
add_filter( 'pre_get_document_title', 'dimitrium_seo_document_title', 20 );

function dimitrium_seo_image( $post_id ) {
	$custom = trim( (string) get_post_meta( $post_id, '_dimitrium_seo_image', true ) );
	if ( $custom ) {
		return $custom;
	}
	// The wide Unendlich cover is the intended social preview for music and album pages.
	if ( in_array( (int) $post_id, array( 142, 143, 434, 436 ), true ) ) {
		return 'https://dimitrium.org/wp-content/uploads/2026/09/Unendlich-Album-Cover-OG.png';
	}
	if ( has_post_thumbnail( $post_id ) ) {
		return wp_get_attachment_image_url( get_post_thumbnail_id( $post_id ), 'full' );
	}
	$content = (string) get_post_field( 'post_content', $post_id );
	if ( preg_match( '/wp-image-(\d+)/', $content, $match ) ) {
		$image = wp_get_attachment_image_url( (int) $match[1], 'full' );
		if ( $image ) {
			return $image;
		}
	}
	return 'https://dimitrium.org/wp-content/uploads/2026/09/ChatGPT-Image-Sep-5-2026-02_34_20-AM.png';
}

function dimitrium_seo_image_dimensions( $post_id, $image_url ) {
	$attachment_id = attachment_url_to_postid( $image_url );
	if ( ! $attachment_id && has_post_thumbnail( $post_id ) ) {
		$attachment_id = get_post_thumbnail_id( $post_id );
	}
	$metadata = $attachment_id ? wp_get_attachment_metadata( $attachment_id ) : array();
	if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
		return array( (int) $metadata['width'], (int) $metadata['height'] );
	}
	return array();
}

function dimitrium_seo_head() {
	if ( ! is_singular() && ! dimitrium_seo_news_archive() ) {
		return;
	}
	$post_id     = is_singular() ? get_queried_object_id() : 0;
	$canonical   = dimitrium_seo_canonical_url( $post_id );
	$title       = dimitrium_seo_title();
	$description = dimitrium_seo_description( $post_id );
	$image       = dimitrium_seo_image( $post_id );
	$lang        = function_exists( 'pll_current_language' ) ? pll_current_language() : substr( get_locale(), 0, 2 );
	$locale      = 'sr' === $lang ? 'sr_RS' : 'en_US';
	$noindex     = (bool) get_post_meta( $post_id, '_dimitrium_seo_noindex', true );
	$type        = is_singular( array( 'dimitrium_news', 'dimipedia_entry' ) ) ? 'article' : 'website';
	$image_size  = dimitrium_seo_image_dimensions( $post_id, $image );

	if ( $noindex ) {
		echo "\n<meta name=\"robots\" content=\"noindex,follow\">";
	}
	echo "\n<meta name=\"description\" content=\"" . esc_attr( $description ) . "\">";
	echo "\n<link rel=\"canonical\" href=\"" . esc_url( $canonical ) . "\">";
	if ( is_singular() && function_exists( 'pll_get_post_translations' ) ) {
		$translations = pll_get_post_translations( $post_id );
		foreach ( $translations as $language => $translation_id ) {
			echo "\n<link rel=\"alternate\" hreflang=\"" . esc_attr( $language ) . "\" href=\"" . esc_url( get_permalink( $translation_id ) ) . "\">";
		}
		if ( ! empty( $translations['en'] ) ) {
			echo "\n<link rel=\"alternate\" hreflang=\"x-default\" href=\"" . esc_url( get_permalink( $translations['en'] ) ) . "\">";
		}
	}
	echo "\n<meta property=\"og:type\" content=\"" . esc_attr( $type ) . "\">";
	echo "\n<meta property=\"og:locale\" content=\"" . esc_attr( $locale ) . "\">";
	echo "\n<meta property=\"og:site_name\" content=\"Dimitrium\">";
	echo "\n<meta property=\"og:title\" content=\"" . esc_attr( $title ) . "\">";
	echo "\n<meta property=\"og:description\" content=\"" . esc_attr( $description ) . "\">";
	echo "\n<meta property=\"og:url\" content=\"" . esc_url( $canonical ) . "\">";
	echo "\n<meta property=\"og:image\" content=\"" . esc_url( $image ) . "\">";
	if ( $image_size ) {
		echo "\n<meta property=\"og:image:width\" content=\"" . (int) $image_size[0] . "\">";
		echo "\n<meta property=\"og:image:height\" content=\"" . (int) $image_size[1] . "\">";
	}
	echo "\n<meta name=\"twitter:card\" content=\"summary_large_image\">";
	echo "\n<meta name=\"twitter:title\" content=\"" . esc_attr( $title ) . "\">";
	echo "\n<meta name=\"twitter:description\" content=\"" . esc_attr( $description ) . "\">";
	echo "\n<meta name=\"twitter:image\" content=\"" . esc_url( $image ) . "\">\n";

	if ( ! $noindex ) {
		echo Dimitrium_SEO_Schema_Builder::output();
	}
}
remove_action( 'wp_head', 'rel_canonical' );
add_action( 'wp_head', 'dimitrium_seo_head', 2 );

function dimitrium_seo_meta_box() {
	foreach ( array( 'page', 'dimitrium_news', 'dimipedia_entry' ) as $post_type ) {
		add_meta_box( 'dimitrium-seo', 'Dimitrium SEO', 'dimitrium_seo_meta_box_render', $post_type, 'normal', 'default' );
	}
}
add_action( 'add_meta_boxes', 'dimitrium_seo_meta_box' );

function dimitrium_seo_meta_box_render( $post ) {
	wp_nonce_field( 'dimitrium_seo_save', 'dimitrium_seo_nonce' );
	$fields = array(
		'_dimitrium_seo_title'       => 'SEO title override',
		'_dimitrium_seo_description' => 'Meta description override',
		'_dimitrium_seo_image'       => 'Social image URL override',
	);
	foreach ( $fields as $key => $label ) {
		echo '<p><label for="' . esc_attr( $key ) . '"><strong>' . esc_html( $label ) . '</strong></label><br>';
		echo '<input class="widefat" id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( get_post_meta( $post->ID, $key, true ) ) . '"></p>';
	}
	echo '<p><label><input type="checkbox" name="_dimitrium_seo_noindex" value="1" ' . checked( get_post_meta( $post->ID, '_dimitrium_seo_noindex', true ), '1', false ) . '> Hide this page from search engines</label></p>';
	echo '<p><small>Empty fields use automatic values generated from the page title, excerpt/content and first image.</small></p>';
}

function dimitrium_seo_save_meta( $post_id ) {
	if ( ! isset( $_POST['dimitrium_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dimitrium_seo_nonce'] ) ), 'dimitrium_seo_save' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	foreach ( array( '_dimitrium_seo_title', '_dimitrium_seo_description', '_dimitrium_seo_image' ) as $key ) {
		$value = isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : '';
		$value ? update_post_meta( $post_id, $key, $value ) : delete_post_meta( $post_id, $key );
	}
	isset( $_POST['_dimitrium_seo_noindex'] ) ? update_post_meta( $post_id, '_dimitrium_seo_noindex', '1' ) : delete_post_meta( $post_id, '_dimitrium_seo_noindex' );
}
add_action( 'save_post', 'dimitrium_seo_save_meta' );

function dimitrium_seo_sitemap() {
	$path = wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
	if ( '/wp-sitemap.xml' !== $path ) {
		return;
	}
	$posts = get_posts( array(
		'post_type'        => array( 'page', 'dimitrium_news', 'dimipedia_entry' ),
		'post_status'      => 'publish',
		'posts_per_page'   => -1,
		'orderby'          => 'modified',
		'order'            => 'DESC',
		'lang'             => '',
		'suppress_filters' => true,
	) );
	status_header( 200 );
	header( 'Content-Type: application/xml; charset=UTF-8' );
	echo '<?xml version="1.0" encoding="UTF-8"?>';
	echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
	foreach ( $posts as $post ) {
		if ( get_post_meta( $post->ID, '_dimitrium_seo_noindex', true ) ) {
			continue;
		}
		echo '<url><loc>' . esc_url( get_permalink( $post ) ) . '</loc><lastmod>' . esc_html( get_post_modified_time( DATE_W3C, true, $post ) ) . '</lastmod>';
		if ( function_exists( 'pll_get_post_translations' ) ) {
			foreach ( pll_get_post_translations( $post->ID ) as $language => $translation_id ) {
				echo '<xhtml:link rel="alternate" hreflang="' . esc_attr( $language ) . '" href="' . esc_url( get_permalink( $translation_id ) ) . '" />';
			}
		}
		echo '</url>';
	}
	echo '</urlset>';
	exit;
}
add_action( 'template_redirect', 'dimitrium_seo_sitemap', 0 );
