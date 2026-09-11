<?php
/**
 * Plugin Name: Dimipedia
 * Description: Encyclopaedia entries, multilingual categories and shared translated slugs for Dimitrium.
 * Version: 0.1.53
 * Author: Dimitrium
 * License: AGPL-3.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIMIPEDIA_VERSION', '0.1.53' );

function dimipedia_register_content() {
	register_post_type(
		'dimipedia_entry',
		array(
			'labels' => array(
				'name'          => 'Dimipedia Entries',
				'singular_name' => 'Dimipedia Entry',
				'menu_name'     => 'Dimipedia',
				'add_new_item'  => 'Add Dimipedia Entry',
				'edit_item'     => 'Edit Dimipedia Entry',
			),
			'public'              => true,
			'show_in_rest'        => true,
			'menu_icon'           => 'dashicons-book-alt',
			'has_archive'         => false,
			'rewrite'             => array( 'slug' => 'dimipedia', 'with_front' => false ),
			'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author', 'custom-fields' ),
			'taxonomies'          => array( 'dimipedia_category' ),
			'publicly_queryable'  => true,
			'exclude_from_search' => false,
		)
	);

	register_taxonomy(
		'dimipedia_category',
		array( 'dimipedia_entry' ),
		array(
			'labels' => array(
				'name'          => 'Dimipedia Categories',
				'singular_name' => 'Dimipedia Category',
				'menu_name'     => 'Categories',
			),
			'public'       => true,
			'hierarchical' => true,
			'show_in_rest' => true,
			'rewrite'      => array( 'slug' => 'dimipedia/category', 'with_front' => false ),
		)
	);
}
add_action( 'init', 'dimipedia_register_content' );

/**
 * The Dimipedia landing page is a regular Page at /{lang}/dimipedia/.
 * Add the article rule explicitly and before WordPress' generic attachment
 * rules, which would otherwise claim the final URL segment.
 */
function dimipedia_add_language_rewrite_rules() {
	$languages = function_exists( 'pll_languages_list' ) ? pll_languages_list() : array( 'en', 'sr' );
	$languages = array_filter( array_map( 'sanitize_key', $languages ) );
	if ( empty( $languages ) ) {
		return;
	}
	add_rewrite_rule(
		'^(' . implode( '|', array_map( 'preg_quote', $languages ) ) . ')/dimipedia/([^/]+)/?$',
		'index.php?dimipedia_entry=$matches[2]&lang=$matches[1]',
		'top'
	);
}
add_action( 'init', 'dimipedia_add_language_rewrite_rules', 20 );

add_filter( 'pll_get_post_types', function ( $post_types ) {
	$post_types['dimipedia_entry'] = 'dimipedia_entry';
	return $post_types;
} );

add_filter( 'pll_get_taxonomies', function ( $taxonomies ) {
	$taxonomies['dimipedia_category'] = 'dimipedia_category';
	return $taxonomies;
} );

function dimipedia_seed_categories() {
	if ( ! function_exists( 'pll_set_term_language' ) || DIMIPEDIA_VERSION === get_option( 'dimipedia_seed_version' ) ) {
		return;
	}

	$categories = array(
		'people-identities' => array( 'en' => 'People & Identities', 'sr' => 'Ljudi i identiteti' ),
		'music'             => array( 'en' => 'Music', 'sr' => 'Muzika' ),
		'projects-software' => array( 'en' => 'Projects & Software', 'sr' => 'Projekti i softver' ),
		'infrastructure'    => array( 'en' => 'Infrastructure', 'sr' => 'Infrastruktura' ),
		'organizations'     => array( 'en' => 'Organizations', 'sr' => 'Organizacije' ),
		'nala'              => array( 'en' => 'Nala', 'sr' => 'Nala' ),
		'licenses-open'     => array( 'en' => 'Licenses & Open Knowledge', 'sr' => 'Licence i otvoreno znanje' ),
		'dimitrium-world'   => array( 'en' => 'Dimitrium World', 'sr' => 'Dimitrium svet' ),
	);

	foreach ( $categories as $key => $translations ) {
		$term_ids = array();
		foreach ( $translations as $language => $name ) {
			$slug = $key . '-' . $language;
			$term = get_term_by( 'slug', $slug, 'dimipedia_category' );
			if ( ! $term ) {
				$created = wp_insert_term( $name, 'dimipedia_category', array( 'slug' => $slug ) );
				if ( is_wp_error( $created ) ) {
					continue;
				}
				$term_id = (int) $created['term_id'];
			} else {
				$term_id = (int) $term->term_id;
			}
			pll_set_term_language( $term_id, $language );
			$term_ids[ $language ] = $term_id;
		}
		if ( 2 === count( $term_ids ) && function_exists( 'pll_save_term_translations' ) ) {
			pll_save_term_translations( $term_ids );
		}
	}

	update_option( 'dimipedia_seed_version', DIMIPEDIA_VERSION, false );
}
add_action( 'admin_init', 'dimipedia_seed_categories' );

