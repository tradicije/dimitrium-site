<?php
/**
 * Plugin Name: Dimitrium Now Playing
 * Description: A bottom now-playing bar synchronized with the WordPress core Playlist block.
 * Version: 0.2.60.133
 * Author: Dimitrium
 * License: AGPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function dimitrium_register_news_post_type() {
	register_post_type(
		'dimitrium_news',
		array(
			'labels' => array(
				'name'          => 'News',
				'singular_name' => 'News item',
				'add_new_item'  => 'Add news item',
				'edit_item'     => 'Edit news item',
			),
			'public'       => true,
			'show_in_rest' => true,
			'has_archive'  => true,
			'menu_icon'    => 'dashicons-megaphone',
			'rewrite'      => array( 'slug' => 'news', 'with_front' => true ),
			'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
		)
	);
}
add_action( 'init', 'dimitrium_register_news_post_type' );

function dimitrium_polylang_news_post_type( $post_types, $is_settings ) {
	$post_types['dimitrium_news'] = 'dimitrium_news';
	return $post_types;
}
add_filter( 'pll_get_post_types', 'dimitrium_polylang_news_post_type', 10, 2 );

function dimitrium_news_marquee_shortcode() {
	$query_args = array(
		'post_type'      => 'dimitrium_news',
		'post_status'    => 'publish',
		'posts_per_page' => 5,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	);
	if ( function_exists( 'pll_current_language' ) ) {
		$query_args['lang'] = pll_current_language();
	}
	$items = get_posts( $query_args );
	if ( ! $items && ! empty( $query_args['lang'] ) ) {
		$query_args['lang'] = '';
		$query_args['suppress_filters'] = true;
		$items = get_posts( $query_args );
	}
	if ( ! $items ) {
		return '';
	}

	$sets = '';
	for ( $copy = 0; $copy < 2; $copy++ ) {
		$links = '';
		foreach ( $items as $item ) {
			$links .= sprintf(
				'<a href="%1$s"%2$s><span aria-hidden="true">✦</span>%3$s</a>',
				esc_url( get_permalink( $item ) ),
				$copy ? ' tabindex="-1"' : '',
				esc_html( get_the_title( $item ) )
			);
		}
		$sets .= '<div class="dimitrium-news-marquee__set"' . ( $copy ? ' aria-hidden="true"' : '' ) . '>' . $links . '</div>';
	}
	$label = function_exists( 'pll_current_language' ) && 'sr' === pll_current_language() ? 'VESTI' : 'NEWS';
	return '<nav class="dimitrium-news-marquee" aria-label="' . esc_attr__( 'Latest news', 'dimitrium' ) . '" data-news-marquee><span class="dimitrium-news-marquee__label">' . esc_html( $label ) . '</span><div class="dimitrium-news-marquee__viewport"><div class="dimitrium-news-marquee__track">' . $sets . '</div></div></nav>';
}
add_shortcode( 'dimitrium_news_marquee', 'dimitrium_news_marquee_shortcode' );

/** Render one localized legal/footer copy instead of shipping both languages. */
function dimitrium_footer_legal_shortcode() {
	$sr = function_exists( 'pll_current_language' ) && 'sr' === pll_current_language( 'slug' );
	$copy = $sr
		? 'Ne, nisam se potrudio da ovde stavim tekst o autorskim pravima; i da, ovo je jedini footer koji za sada dobijaš.'
		: 'No, I did not bother to place the copyright text here, and yes, this is the only footer you get for now.';
	$tagline = 'Stay frosty!';
	$source = $sr ? 'Izvorni kod' : 'Source code';
	$license = $sr ? 'Slike i muzika, osim fotografija Nale' : 'Images and music, except Nala photography';
	$nala = $sr ? 'Fotografije Nale' : 'Nala photography';
	$noir_url = $sr ? home_url( '/sr/noir-licenca/' ) : home_url( '/en/noir-license/' );

	return '<div class="dimitrium-footer-legal">'
		. '<p class="dimitrium-footer-copy">' . esc_html( $copy ) . '</p>'
		. '<p class="dimitrium-footer-tagline">' . esc_html( $tagline ) . '</p>'
		. '<p class="dimitrium-footer-source">' . esc_html( $source ) . ': <a href="https://github.com/tradicije/dimitrium-site">GitHub</a> · AGPLv3</p>'
		. '<p class="dimitrium-footer-license">' . esc_html( $license ) . ': <a href="https://artlibre.org/licence/lal/en/">Free Art License 1.3</a> · ' . esc_html( $nala ) . ': <a href="' . esc_url( $noir_url ) . '">NoIR ' . esc_html( $sr ? 'licenca' : 'License' ) . '</a></p>'
		. '</div>';
}
add_shortcode( 'dimitrium_footer_legal', 'dimitrium_footer_legal_shortcode' );

function dimitrium_now_playing_is_music_page() {
	return is_page( array( 122, 123, 142, 143, 434, 436 ) );
}

function dimitrium_now_playing_enqueue_header_style() {
	wp_enqueue_style(
		'dimitrium-frosted-header',
		plugin_dir_url( __FILE__ ) . 'assets/header.css',
		array(),
		'0.2.60.133.15'
	);
	wp_enqueue_script(
		'dimitrium-frosted-header',
		plugin_dir_url( __FILE__ ) . 'assets/header.js',
		array(),
		'0.2.60.133.11',
		array( 'in_footer' => false, 'strategy' => 'defer' )
	);
}
add_action( 'wp_enqueue_scripts', 'dimitrium_now_playing_enqueue_header_style' );

function dimitrium_now_playing_enqueue_404_style() {
	if ( ! is_404() ) {
		return;
	}
	wp_enqueue_style(
		'dimitrium-404',
		plugin_dir_url( __FILE__ ) . 'assets/404.css',
		array( 'dimitrium-frosted-header' ),
		'0.2.60.133.6'
	);
}
add_action( 'wp_enqueue_scripts', 'dimitrium_now_playing_enqueue_404_style' );

/**
 * Homepage image performance: preserve PNG uploads as fallbacks while serving
 * purpose-made WebP derivatives to browsers that support them.
 */
function dimitrium_home_performance_is_homepage() {
	return is_page( array( 122, 123 ) );
}

function dimitrium_home_performance_webp_url( $attachment_id, $width ) {
	$file = get_attached_file( $attachment_id );
	$url  = wp_get_attachment_url( $attachment_id );
	if ( ! $file || ! $url ) {
		return '';
	}
	$path = dirname( $file ) . '/' . pathinfo( $file, PATHINFO_FILENAME ) . '-perf-' . (int) $width . '.webp';
	if ( ! file_exists( $path ) ) {
		return '';
	}
	return dirname( $url ) . '/' . pathinfo( $url, PATHINFO_FILENAME ) . '-perf-' . (int) $width . '.webp';
}

function dimitrium_home_performance_preload_hero() {
	if ( ! dimitrium_home_performance_is_homepage() ) {
		return;
	}
	$small = dimitrium_home_performance_webp_url( 345, 768 );
	$large = dimitrium_home_performance_webp_url( 345, 1448 );
	if ( $small ) {
		echo '<link rel="preload" as="image" href="' . esc_url( $small ) . '" type="image/webp" media="(max-width: 899px)">' . "\n";
	}
	if ( $large ) {
		echo '<link rel="preload" as="image" href="' . esc_url( $large ) . '" type="image/webp" media="(min-width: 900px)">' . "\n";
	}
}
add_action( 'wp_head', 'dimitrium_home_performance_preload_hero', 1 );

function dimitrium_home_performance_picture_sources( $attachment_id, array $widths ) {
	$parts = array();
	foreach ( $widths as $width ) {
		$url = dimitrium_home_performance_webp_url( $attachment_id, $width );
		if ( $url ) {
			$parts[] = esc_url( $url ) . ' ' . (int) $width . 'w';
		}
	}
	return implode( ', ', $parts );
}

function dimitrium_home_performance_content_images( $content ) {
	if ( ! dimitrium_home_performance_is_homepage() || ! in_the_loop() || ! is_main_query() ) {
		return $content;
	}
	$images = array(
		240 => array( 'widths' => array( 300, 768, 1024 ), 'sizes' => '(max-width: 700px) calc(100vw - 48px), 50vw', 'priority' => true, 'alt' => '' ),
		276 => array( 'widths' => array( 300, 480, 768 ), 'sizes' => '(max-width: 700px) calc(100vw - 48px), 380px', 'priority' => false, 'alt' => array( 'en' => 'AI-generated portrait of Aleksa Dimitrijević', 'sr' => 'AI-generisani portret Alekse Dimitrijevića' ) ),
		283 => array( 'widths' => array( 300, 480, 768 ), 'sizes' => '(max-width: 700px) calc(100vw - 48px), 380px', 'priority' => false, 'alt' => array( 'en' => 'AI-generated portrait of Nala the cat', 'sr' => 'AI-generisani portret mačke Nale' ) ),
		324 => array( 'widths' => array( 300, 480, 768 ), 'sizes' => '(max-width: 700px) calc(100vw - 48px), 380px', 'priority' => false, 'alt' => array( 'en' => 'AI-generated WordPress illustration', 'sr' => 'AI-generisana ilustracija WordPressa' ) ),
		308 => array( 'widths' => array( 300, 480, 768 ), 'sizes' => '(max-width: 700px) calc(100vw - 48px), 380px', 'priority' => false, 'alt' => array( 'en' => 'AI-generated illustration of the Venus and Pluto servers', 'sr' => 'AI-generisana ilustracija servera Venera i Pluton' ) ),
	);
	return preg_replace_callback( '/<img\\b(?=[^>]*\\bwp-image-(' . implode( '|', array_keys( $images ) ) . ')\\b)[^>]*>/i', static function ( $match ) use ( $images ) {
		preg_match( '/\\bwp-image-(' . implode( '|', array_keys( $images ) ) . ')\\b/i', $match[0], $id_match );
		$id = isset( $id_match[1] ) ? (int) $id_match[1] : 0;
		if ( ! $id ) {
			return $match[0];
		}
		$source = dimitrium_home_performance_picture_sources( $id, $images[ $id ]['widths'] );
		if ( ! $source ) {
			return $match[0];
		}
		$image = $match[0];
		$language = function_exists( 'pll_current_language' ) ? pll_current_language() : 'en';
		$alt = isset( $images[ $id ]['alt'] ) && is_array( $images[ $id ]['alt'] ) ? ( $images[ $id ]['alt'][ $language ] ?? '' ) : '';
		if ( '' !== $alt ) {
			$image = preg_replace( '/\\salt=("|\\\')[^"\\\']*\\1/i', ' alt="' . esc_attr( $alt ) . '"', $image, 1 );
		}
		if ( ! $images[ $id ]['priority'] ) {
			$image = preg_replace( '/\\sfetchpriority=("|\\\')[^"\\\']*\\1/i', '', $image );
			if ( false === stripos( $image, ' loading=' ) ) {
				$image = preg_replace( '/<img\\b/i', '<img loading="lazy"', $image, 1 );
			}
		}
		return '<picture><source type="image/webp" srcset="' . esc_attr( $source ) . '" sizes="' . esc_attr( $images[ $id ]['sizes'] ) . '">' . $image . '</picture>';
	}, $content );
}
// Generic WebP delivery below now covers these images and every other image block.