function dimipedia_activate() {
	dimipedia_register_content();
	dimipedia_seed_categories();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'dimipedia_activate' );

function dimipedia_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'dimipedia_deactivate' );

/** Allow only linked Polylang translations to share a Dimipedia article slug. */
function dimipedia_shared_translation_slug( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
	if ( 'dimipedia_entry' !== $post_type || ! function_exists( 'pll_get_post_translations' ) ) {
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
	$colliding_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'dimipedia_entry' AND ID != %d", $wanted_slug, (int) $post_id ) );
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
add_filter( 'wp_unique_post_slug', 'dimipedia_shared_translation_slug', 20, 6 );

/** Resolve a shared article slug to the entry in the language requested by the URL. */
function dimipedia_resolve_shared_translation_slug( $query_vars ) {
	if ( is_admin() || empty( $query_vars['dimipedia_entry'] ) || empty( $query_vars['lang'] ) || ! function_exists( 'pll_get_post_language' ) ) {
		return $query_vars;
	}
	$slug     = sanitize_title( $query_vars['dimipedia_entry'] );
	$language = sanitize_key( $query_vars['lang'] );
	if ( '' === $slug ) {
		return $query_vars;
	}

	global $wpdb;
	$candidate_ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'dimipedia_entry' AND post_status NOT IN ('auto-draft', 'trash')", $slug ) );
	foreach ( $candidate_ids as $candidate_id ) {
		$candidate_id = (int) $candidate_id;
		if ( $language === pll_get_post_language( $candidate_id, 'slug' ) ) {
			$query_vars['p'] = $candidate_id;
			$query_vars['post_type'] = 'dimipedia_entry';
			unset( $query_vars['dimipedia_entry'] );
			return $query_vars;
		}
	}
	return $query_vars;
}
add_filter( 'request', 'dimipedia_resolve_shared_translation_slug', 20 );

/**
 * A translation can have a localized slug. If a visitor combines a language
 * prefix with the other translation's slug, WordPress may otherwise fall back
 * to that other entry. Redirect to the actual translation instead of serving
 * a non-canonical language/slug combination.
 */
function dimipedia_redirect_mismatched_language_slug() {
	$request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	$legacy_paths = array(
		'/sr/dimipedia/nala/' => '/sr/dimipedia/macka-nala/',
	);
	$request_path = trailingslashit( $request_path );
	if ( isset( $legacy_paths[ $request_path ] ) ) {
		wp_safe_redirect( home_url( $legacy_paths[ $request_path ] ), 301 );
		exit;
	}
	if ( ! is_singular( 'dimipedia_entry' ) || ! function_exists( 'pll_get_post_language' ) || ! function_exists( 'pll_get_post_translations' ) ) {
		return;
	}
	if ( ! preg_match( '#^/(en|sr)/dimipedia/[^/]+/?$#i', $request_path, $matches ) ) {
		return;
	}
	$requested_language = sanitize_key( $matches[1] );
	$post_id            = get_queried_object_id();
	if ( ! $post_id || $requested_language === pll_get_post_language( $post_id, 'slug' ) ) {
		return;
	}
	$translations = pll_get_post_translations( $post_id );
	$target_id    = isset( $translations[ $requested_language ] ) ? (int) $translations[ $requested_language ] : 0;
	if ( $target_id && 'publish' === get_post_status( $target_id ) ) {
		wp_safe_redirect( get_permalink( $target_id ), 301 );
		exit;
	}
}
add_action( 'template_redirect', 'dimipedia_redirect_mismatched_language_slug', -30 );

function dimipedia_infobox_fields( $post_id = 0 ) {
	$language = function_exists( 'pll_get_post_language' ) ? pll_get_post_language( $post_id, 'slug' ) : 'en';
	$sr       = 'sr' === $language;
	return array(
		'also_known_as' => $sr ? 'Takođe poznat kao' : 'Also known as',
		'born'          => $sr ? 'Rođen/a' : 'Born',
		'died'          => $sr ? 'Preminuo/la' : 'Died',
		'nationality'   => $sr ? 'Državljanstvo' : 'Nationality',
		'education'     => $sr ? 'Obrazovanje' : 'Education',
		'occupation'    => $sr ? 'Zanimanje' : 'Occupation',
		'years_active'  => $sr ? 'Godine aktivnosti' : 'Years active',
		'organization'  => $sr ? 'Organizacija' : 'Organization',
		'spouse'        => $sr ? 'Supružnik/ca' : 'Spouse',
		'website'       => $sr ? 'Sajt' : 'Website',
	);
}

function dimipedia_allowed_inline_html() {
	return array(
		'a' => array(
			'href'   => true,
			'target' => true,
			'rel'    => true,
		),
	);
}

function dimipedia_sanitize_inline_value( $value ) {
	return wp_kses( trim( (string) $value ), dimipedia_allowed_inline_html() );
}

/** Types that a Dimipedia entry may explicitly represent in Dimitrium's schema graph. */
function dimipedia_schema_entity_types() {
	return array( 'Person', 'Organization', 'Brand', 'Thing', 'SoftwareApplication', 'MusicAlbum', 'MusicRecording', 'MusicGroup', 'Event', 'SportsOrganization' );
}

function dimipedia_add_infobox_meta_box() {
	add_meta_box( 'dimipedia-infobox', 'Dimipedia Info Box', 'dimipedia_render_infobox_meta_box', 'dimipedia_entry', 'normal', 'high' );
}
add_action( 'add_meta_boxes', 'dimipedia_add_infobox_meta_box' );

function dimipedia_render_infobox_meta_box( $post ) {
	wp_nonce_field( 'dimipedia_save_infobox', 'dimipedia_infobox_nonce' );
	echo '<p>Title and featured image are used automatically. The image caption comes from its Media Library caption unless you add the per-entry caption below. Use that field for translated captions. Empty fields never appear on the public article. Text fields may contain a link such as <code>&lt;a href="https://example.com"&gt;visible text&lt;/a&gt;</code>.</p>';
	echo '<table class="form-table" role="presentation"><tbody>';
	$caption = get_post_meta( $post->ID, '_dimipedia_image_caption', true );
	echo '<tr><th scope="row"><label for="dimipedia_image_caption">Image caption</label></th><td><input class="large-text" id="dimipedia_image_caption" name="dimipedia_image_caption" type="text" value="' . esc_attr( $caption ) . '"><p class="description">Optional per-entry caption. Useful when EN and SR entries use the same Media Library image.</p></td></tr>';
	$schema_type = get_post_meta( $post->ID, '_dimipedia_schema_type', true );
	$schema_id = get_post_meta( $post->ID, '_dimipedia_schema_id', true );
	$schema_same_as = get_post_meta( $post->ID, '_dimipedia_schema_same_as', true );
	echo '<tr><th scope="row"><label for="dimipedia_schema_type">Schema entity type</label></th><td><select id="dimipedia_schema_type" name="dimipedia_schema_type"><option value="">No entity markup</option>';
	foreach ( dimipedia_schema_entity_types() as $type ) {
		echo '<option value="' . esc_attr( $type ) . '" ' . selected( $schema_type, $type, false ) . '>' . esc_html( $type ) . '</option>';
	}
	echo '</select><p class="description">Choose only the real-world entity this entry describes. Translations must use the same type and ID.</p></td></tr>';
	echo '<tr><th scope="row"><label for="dimipedia_schema_id">Stable schema entity ID</label></th><td><input class="large-text" id="dimipedia_schema_id" name="dimipedia_schema_id" type="url" value="' . esc_attr( $schema_id ) . '"><p class="description">For example: https://dimitrium.org/#alexdietrich. This is not the page URL and must be identical for every translation.</p></td></tr>';
	echo '<tr><th scope="row"><label for="dimipedia_schema_same_as">Schema sameAs URLs</label></th><td><textarea class="large-text" rows="3" id="dimipedia_schema_same_as" name="dimipedia_schema_same_as">' . esc_textarea( $schema_same_as ) . '</textarea><p class="description">Optional: one verified URL per line. Do not add ordinary content links.</p></td></tr>';
	foreach ( dimipedia_infobox_fields( $post->ID ) as $key => $label ) {
		$value = get_post_meta( $post->ID, '_dimipedia_' . $key, true );
		echo '<tr><th scope="row"><label for="dimipedia_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="regular-text" id="dimipedia_' . esc_attr( $key ) . '" name="dimipedia_' . esc_attr( $key ) . '" type="' . ( 'website' === $key ? 'url' : 'text' ) . '" value="' . esc_attr( $value ) . '"></td></tr>';
	}
	$facts = get_post_meta( $post->ID, '_dimipedia_additional_facts', true );
	echo '<tr><th scope="row"><label for="dimipedia_additional_facts">Additional facts</label></th><td><textarea class="large-text" rows="5" id="dimipedia_additional_facts" name="dimipedia_additional_facts">' . esc_textarea( $facts ) . '</textarea><p class="description">One fact per line in the form <code>Label | value</code>. Use this for album, project, server or other entity-specific data.</p></td></tr>';
	echo '</tbody></table>';
}

function dimipedia_save_infobox( $post_id ) {
	if ( ! isset( $_POST['dimipedia_infobox_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['dimipedia_infobox_nonce'] ) ), 'dimipedia_save_infobox' ) || ! current_user_can( 'edit_post', $post_id ) || wp_is_post_revision( $post_id ) ) {
		return;
	}
	foreach ( array_keys( dimipedia_infobox_fields( $post_id ) ) as $key ) {
		$raw   = isset( $_POST[ 'dimipedia_' . $key ] ) ? wp_unslash( $_POST[ 'dimipedia_' . $key ] ) : '';
		$value = 'website' === $key ? esc_url_raw( $raw ) : dimipedia_sanitize_inline_value( $raw );
		$value ? update_post_meta( $post_id, '_dimipedia_' . $key, $value ) : delete_post_meta( $post_id, '_dimipedia_' . $key );
	}
	$facts = isset( $_POST['dimipedia_additional_facts'] ) ? trim( wp_kses( wp_unslash( $_POST['dimipedia_additional_facts'] ), dimipedia_allowed_inline_html() ) ) : '';
	$facts ? update_post_meta( $post_id, '_dimipedia_additional_facts', $facts ) : delete_post_meta( $post_id, '_dimipedia_additional_facts' );
	$caption = isset( $_POST['dimipedia_image_caption'] ) ? sanitize_text_field( wp_unslash( $_POST['dimipedia_image_caption'] ) ) : '';
	$caption ? update_post_meta( $post_id, '_dimipedia_image_caption', $caption ) : delete_post_meta( $post_id, '_dimipedia_image_caption' );
	$schema_type = isset( $_POST['dimipedia_schema_type'] ) ? sanitize_key( wp_unslash( $_POST['dimipedia_schema_type'] ) ) : '';
	$schema_type = current( array_filter( dimipedia_schema_entity_types(), static function ( $type ) use ( $schema_type ) { return strtolower( $type ) === $schema_type; } ) );
	$schema_type ? update_post_meta( $post_id, '_dimipedia_schema_type', $schema_type ) : delete_post_meta( $post_id, '_dimipedia_schema_type' );
	$schema_id = isset( $_POST['dimipedia_schema_id'] ) ? esc_url_raw( wp_unslash( $_POST['dimipedia_schema_id'] ) ) : '';
	$schema_id ? update_post_meta( $post_id, '_dimipedia_schema_id', $schema_id ) : delete_post_meta( $post_id, '_dimipedia_schema_id' );
	$same_as = array();
	foreach ( preg_split( '/\r\n|\r|\n/', isset( $_POST['dimipedia_schema_same_as'] ) ? wp_unslash( $_POST['dimipedia_schema_same_as'] ) : '' ) as $url ) {
		$url = esc_url_raw( trim( $url ) );
		if ( $url && wp_http_validate_url( $url ) ) { $same_as[] = $url; }
	}
	$same_as = implode( "\n", array_unique( $same_as ) );
	$same_as ? update_post_meta( $post_id, '_dimipedia_schema_same_as', $same_as ) : delete_post_meta( $post_id, '_dimipedia_schema_same_as' );
}
add_action( 'save_post_dimipedia_entry', 'dimipedia_save_infobox' );

function dimipedia_additional_infobox_rows( $post_id ) {
	$facts = trim( (string) get_post_meta( $post_id, '_dimipedia_additional_facts', true ) );
	if ( '' === $facts ) {
		return array();
	}
	$rows = array();
	foreach ( preg_split( '/\r\n|\r|\n/', $facts ) as $line ) {
		$parts = array_map( 'trim', explode( '|', $line, 2 ) );
		if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] ) {
			$rows[] = array( sanitize_text_field( $parts[0] ), dimipedia_sanitize_inline_value( $parts[1] ) );
		}
	}
	return $rows;
}

function dimipedia_render_info_block() {
	$post_id = get_the_ID() ?: get_queried_object_id();
	if ( ! $post_id || 'dimipedia_entry' !== get_post_type( $post_id ) ) {
		return '';
	}

	$rows = array();
	foreach ( dimipedia_infobox_fields( $post_id ) as $key => $label ) {
		$value = trim( (string) get_post_meta( $post_id, '_dimipedia_' . $key, true ) );
		if ( '' === $value ) {
			continue;
		}
		if ( 'website' === $key ) {
			$value = '<a href="' . esc_url( $value ) . '" rel="external noopener">' . esc_html( preg_replace( '#^https?://#', '', untrailingslashit( $value ) ) ) . '</a>';
		} else {
			$value = dimipedia_sanitize_inline_value( $value );
		}
		$rows[] = array( $label, $value );
	}
	foreach ( dimipedia_additional_infobox_rows( $post_id ) as $row ) {
		$rows[] = array( $row[0], $row[1] );
	}

	$server_status = array(
		'pluto'  => 'pluto',
		'pluton' => 'pluto',
		'venus'  => 'venus',
		'venera' => 'venus',
	);
	$server = isset( $server_status[ get_post_field( 'post_name', $post_id ) ] ) ? $server_status[ get_post_field( 'post_name', $post_id ) ] : '';

	ob_start();
	?>
	<div class="dimipedia-infobox-stack">
		<aside class="dimipedia-infobox" aria-label="<?php echo esc_attr( get_the_title( $post_id ) ); ?>">
			<h2 class="dimipedia-infobox__title"><?php echo esc_html( get_the_title( $post_id ) ); ?></h2>
			<?php if ( has_post_thumbnail( $post_id ) ) : ?>
				<figure class="dimipedia-infobox__image">
					<?php $image_id = get_post_thumbnail_id( $post_id ); ?>
					<a href="<?php echo esc_url( wp_get_attachment_image_url( $image_id, 'full' ) ); ?>" data-lbwps-gid="dimipedia-entry-<?php echo esc_attr( $post_id ); ?>">
						<?php echo get_the_post_thumbnail( $post_id, 'large' ); ?>
					</a>
					<?php $caption = trim( (string) get_post_meta( $post_id, '_dimipedia_image_caption', true ) ); ?>
					<?php $caption = $caption ? $caption : wp_get_attachment_caption( $image_id ); ?>
					<?php if ( $caption ) : ?><figcaption><?php echo esc_html( $caption ); ?></figcaption><?php endif; ?>
				</figure>
			<?php endif; ?>
			<?php if ( $rows ) : ?>
				<dl class="dimipedia-infobox__facts">
					<?php foreach ( $rows as $row ) : ?>
						<dt><?php echo esc_html( $row[0] ); ?></dt><dd><?php echo wp_kses( $row[1], dimipedia_allowed_inline_html() ); ?></dd>
					<?php endforeach; ?>
				</dl>
			<?php endif; ?>
		</aside>
		<?php if ( $server ) : ?><?php echo dimipedia_render_server_status( array( 'server' => $server ) ); ?><?php endif; ?>
	</div>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'dimitrium_info_block', 'dimipedia_render_info_block' );

/** Render the automatically maintained directory on the Dimipedia landing page. */
function dimipedia_render_entries_grid() {
	$language = function_exists( 'pll_current_language' ) ? pll_current_language( 'slug' ) : '';
	$args     = array(
		'post_type'           => 'dimipedia_entry',
		'post_status'         => 'publish',
		'posts_per_page'      => -1,
		'orderby'             => 'title',
		'order'               => 'ASC',
		'ignore_sticky_posts' => true,
	);
	if ( $language ) {
		$args['lang'] = $language;
	}
	$entries = new WP_Query( $args );

	if ( ! $entries->have_posts() ) {
		return '';
	}

	$heading = 'sr' === $language ? 'Znanje koje niko nije tražio' : 'Knowledge nobody asked for';
	ob_start();
	?>
	<section class="wp-block-group website-section dimipedia-directory-section has-theme-3-background-color has-background has-global-padding is-layout-constrained wp-block-group-is-layout-constrained" style="padding-top:80px;padding-bottom:80px" aria-label="<?php echo esc_attr( $heading ); ?>">
		<h2 class="dimipedia-directory-section__title"><?php echo esc_html( $heading ); ?></h2>
		<div class="dimipedia-entries-grid">
			<?php while ( $entries->have_posts() ) : $entries->the_post(); ?>
				<?php
				$entry_id = get_the_ID();
				$terms    = get_the_terms( $entry_id, 'dimipedia_category' );
				$excerpt  = get_the_excerpt( $entry_id );
				if ( '' === trim( $excerpt ) ) {
					$excerpt = wp_trim_words( wp_strip_all_tags( strip_shortcodes( get_post_field( 'post_content', $entry_id ) ) ), 24, '…' );
				}
				?>
				<article class="dimipedia-entry-card">
					<a class="dimipedia-entry-card__link" href="<?php echo esc_url( get_permalink( $entry_id ) ); ?>">
						<?php if ( has_post_thumbnail( $entry_id ) ) : ?>
							<div class="dimipedia-entry-card__image"><?php echo get_the_post_thumbnail( $entry_id, 'large', array( 'loading' => 'lazy' ) ); ?></div>
						<?php endif; ?>
						<div class="dimipedia-entry-card__content">
							<?php if ( $terms && ! is_wp_error( $terms ) ) : ?><p class="dimipedia-entry-card__category"><?php echo esc_html( $terms[0]->name ); ?></p><?php endif; ?>
							<h3 class="dimipedia-entry-card__title"><?php the_title(); ?></h3>
							<?php if ( $excerpt ) : ?><p class="dimipedia-entry-card__excerpt"><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
						</div>
					</a>
				</article>
			<?php endwhile; ?>
		</div>
	</section>
	<?php
	wp_reset_postdata();
	return (string) ob_get_clean();
}
add_shortcode( 'dimipedia_entries_grid', 'dimipedia_render_entries_grid' );

/** Store signed, minimal server-monitoring events without exposing infrastructure details. */
function dimipedia_server_status_option( $server ) {
	return 'dimipedia_server_status_' . sanitize_key( $server );
}

function dimipedia_public_server_status( $server ) {
	$status = get_option( dimipedia_server_status_option( $server ), array() );
	$status = is_array( $status ) ? $status : array();
	$last   = isset( $status['last_report'] ) ? (int) $status['last_report'] : 0;
	$age    = $last ? max( 0, time() - $last ) : null;
	$state  = null === $age ? 'unknown' : ( $age <= 300 ? 'online' : ( $age <= 900 ? 'delayed' : 'offline' ) );
	return array(
		'server'      => $server,
		'state'       => $state,
		'last_report' => $last,
		'heartbeat'   => isset( $status['heartbeat'] ) && is_array( $status['heartbeat'] ) ? $status['heartbeat'] : array(),
		'peer'        => isset( $status['peer'] ) && is_array( $status['peer'] ) ? $status['peer'] : array(),
		'backup'      => isset( $status['backup'] ) && is_array( $status['backup'] ) ? $status['backup'] : array(),
	);
}

function dimipedia_register_server_status_routes() {
	register_rest_route(
		'dimipedia/v1',
		'/server-status/(?P<server>pluto|venus)',
		array(
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => 'dimipedia_receive_server_status',
				'permission_callback' => '__return_true',
			),
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => 'dimipedia_read_server_status',
				'permission_callback' => '__return_true',
			),
		)
	);
	register_rest_route(
		'dimipedia/v1',
		'/entry-preview/(?P<language>en|sr)/(?P<slug>[a-z0-9-]+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'dimipedia_read_entry_preview',
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'dimipedia_register_server_status_routes' );

function dimipedia_receive_server_status( WP_REST_Request $request ) {
	$secret = (string) get_option( 'dimipedia_server_status_secret', '' );
	$given  = (string) $request->get_header( 'x-dimipedia-status-key' );
	if ( '' === $secret || ! hash_equals( $secret, $given ) ) {
		return new WP_Error( 'dimipedia_status_forbidden', 'Invalid status reporter.', array( 'status' => 403 ) );
	}
	$event = sanitize_key( (string) $request->get_param( 'event' ) );
	$state = sanitize_key( (string) $request->get_param( 'state' ) );
	$valid = array(
		'heartbeat' => array( 'success', 'failure' ),
		'peer'      => array( 'up', 'down', 'unknown' ),
		'backup'    => array( 'success', 'failure' ),
	);
	if ( ! isset( $valid[ $event ] ) || ! in_array( $state, $valid[ $event ], true ) ) {
		return new WP_Error( 'dimipedia_status_invalid', 'Invalid status event.', array( 'status' => 400 ) );
	}
	$server = sanitize_key( $request['server'] );
	$status = get_option( dimipedia_server_status_option( $server ), array() );
	$status = is_array( $status ) ? $status : array();
	$now    = time();
	$status['last_report'] = $now;
	$status[ $event ]      = array( 'state' => $state, 'at' => $now );
	update_option( dimipedia_server_status_option( $server ), $status, false );
	return rest_ensure_response( array( 'accepted' => true ) );
}

function dimipedia_read_server_status( WP_REST_Request $request ) {
	return rest_ensure_response( dimipedia_public_server_status( sanitize_key( $request['server'] ) ) );
}

/** Return only the public, compact fields needed for an article hover preview. */
function dimipedia_read_entry_preview( WP_REST_Request $request ) {
	$language = sanitize_key( $request['language'] );
	$slug     = sanitize_title( $request['slug'] );
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'dimipedia_entry' AND post_status = 'publish'", $slug ) );
	$post_id = 0;
	foreach ( $ids as $candidate_id ) {
		$candidate_id = (int) $candidate_id;
		if ( ! function_exists( 'pll_get_post_language' ) || $language === pll_get_post_language( $candidate_id, 'slug' ) ) {
			$post_id = $candidate_id;
			break;
		}
	}
	if ( ! $post_id ) {
		return new WP_Error( 'dimipedia_preview_not_found', 'Entry not found.', array( 'status' => 404 ) );
	}
	$terms = get_the_terms( $post_id, 'dimipedia_category' );
	$facts = array();
	foreach ( dimipedia_infobox_fields( $post_id ) as $key => $label ) {
		$value = wp_strip_all_tags( (string) get_post_meta( $post_id, '_dimipedia_' . $key, true ) );
		if ( '' !== trim( $value ) ) {
			$facts[] = array( 'label' => $label, 'value' => $value );
		}
		if ( count( $facts ) >= 3 ) {
			break;
		}
	}
	$excerpt = get_the_excerpt( $post_id );
	if ( '' === trim( $excerpt ) ) {
		$excerpt = get_post_field( 'post_content', $post_id );
	}
	$excerpt = html_entity_decode( wp_strip_all_tags( strip_shortcodes( $excerpt ) ), ENT_QUOTES, get_bloginfo( 'charset' ) );
	$excerpt = preg_replace( '/\s*\[(?:…|\.\.\.)\]\s*$/u', '', $excerpt );
	$excerpt = wp_trim_words( $excerpt, 48, '…' );
	$image_id = get_post_thumbnail_id( $post_id );
	$image    = $image_id ? wp_get_attachment_image_src( $image_id, 'medium_large' ) : false;
	return rest_ensure_response( array(
		'title'    => get_the_title( $post_id ),
		'url'      => get_permalink( $post_id ),
		'category' => $terms && ! is_wp_error( $terms ) ? html_entity_decode( wp_strip_all_tags( $terms[0]->name ), ENT_QUOTES, get_bloginfo( 'charset' ) ) : '',
		'image'    => $image ? $image[0] : '',
		'imageRatio' => $image && ! empty( $image[1] ) ? ( (float) $image[2] / (float) $image[1] ) : 1,
		'excerpt'  => $excerpt,
		'facts'    => $facts,
	) );
}

function dimipedia_status_time( $timestamp, $language ) {
	if ( ! $timestamp ) {
		return 'sr' === $language ? 'Nema potvrde' : 'No confirmation yet';
	}
	return sprintf( 'sr' === $language ? 'pre %s' : '%s ago', human_time_diff( (int) $timestamp, time() ) );
}

function dimipedia_render_server_status( $atts ) {
	$atts     = shortcode_atts( array( 'server' => '' ), $atts, 'dimipedia_server_status' );
	$server   = sanitize_key( $atts['server'] );
	$language = function_exists( 'pll_current_language' ) ? pll_current_language( 'slug' ) : 'en';
	if ( ! in_array( $server, array( 'pluto', 'venus' ), true ) ) {
		return '';
	}
	$status = dimipedia_public_server_status( $server );
	$labels = 'sr' === $language ? array(
		'online'  => 'Online', 'delayed' => 'Kasni potvrda', 'offline' => 'Offline / nema sveže potvrde', 'unknown' => 'Čeka prvu potvrdu',
		'title' => 'Aktivnost servera', 'heartbeat' => 'Healthchecks heartbeat', 'peer' => 'Peer provera', 'backup' => 'Poslednji backup',
		'caveat' => 'Ova stranica se hostuje na Plutonu. Pri potpunom prekidu Plutona posetilac će prvo videti da Dimitrium nije dostupan; ovde se zato prikazuje poslednja zabeležena potvrda, ne tvrdnja da stranica može da nadživi svoj host.',
	) : array(
		'online'  => 'Online', 'delayed' => 'Confirmation delayed', 'offline' => 'Offline / no fresh confirmation', 'unknown' => 'Awaiting first confirmation',
		'title' => 'Server activity', 'heartbeat' => 'Healthchecks heartbeat', 'peer' => 'Peer check', 'backup' => 'Latest backup',
		'caveat' => 'This page is hosted on Pluto. During a total Pluto outage, a visitor will first see that Dimitrium is unavailable; this card therefore shows the last recorded confirmation, not a claim that the page can outlive its own host.',
	);
	$format_event = function( $event ) use ( $status, $language ) {
		$entry = $status[ $event ];
		if ( empty( $entry['at'] ) ) {
			return dimipedia_status_time( 0, $language );
		}
		return ( 'sr' === $language ? ( 'success' === $entry['state'] || 'up' === $entry['state'] ? 'Uspešno' : 'Problem' ) : ( 'success' === $entry['state'] || 'up' === $entry['state'] ? 'Successful' : 'Problem' ) ) . ' · ' . dimipedia_status_time( $entry['at'], $language );
	};
	ob_start();
	?>
	<section class="dimipedia-server-status dimipedia-server-status--<?php echo esc_attr( $status['state'] ); ?>" aria-label="<?php echo esc_attr( $server . ' status' ); ?>">
		<h2 class="dimipedia-server-status__title"><?php echo esc_html( $labels['title'] ); ?></h2>
		<p class="dimipedia-server-status__state"><span aria-hidden="true"></span><?php echo esc_html( $labels[ $status['state'] ] ); ?></p>
		<p class="dimipedia-server-status__updated"><?php echo esc_html( dimipedia_status_time( $status['last_report'], $language ) ); ?></p>
		<dl>
			<dt><?php echo esc_html( $labels['heartbeat'] ); ?></dt><dd><?php echo esc_html( $format_event( 'heartbeat' ) ); ?></dd>
			<dt><?php echo esc_html( $labels['peer'] ); ?></dt><dd><?php echo esc_html( $format_event( 'peer' ) ); ?></dd>
			<dt><?php echo esc_html( $labels['backup'] ); ?></dt><dd><?php echo esc_html( $format_event( 'backup' ) ); ?></dd>
		</dl>
		<?php if ( 'pluto' === $server ) : ?><p class="dimipedia-server-status__caveat"><?php echo esc_html( $labels['caveat'] ); ?></p><?php endif; ?>
	</section>
	<?php
	return (string) ob_get_clean();
}
add_shortcode( 'dimipedia_server_status', 'dimipedia_render_server_status' );

function dimipedia_enqueue_styles() {
	$post = get_post();
	if ( is_singular( 'dimipedia_entry' ) || ( $post && has_shortcode( $post->post_content, 'dimipedia_entries_grid' ) ) ) {
		wp_enqueue_style( 'dimipedia', plugin_dir_url( __FILE__ ) . 'assets/dimipedia.css', array(), DIMIPEDIA_VERSION );
		wp_enqueue_script( 'dimipedia-preview', plugin_dir_url( __FILE__ ) . 'assets/dimipedia-preview.js', array(), DIMIPEDIA_VERSION, true );
		wp_add_inline_script( 'dimipedia-preview', 'window.dimipediaPreview = ' . wp_json_encode( array( 'apiBase' => esc_url_raw( rest_url( 'dimipedia/v1/entry-preview/' ) ), 'enabled' => is_singular( 'dimipedia_entry' ) ) ) . ';', 'before' );
		$version_markup = sprintf(
			'(function(){var enhanceHeader=function(){document.querySelectorAll(".dimipedia-main-header").forEach(function(header){var content=header.querySelector(":scope > .wp-block-group");if(content&&!header.querySelector(".dimipedia-main-header__home-link")){var homeLink=document.createElement("a");homeLink.className="dimipedia-main-header__home-link";homeLink.href=document.documentElement.lang.toLowerCase().indexOf("sr")===0?"/sr/dimipedia/":"/en/dimipedia/";homeLink.setAttribute("aria-label","Dimipedia");content.before(homeLink);homeLink.appendChild(content);}var title=header.querySelector(".dimipedia-main-header__home-link .wp-block-heading");if(title&&!title.dataset.dimipediaTypewriter){title.dataset.dimipediaTypewriter="true";var label=title.textContent.trim();title.setAttribute("aria-label",label);title.replaceChildren();var text=document.createElement("span");text.className="dimipedia-main-header__typewriter-text";var cursor=document.createElement("span");cursor.className="dimipedia-main-header__cursor";cursor.setAttribute("aria-hidden","true");cursor.textContent="_";title.append(text,cursor);if(window.matchMedia("(prefers-reduced-motion: reduce)").matches){text.textContent=label;title.classList.add("is-finished");}else{var index=0;var typeNext=function(){if(index<label.length){text.textContent+=label.charAt(index++);window.setTimeout(typeNext,110);}else{window.setTimeout(function(){title.classList.add("is-finished");},500);}};window.setTimeout(typeNext,300);}}if(header.querySelector(".dimipedia-main-header__version")){return;}var version=document.createElement("span");version.className="dimipedia-main-header__version";version.textContent=%s;header.appendChild(version);});};if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",enhanceHeader,{once:true});}else{enhanceHeader();}})();',
			wp_json_encode( 'v' . DIMIPEDIA_VERSION )
		);
		wp_add_inline_script( 'dimipedia-preview', $version_markup, 'after' );
	}
}
add_action( 'wp_enqueue_scripts', 'dimipedia_enqueue_styles' );