/**
 * Core's Page List block currently renders a second <ul> directly inside a
 * Navigation block's list. Move its list items into the navigation list so
 * assistive technology receives one valid list, while keeping Polylang's
 * language-aware Page List output intact.
 */
function dimitrium_accessibility_flatten_navigation_page_list( $content ) {
	if ( false === strpos( $content, 'wp-block-navigation__container' ) || false === strpos( $content, 'wp-block-page-list' ) || ! class_exists( 'DOMDocument' ) ) {
		return $content;
	}

	$previous_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument( '1.0', 'UTF-8' );
	$loaded          = $document->loadHTML( '<?xml encoding="UTF-8"><div id="dimitrium-navigation-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous_errors );

	if ( ! $loaded ) {
		return $content;
	}

	$xpath = new DOMXPath( $document );
	$lists = $xpath->query( '//ul[contains(concat(" ", normalize-space(@class), " "), " wp-block-navigation__container ")]/ul[contains(concat(" ", normalize-space(@class), " "), " wp-block-page-list ")]' );
	if ( $lists ) {
		foreach ( $lists as $page_list ) {
			$parent = $page_list->parentNode;
			while ( $page_list->firstChild ) {
				$parent->insertBefore( $page_list->firstChild, $page_list );
			}
			$parent->removeChild( $page_list );
		}
	}

	// Unendlich is reachable from the Music page and home hero, not the primary menu.
	$album_links = $xpath->query( '//a[contains(@href, "/muzika/unendlich/") or contains(@href, "/musique/unendlich/")]' );
	if ( $album_links ) {
		foreach ( $album_links as $album_link ) {
			$item = $album_link;
			while ( $item && ( ! $item instanceof DOMElement || 'li' !== $item->tagName ) ) {
				$item = $item->parentNode;
			}
			if ( ! $item || ! $item->parentNode ) {
				continue;
			}
			$item->parentNode->removeChild( $item );
		}
	}

	$empty_submenus = $xpath->query( '//ul[contains(concat(" ", normalize-space(@class), " "), " wp-block-navigation__submenu-container ") and not(.//li)]' );
	if ( $empty_submenus ) {
		foreach ( $empty_submenus as $submenu ) {
			$parent_item = $submenu->parentNode;
			if ( ! $parent_item instanceof DOMElement ) {
				continue;
			}
			$buttons = $xpath->query( './button[contains(concat(" ", normalize-space(@class), " "), " wp-block-navigation-submenu__toggle ")]', $parent_item );
			foreach ( $buttons as $button ) {
				$parent_item->removeChild( $button );
			}
			$classes = preg_split( '/\s+/', trim( $parent_item->getAttribute( 'class' ) ) );
			$parent_item->setAttribute( 'class', implode( ' ', array_diff( $classes, array( 'has-child' ) ) ) );
			foreach ( array( 'data-wp-context', 'data-wp-interactive', 'data-wp-on--focusout', 'data-wp-on--keydown', 'data-wp-on--pointerenter', 'data-wp-on--pointerleave', 'data-wp-watch', 'tabindex' ) as $attribute ) {
				$parent_item->removeAttribute( $attribute );
			}
			$parent_item->removeChild( $submenu );
		}
	}

	// Keep the same information architecture in both Polylang languages and views.
	$order = array(
		'dimipedia'    => 10,
		'filozofija'   => 20,
		'philosophy'   => 20,
		'fleks'        => 30,
		'flex'         => 30,
		'muzika'       => 40,
		'musique'      => 40,
		'softver'      => 50,
		'software'     => 50,
		'noir-licenca' => 60,
		'noir-license' => 60,
		'macka'        => 70,
		'cat'          => 70,
		'kompanija'    => 80,
		'company'      => 80,
	);
	$navigation_lists = $xpath->query( '//ul[contains(concat(" ", normalize-space(@class), " "), " wp-block-navigation__container ")]' );
	if ( $navigation_lists ) {
		foreach ( $navigation_lists as $navigation_list ) {
			$items = array();
			$index = 0;
			foreach ( $navigation_list->childNodes as $item ) {
				if ( ! $item instanceof DOMElement || 'li' !== $item->tagName ) {
					continue;
				}
				$link = $xpath->query( './a', $item )->item( 0 );
				$path = $link ? trim( (string) wp_parse_url( $link->getAttribute( 'href' ), PHP_URL_PATH ), '/' ) : '';
				$slug = $path ? basename( $path ) : '';
				$items[] = array(
					'node'   => $item,
					'weight' => $order[ $slug ] ?? 1000,
					'index'  => $index++,
				);
			}
			usort( $items, static function ( $left, $right ) {
				return $left['weight'] === $right['weight'] ? $left['index'] <=> $right['index'] : $left['weight'] <=> $right['weight'];
			} );
			foreach ( $items as $item ) {
				$navigation_list->appendChild( $item['node'] );
			}
		}
	}

	$root   = $document->getElementById( 'dimitrium-navigation-root' );
	$output = '';
	foreach ( $root->childNodes as $node ) {
		$output .= $document->saveHTML( $node );
	}

	return $output;
}
add_filter( 'render_block_core/navigation', 'dimitrium_accessibility_flatten_navigation_page_list', 20 );

/** Return a local upload's WebP sibling only when it has been generated. */
function dimitrium_webp_sibling_url( $url ) {
	static $uploads = null;
	if ( null === $uploads ) {
		$uploads = wp_upload_dir();
	}

	$path     = wp_parse_url( html_entity_decode( $url ), PHP_URL_PATH );
	$base_url = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
	if ( ! $path || ! $base_url || 0 !== strpos( $path, $base_url . '/' ) || ! preg_match( '/\.(?:png|jpe?g)$/i', $path ) ) {
		return '';
	}

	$relative = ltrim( substr( $path, strlen( $base_url ) ), '/' );
	$webp      = preg_replace( '/\.(?:png|jpe?g)$/i', '.webp', $relative );
	$webp_path = $webp ? trailingslashit( $uploads['basedir'] ) . $webp : '';
	if ( ! $webp || ! is_readable( $webp_path ) ) {
		return '';
	}

	return trailingslashit( $uploads['baseurl'] ) . str_replace( '%2F', '/', rawurlencode( $webp ) ) . '?ver=' . filemtime( $webp_path );
}

function dimitrium_webp_srcset( $srcset ) {
	$items   = preg_split( '/\s*,\s*/', $srcset );
	$webp    = array();
	$changed = false;
	foreach ( $items as $item ) {
		$parts = preg_split( '/\s+/', trim( $item ), 2 );
		$url   = dimitrium_webp_sibling_url( $parts[0] ?? '' );
		if ( ! $url ) {
			continue;
		}
		$webp[]  = $url . ( isset( $parts[1] ) ? ' ' . $parts[1] : '' );
		$changed = true;
	}

	return $changed ? implode( ', ', $webp ) : '';
}

/**
 * Add a WebP <source> to content images globally. The image element remains
 * unchanged as a fallback and existing <picture> markup is left untouched.
 */
function dimitrium_webp_content_images( $content ) {
	if ( is_admin() || false === stripos( $content, '<img' ) || ! class_exists( 'DOMDocument' ) ) {
		return $content;
	}

	$previous_errors = libxml_use_internal_errors( true );
	$document        = new DOMDocument( '1.0', 'UTF-8' );
	$loaded          = $document->loadHTML( '<?xml encoding="UTF-8"><div id="dimitrium-webp-root">' . $content . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $previous_errors );
	if ( ! $loaded ) {
		return $content;
	}

	$xpath  = new DOMXPath( $document );
	$images = $xpath->query( '//img[not(ancestor::picture)]' );
	foreach ( $images as $image ) {
		$src = dimitrium_webp_sibling_url( $image->getAttribute( 'src' ) );
		if ( ! $src ) {
			continue;
		}

		$srcset = $image->hasAttribute( 'srcset' ) ? dimitrium_webp_srcset( $image->getAttribute( 'srcset' ) ) : '';
		$source = $document->createElement( 'source' );
		$source->setAttribute( 'type', 'image/webp' );
		$source->setAttribute( 'srcset', $srcset ?: $src );
		if ( $image->hasAttribute( 'sizes' ) ) {
			$source->setAttribute( 'sizes', $image->getAttribute( 'sizes' ) );
		}

		$picture = $document->createElement( 'picture' );
		$image->parentNode->replaceChild( $picture, $image );
		$picture->appendChild( $source );
		$picture->appendChild( $image );
	}

	$root   = $document->getElementById( 'dimitrium-webp-root' );
	$output = '';
	foreach ( $root->childNodes as $node ) {
		$output .= $document->saveHTML( $node );
	}

	return $output;
}
add_filter( 'the_content', 'dimitrium_webp_content_images', 30 );
add_filter( 'post_thumbnail_html', 'dimitrium_webp_content_images', 30 );
add_filter( 'render_block_core/image', 'dimitrium_webp_content_images', 30 );
add_filter( 'render_block_core/shortcode', 'dimitrium_webp_content_images', 30 );

function dimitrium_now_playing_enqueue_assets() {
	if ( ! dimitrium_now_playing_is_music_page() ) {
		return;
	}

	$version = '0.2.60.133.5';
	$base    = plugin_dir_url( __FILE__ );

	wp_enqueue_style(
		'dimitrium-now-playing',
		$base . 'assets/now-playing.css',
		array(),
		$version
	);

	wp_enqueue_script(
		'dimitrium-now-playing',
		$base . 'assets/now-playing.js',
		array(),
		$version,
		array( 'in_footer' => false, 'strategy' => 'defer' )
	);

	$back_covers = array();
	$audio_files = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'audio',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
		)
	);
	foreach ( $audio_files as $audio_file ) {
		$cover_id = (int) get_post_meta( $audio_file->ID, '_dimitrium_back_cover_id', true );
		if ( $cover_id ) {
			$back_covers[ wp_get_attachment_url( $audio_file->ID ) ] = wp_get_attachment_image_url( $cover_id, 'full' );
		}
	}
	wp_add_inline_script(
		'dimitrium-now-playing',
		'window.dimitriumBackCovers = ' . wp_json_encode( $back_covers ) . ';',
		'before'
	);
}
add_action( 'wp_enqueue_scripts', 'dimitrium_now_playing_enqueue_assets' );

function dimitrium_now_playing_admin_menu() {
	add_media_page(
		'Music Back Covers',
		'Music Back Covers',
		'upload_files',
		'dimitrium-music-back-covers',
		'dimitrium_now_playing_back_covers_page'
	);
}
add_action( 'admin_menu', 'dimitrium_now_playing_admin_menu' );

function dimitrium_now_playing_save_back_covers() {
	if ( empty( $_POST['dimitrium_back_covers_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dimitrium_back_covers_nonce'] ) ), 'dimitrium_save_back_covers' ) ) {
		return;
	}
	if ( ! current_user_can( 'upload_files' ) ) {
		wp_die( esc_html__( 'You are not allowed to edit these settings.' ) );
	}

	$covers = isset( $_POST['back_covers'] ) && is_array( $_POST['back_covers'] ) ? wp_unslash( $_POST['back_covers'] ) : array();
	foreach ( $covers as $audio_id => $cover_id ) {
		$audio_id = absint( $audio_id );
		$cover_id = absint( $cover_id );
		if ( ! $audio_id || 0 !== strpos( (string) get_post_mime_type( $audio_id ), 'audio/' ) ) {
			continue;
		}
		if ( $cover_id ) {
			update_post_meta( $audio_id, '_dimitrium_back_cover_id', $cover_id );
		} else {
			delete_post_meta( $audio_id, '_dimitrium_back_cover_id' );
		}
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'dimitrium-music-back-covers', 'updated' => '1' ), admin_url( 'upload.php' ) ) );
	exit;
}
add_action( 'admin_init', 'dimitrium_now_playing_save_back_covers' );

function dimitrium_now_playing_back_covers_page() {
	if ( ! current_user_can( 'upload_files' ) ) {
		return;
	}
	wp_enqueue_media();
	$audio_files = get_posts(
		array(
			'post_type'      => 'attachment',
			'post_mime_type' => 'audio',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'orderby'        => 'title',
			'order'          => 'ASC',
		)
	);
	?>
	<div class="wrap">
		<h1>Music Back Covers</h1>
		<p>Dodeli posebnu poleđinu omota svakom audio fajlu. Slika se prikazuje kada okreneš cover u full-screen playeru.</p>
		<?php if ( isset( $_GET['updated'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Back cover podešavanja su sačuvana.</p></div>
		<?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'dimitrium_save_back_covers', 'dimitrium_back_covers_nonce' ); ?>
			<table class="widefat striped" style="max-width:900px">
				<thead><tr><th>Traka</th><th>Back cover</th><th>Izbor</th></tr></thead>
				<tbody>
				<?php foreach ( $audio_files as $audio_file ) :
					$cover_id  = (int) get_post_meta( $audio_file->ID, '_dimitrium_back_cover_id', true );
					$cover_url = $cover_id ? wp_get_attachment_image_url( $cover_id, 'thumbnail' ) : '';
				?>
					<tr class="dimitrium-cover-row">
						<td><strong><?php echo esc_html( get_the_title( $audio_file ) ); ?></strong><br><code><?php echo esc_html( wp_basename( get_attached_file( $audio_file->ID ) ) ); ?></code></td>
						<td><img class="dimitrium-cover-preview" src="<?php echo esc_url( $cover_url ); ?>" alt="" style="width:80px;height:80px;object-fit:cover;<?php echo $cover_url ? '' : 'display:none;'; ?>"></td>
						<td>
							<input class="dimitrium-cover-id" type="hidden" name="back_covers[<?php echo (int) $audio_file->ID; ?>]" value="<?php echo (int) $cover_id; ?>">
							<button type="button" class="button dimitrium-choose-cover">Izaberi sliku</button>
							<button type="button" class="button-link-delete dimitrium-remove-cover" <?php echo $cover_url ? '' : 'hidden'; ?>>Ukloni</button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( 'Sačuvaj back covere' ); ?>
		</form>
	</div>
	<script>
	document.addEventListener('click', function (event) {
		const choose = event.target.closest('.dimitrium-choose-cover');
		const remove = event.target.closest('.dimitrium-remove-cover');
		if (choose) {
			const row = choose.closest('.dimitrium-cover-row');
			const frame = wp.media({ title: 'Izaberi back cover', button: { text: 'Koristi ovu sliku' }, library: { type: 'image' }, multiple: false });
			frame.on('select', function () {
				const image = frame.state().get('selection').first().toJSON();
				row.querySelector('.dimitrium-cover-id').value = image.id;
				const preview = row.querySelector('.dimitrium-cover-preview');
				preview.src = image.sizes?.thumbnail?.url || image.url;
				preview.style.display = '';
				row.querySelector('.dimitrium-remove-cover').hidden = false;
			});
			frame.open();
		}
		if (remove) {
			const row = remove.closest('.dimitrium-cover-row');
			row.querySelector('.dimitrium-cover-id').value = '';
			row.querySelector('.dimitrium-cover-preview').style.display = 'none';
			remove.hidden = true;
		}
	});
	</script>
	<?php
}

function dimitrium_now_playing_render() {
	if ( ! dimitrium_now_playing_is_music_page() ) {
		return;
	}

	$lang   = function_exists( 'pll_current_language' ) ? pll_current_language() : 'en';
	$labels = 'sr' === $lang
		? array(
			'previous' => 'Prethodna pesma',
			'play'     => 'Pusti',
			'pause'    => 'Pauziraj',
			'next'     => 'Sledeća pesma',
			'seek'     => 'Pozicija u pesmi',
			'unknown'  => 'Nepoznata pesma',
			'expand'   => 'Prikaži preko celog ekrana',
			'minimize' => 'Umanji player',
			'full'     => 'Pesma koja se trenutno reprodukuje',
			'details'  => 'Prikaži informacije o albumu',
			'year'     => 'GODINA',
			'tracks'   => 'PESME',
			'length'   => 'TRAJANJE',
			'download' => 'Preuzmi pesmu',
			'reward_title' => 'Čestitam!',
			'reward_copy'  => 'Odslušao si celu jednu moju pesmu. Otključao si sliku koja je dostupna samo tebi.',
			'reward_close' => 'Zatvori nagradu',
			'reward_image' => 'Otključana nagradna slika',
			'reward_admin' => 'Prikaži skrivenu nagradu',
			'reward_reopen' => 'Prikaži otključanu nagradu',
		)
		: array(
			'previous' => 'Previous track',
			'play'     => 'Play',
			'pause'    => 'Pause',
			'next'     => 'Next track',
			'seek'     => 'Track position',
			'unknown'  => 'Unknown track',
			'expand'   => 'Open full-screen player',
			'minimize' => 'Minimize player',
			'full'     => 'Now playing',
			'details'  => 'Show album details',
			'year'     => 'YEAR',
			'tracks'   => 'TRACKS',
			'length'   => 'DURATION',
			'download' => 'Download track',
			'reward_title' => 'Congratulations!',
			'reward_copy'  => 'You listened to one entire song of mine. You unlocked an image available only to you.',
			'reward_close' => 'Close reward',
			'reward_image' => 'Unlocked reward image',
			'reward_admin' => 'Show hidden reward',
			'reward_reopen' => 'Show unlocked reward',
		);
	?>
	<div class="dimitrium-now-playing" data-dimitrium-now-playing hidden
		data-label-play="<?php echo esc_attr( $labels['play'] ); ?>"
		data-label-pause="<?php echo esc_attr( $labels['pause'] ); ?>"
		data-label-unknown="<?php echo esc_attr( $labels['unknown'] ); ?>">
		<div class="dimitrium-now-playing__frost" aria-hidden="true"></div>
		<div class="dimitrium-now-playing__inner">
			<div class="dimitrium-now-playing__track">
				<img class="dimitrium-now-playing__artwork" alt="" width="64" height="64">
				<div class="dimitrium-now-playing__info">
					<strong class="dimitrium-now-playing__title"><?php echo esc_html( $labels['unknown'] ); ?></strong>
					<span class="dimitrium-now-playing__artist"></span>
				</div>
			</div>

			<div class="dimitrium-now-playing__transport">
				<div class="dimitrium-now-playing__buttons">
					<button type="button" class="dimitrium-now-playing__button" data-action="previous" aria-label="<?php echo esc_attr( $labels['previous'] ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19,6.618V17.382a2.591,2.591,0,0,1-4.192,2.057L7.993,14.056a2.654,2.654,0,0,1-.243-.243V20a.75.75,0,0,1-1.5,0V4a.75.75,0,0,1,1.5,0v6.186a2.654,2.654,0,0,1,.243-.243l6.816-5.382A2.591,2.591,0,0,1,19,6.618Z"/></svg>
					</button>
					<button type="button" class="dimitrium-now-playing__button dimitrium-now-playing__button--play" data-action="toggle" aria-label="<?php echo esc_attr( $labels['play'] ); ?>">
						<svg class="dimitrium-now-playing__play-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.661,14.386,8.584,20.552A3.01,3.01,0,0,1,4,17.994V6.006A3.01,3.01,0,0,1,8.584,3.448L18.661,9.614A2.8,2.8,0,0,1,18.661,14.386Z"/></svg>
						<svg class="dimitrium-now-playing__pause-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5,19.5V4.5A1.326,1.326,0,0,1,6.5,3h2A1.326,1.326,0,0,1,10,4.5v15A1.326,1.326,0,0,1,8.5,21h-2A1.326,1.326,0,0,1,5,19.5ZM15.5,21h2A1.326,1.326,0,0,0,19,19.5V4.5A1.326,1.326,0,0,0,17.5,3h-2A1.326,1.326,0,0,0,14,4.5v15A1.326,1.326,0,0,0,15.5,21Z"/></svg>
					</button>
					<button type="button" class="dimitrium-now-playing__button" data-action="next" aria-label="<?php echo esc_attr( $labels['next'] ); ?>">
						<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.75,4V20a.75.75,0,0,1-1.5,0V13.813a2.654,2.654,0,0,1-.243.243L9.192,19.439A2.591,2.591,0,0,1,5,17.382V6.618A2.591,2.591,0,0,1,9.191,4.561l6.816,5.382a2.654,2.654,0,0,1,.243.243V4a.75.75,0,0,1,1.5,0Z"/></svg>
					</button>
					<button type="button" class="dimitrium-now-playing__button" data-action="expand" aria-label="<?php echo esc_attr( $labels['expand'] ); ?>">
						<svg class="dimitrium-corner-icon" viewBox="0 0 24 24" aria-hidden="true"><path data-corner d="M2 8V4a2 2 0 0 1 2-2h4v2H4v4Z"/><path data-corner d="M16 2h4a2 2 0 0 1 2 2v4h-2V4h-4Z"/><path data-corner d="M2 16h2v4h4v2H4a2 2 0 0 1-2-2Z"/><path data-corner d="M20 16h2v4a2 2 0 0 1-2 2h-4v-2h4Z"/></svg>
					</button>
				</div>
				<div class="dimitrium-now-playing__timeline">
					<time class="dimitrium-now-playing__current">00:00</time>
					<input class="dimitrium-now-playing__seek" type="range" min="0" max="1000" value="0" step="1" aria-label="<?php echo esc_attr( $labels['seek'] ); ?>">
					<time class="dimitrium-now-playing__duration">00:00</time>
				</div>
			</div>
		</div>
	</div>

	<div class="dimitrium-full-player" data-dimitrium-full-player hidden role="dialog" aria-modal="true" aria-label="<?php echo esc_attr( $labels['full'] ); ?>">
		<button type="button" class="dimitrium-full-player__close" data-full-action="close" aria-label="<?php echo esc_attr( $labels['minimize'] ); ?>">
			<svg class="dimitrium-corner-icon dimitrium-corner-icon--minimize" viewBox="0 0 24 24" aria-hidden="true"><path data-corner d="M2 8V4a2 2 0 0 1 2-2h4v2H4v4Z"/><path data-corner d="M16 2h4a2 2 0 0 1 2 2v4h-2V4h-4Z"/><path data-corner d="M2 16h2v4h4v2H4a2 2 0 0 1-2-2Z"/><path data-corner d="M20 16h2v4a2 2 0 0 1-2 2h-4v-2h4Z"/></svg>
		</button>
		<div class="dimitrium-full-player__inner">
			<button type="button" class="dimitrium-full-player__artwork-wrap" aria-pressed="false" aria-label="<?php echo esc_attr( $labels['details'] ); ?>">
				<span class="dimitrium-full-player__artwork-card">
					<img class="dimitrium-full-player__artwork" alt="">
					<span class="dimitrium-full-player__album-back" aria-hidden="true">
						<img class="dimitrium-full-player__album-back-art" alt="">
						<span class="dimitrium-full-player__album-back-content">
							<span class="dimitrium-full-player__album-heading">
								<strong class="dimitrium-full-player__album-title"></strong>
								<span class="dimitrium-full-player__album-artist"></span>
							</span>
							<canvas class="dimitrium-full-player__album-waveform" aria-hidden="true"></canvas>
							<span class="dimitrium-full-player__album-stats">
								<span><small><?php echo esc_html( $labels['year'] ); ?></small><b>2026</b></span>
								<span><small><?php echo esc_html( $labels['tracks'] ); ?></small><b class="dimitrium-full-player__album-tracks">0</b></span>
								<span><small><?php echo esc_html( $labels['length'] ); ?></small><b class="dimitrium-full-player__album-duration">0:00</b></span>
							</span>
						</span>
					</span>
				</span>
			</button>
			<div class="dimitrium-full-player__info">
				<strong class="dimitrium-full-player__title"><?php echo esc_html( $labels['unknown'] ); ?></strong>
				<span class="dimitrium-full-player__artist"></span>
			</div>
			<a class="dimitrium-now-playing__button dimitrium-full-player__download" data-full-download href="#" download aria-label="<?php echo esc_attr( $labels['download'] ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.312,10.709A.989.989,0,0,1,5.992,9H8.415V4a1,1,0,0,1,1-1h6a1,1,0,0,1,1,1V9h2.593a.989.989,0,0,1,.68,1.709l-6.275,5.928a1.33,1.33,0,0,1-1.826,0ZM19.5,20.25H5.5a.75.75,0,0,0,0-1.5h14a.75.75,0,0,0,0-1.5Z"/></svg>
			</a>
			<div class="dimitrium-full-player__buttons">
				<button type="button" class="dimitrium-now-playing__button" data-full-action="previous" aria-label="<?php echo esc_attr( $labels['previous'] ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19,6.618V17.382a2.591,2.591,0,0,1-4.192,2.057L7.993,14.056a2.654,2.654,0,0,1-.243-.243V20a.75.75,0,0,1-1.5,0V4a.75.75,0,0,1,1.5,0v6.186a2.654,2.654,0,0,1,.243-.243l6.816-5.382A2.591,2.591,0,0,1,19,6.618Z"/></svg></button>
				<button type="button" class="dimitrium-now-playing__button dimitrium-now-playing__button--play" data-full-action="toggle" aria-label="<?php echo esc_attr( $labels['play'] ); ?>"><svg class="dimitrium-now-playing__play-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M18.661,14.386,8.584,20.552A3.01,3.01,0,0,1,4,17.994V6.006A3.01,3.01,0,0,1,8.584,3.448L18.661,9.614A2.8,2.8,0,0,1,18.661,14.386Z"/></svg><svg class="dimitrium-now-playing__pause-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5,19.5V4.5A1.326,1.326,0,0,1,6.5,3h2A1.326,1.326,0,0,1,10,4.5v15A1.326,1.326,0,0,1,8.5,21h-2A1.326,1.326,0,0,1,5,19.5ZM15.5,21h2A1.326,1.326,0,0,0,19,19.5V4.5A1.326,1.326,0,0,0,17.5,3h-2A1.326,1.326,0,0,0,14,4.5v15A1.326,1.326,0,0,0,15.5,21Z"/></svg></button>
				<button type="button" class="dimitrium-now-playing__button" data-full-action="next" aria-label="<?php echo esc_attr( $labels['next'] ); ?>"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.75,4V20a.75.75,0,0,1-1.5,0V13.813a2.654,2.654,0,0,1-.243.243L9.192,19.439A2.591,2.591,0,0,1,5,17.382V6.618A2.591,2.591,0,0,1,9.191,4.561l6.816,5.382a2.654,2.654,0,0,1,.243.243V4a.75.75,0,0,1,1.5,0Z"/></svg></button>
			</div>
			<div class="dimitrium-full-player__timeline">
				<input class="dimitrium-now-playing__seek dimitrium-full-player__seek" type="range" min="0" max="1000" value="0" step="1" aria-label="<?php echo esc_attr( $labels['seek'] ); ?>">
				<time class="dimitrium-full-player__current">00:00</time>
				<time class="dimitrium-full-player__duration">00:00</time>
			</div>
		</div>
	</div>

	<div class="dimitrium-listening-reward" data-dimitrium-listening-reward hidden role="dialog" aria-modal="true" aria-labelledby="dimitrium-listening-reward-title">
		<div class="dimitrium-listening-reward__panel">
			<button type="button" class="dimitrium-listening-reward__close" data-reward-close aria-label="<?php echo esc_attr( $labels['reward_close'] ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5.293 5.293a1 1 0 0 1 1.414 0L12 10.586l5.293-5.293a1 1 0 1 1 1.414 1.414L13.414 12l5.293 5.293a1 1 0 0 1-1.414 1.414L12 13.414l-5.293 5.293a1 1 0 0 1-1.414-1.414L10.586 12 5.293 6.707a1 1 0 0 1 0-1.414Z"/></svg>
			</button>
			<h2 id="dimitrium-listening-reward-title" class="dimitrium-listening-reward__title"><?php echo esc_html( $labels['reward_title'] ); ?></h2>
			<p class="dimitrium-listening-reward__copy"><?php echo esc_html( $labels['reward_copy'] ); ?></p>
			<figure class="wp-block-image size-large dimitrium-listening-reward__image">
				<a href="https://dimitrium.org/wp-content/uploads/2026/09/ChatGPT-Image-Sep-7-2026-05_18_05-AM.png" data-lbwps-gid="dimitrium-listening-reward" data-lbwps-width="1086" data-lbwps-height="1448" data-lbwps-title="<?php echo esc_attr( $labels['reward_image'] ); ?>">
					<img data-dimitrium-reward-image data-src="https://dimitrium.org/wp-content/uploads/2026/09/ChatGPT-Image-Sep-7-2026-05_18_05-AM-perf-768.webp" width="1086" height="1448" alt="<?php echo esc_attr( $labels['reward_image'] ); ?>">
				</a>
			</figure>
		</div>
	</div>
	<?php $is_reward_admin = is_user_logged_in() && current_user_can( 'manage_options' ); ?>
		<button type="button" class="dimitrium-admin-reward-trigger" data-reward-trigger<?php echo $is_reward_admin ? ' data-admin-reward-trigger' : ' hidden'; ?> aria-label="<?php echo esc_attr( $is_reward_admin ? $labels['reward_admin'] : $labels['reward_reopen'] ); ?>" title="<?php echo esc_attr( $is_reward_admin ? $labels['reward_admin'] : $labels['reward_reopen'] ); ?>">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2.75a4.25 4.25 0 0 1 4.244 4.03l.006.22v1.25H18A2.75 2.75 0 0 1 20.75 11v7A2.75 2.75 0 0 1 18 20.75H6A2.75 2.75 0 0 1 3.25 18v-7A2.75 2.75 0 0 1 6 8.25h1.75V7A4.25 4.25 0 0 1 12 2.75Zm6 7H6c-.69 0-1.25.56-1.25 1.25v7c0 .69.56 1.25 1.25 1.25h12c.69 0 1.25-.56 1.25-1.25v-7c0-.69-.56-1.25-1.25-1.25ZM12 12a2 2 0 0 1 .75 3.854V17a.75.75 0 0 1-1.5 0v-1.146A2 2 0 0 1 12 12Zm0-7.75A2.75 2.75 0 0 0 9.25 7v1.25h5.5V7A2.75 2.75 0 0 0 12 4.25Z"/></svg>
		</button>
	<?php
}
add_action( 'wp_footer', 'dimitrium_now_playing_render', 20 );
