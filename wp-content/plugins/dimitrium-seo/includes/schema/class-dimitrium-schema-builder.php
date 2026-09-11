<?php
/**
 * Central structured-data subsystem. Add project-specific entity mappings through
 * the dimitrium_seo_schema_config filter; no provider guesses real-world facts.
 */
class Dimitrium_SEO_Schema_Builder {
	const PERSON_ID  = 'https://dimitrium.org/#aleksadimitrijevic';
	const DIMITRIUM_ID = 'https://dimitrium.org/#dimitrium';
	const WEBSITE_ID = 'https://dimitrium.org/#website';

	public static function output() {
		$builder = new self( new Dimitrium_SEO_Schema_Context() );
		$graph   = $builder->build();
		if ( ! $graph ) {
			return '';
		}
		return '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . "</script>\n";
	}

	private $context;
	private $registry;
	private $config;

	private function __construct( Dimitrium_SEO_Schema_Context $context ) {
		$this->context  = $context;
		$this->registry = new Dimitrium_SEO_Schema_Registry();
		$this->config   = apply_filters( 'dimitrium_seo_schema_config', array(
			// These are the profiles already explicitly used by Dimitrium SEO.
			'person_same_as' => array( 'https://www.instagram.com/tradicije/', 'https://mastodon.social/@haslem', 'https://www.youtube.com/@koombl4' ),
			'dimitrium_same_as' => array(),
			'aleksa_profile_ids' => array( 498, 501 ),
			// Site updates are BlogPosting by default; opt in to Article/NewsArticle per post.
			'article_types' => array(),
			// Key by a translation's post ID. Add only verified project facts.
			'software' => array(),
			'music' => array(),
		) );
	}

	private function build() {
		$this->add_global_entities();
		$this->add_page();
		$this->add_content_entity();
		return $this->registry->graph();
	}

	private function add_global_entities() {
		$profile_id = $this->profile_id();
		$person = array( '@type' => 'Person', '@id' => self::PERSON_ID, 'name' => 'Aleksa Dimitrijević', 'sameAs' => $this->config['person_same_as'] );
		if ( $profile_id ) {
			$person['url'] = get_permalink( $profile_id );
			$image = get_the_post_thumbnail_url( $profile_id, 'full' );
			if ( $image ) { $person['image'] = $image; }
			$description = dimitrium_seo_description( $profile_id );
			if ( $description ) { $person['description'] = $description; }
		}
		$this->registry->add( $this->clean( $person ) );

		// Dimitrium is a creative/site identity, not presented on the site as a company.
		$this->registry->add( $this->clean( array( '@type' => 'Brand', '@id' => self::DIMITRIUM_ID, 'name' => 'Dimitrium', 'url' => home_url( '/' ), 'creator' => $this->context->ref( self::PERSON_ID ), 'sameAs' => $this->config['dimitrium_same_as'] ) ) );
		$this->registry->add( array( '@type' => 'WebSite', '@id' => self::WEBSITE_ID, 'url' => home_url( '/' ), 'name' => 'Dimitrium', 'inLanguage' => array( 'en', 'sr' ), 'publisher' => $this->context->ref( self::PERSON_ID ), 'about' => $this->context->ref( self::DIMITRIUM_ID ) ) );
	}

	private function add_page() {
		$type = $this->context->is_news_archive ? 'CollectionPage' : 'WebPage';
		$entity = $this->dimipedia_entity();
		if ( $entity && in_array( $entity['@type'], array( 'Person', 'Organization' ), true ) ) { $type = 'ProfilePage'; }
		elseif ( $this->is_aleksa_profile() ) { $type = 'ProfilePage'; } // Safe legacy fallback for the existing profile.
		$page = array( '@type' => $type, '@id' => $this->context->canonical . '#webpage', 'url' => $this->context->canonical, 'name' => $this->context->title, 'description' => $this->context->description, 'inLanguage' => $this->context->language, 'isPartOf' => $this->context->ref( self::WEBSITE_ID ) );
		if ( $entity ) { $page['mainEntity'] = $this->context->ref( $entity['@id'] ); }
		elseif ( $this->is_aleksa_profile() ) { $page['mainEntity'] = $this->context->ref( self::PERSON_ID ); }
		$this->registry->add( $this->clean( $page ) );
	}

	private function add_content_entity() {
		if ( ! $this->context->post_id ) { return; }
		$post_type = get_post_type( $this->context->post_id );
		if ( 'dimipedia_entry' === $post_type ) { $this->add_dimipedia_entity(); return; }
		if ( 'dimitrium_news' === $post_type ) { $this->add_article( $this->config['article_types'][ $this->context->post_id ] ?? 'BlogPosting' ); return; }
		if ( isset( $this->config['software'][ $this->context->post_id ] ) ) { $this->add_software( $this->config['software'][ $this->context->post_id ] ); return; }
		if ( isset( $this->config['music'][ $this->context->post_id ] ) ) { $this->add_music( $this->config['music'][ $this->context->post_id ] ); }
	}

	/** Register only facts explicitly stored on a Dimipedia entry; never infer an entity from a title or slug. */
	private function add_dimipedia_entity() {
		$entity = $this->dimipedia_entity();
		if ( ! $entity ) { return; }
		$post_id = $this->context->post_id;
		$entity['name'] = html_entity_decode( wp_strip_all_tags( get_the_title( $post_id ) ), ENT_QUOTES, 'UTF-8' );
		$entity['url'] = $this->context->canonical;
		$entity['description'] = dimitrium_seo_description( $post_id );
		$image = get_the_post_thumbnail_url( $post_id, 'full' );
		if ( ! $image ) { $image = trim( (string) get_post_meta( $post_id, '_dimitrium_seo_image', true ) ); }
		if ( $image ) { $entity['image'] = $image; }
		$same_as = $this->same_as_urls( get_post_meta( $post_id, '_dimipedia_schema_same_as', true ) );
		if ( $same_as ) { $entity['sameAs'] = $same_as; }
		$entity = apply_filters( 'dimitrium_seo_schema_dimipedia_entity', $entity, $post_id, $this->context );
		if ( is_array( $entity ) && ! empty( $entity['@id'] ) && ! empty( $entity['@type'] ) ) { $this->registry->add( $this->clean( $entity ) ); }
	}

	private function dimipedia_entity( $post_id = 0 ) {
		$post_id = $post_id ?: $this->context->post_id;
		if ( 'dimipedia_entry' !== get_post_type( $post_id ) ) { return null; }
		$type = (string) get_post_meta( $post_id, '_dimipedia_schema_type', true );
		$id = esc_url_raw( get_post_meta( $post_id, '_dimipedia_schema_id', true ) );
		$allowed = array( 'Person', 'Organization', 'Brand', 'Thing', 'SoftwareApplication', 'MusicAlbum', 'MusicRecording', 'MusicGroup', 'Event', 'SportsOrganization' );
		$site_entity_prefix = untrailingslashit( home_url( '/' ) ) . '/#';
		if ( ! in_array( $type, $allowed, true ) || 0 !== strpos( $id, $site_entity_prefix ) ) { return null; }
		return array( '@type' => $type, '@id' => $id );
	}

	private function same_as_urls( $value ) {
		$urls = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $value ) as $url ) {
			$url = esc_url_raw( trim( $url ) );
			if ( $url && wp_http_validate_url( $url ) ) { $urls[] = $url; }
		}
		return array_values( array_unique( $urls ) );
	}

	private function add_article( $type ) {
		$id = $this->context->canonical . '#article';
		$node = array( '@type' => $type, '@id' => $id, 'headline' => html_entity_decode( wp_strip_all_tags( get_the_title( $this->context->post_id ) ), ENT_QUOTES, 'UTF-8' ), 'url' => $this->context->canonical, 'description' => $this->context->description, 'datePublished' => get_the_date( DATE_W3C, $this->context->post_id ), 'dateModified' => get_the_modified_date( DATE_W3C, $this->context->post_id ), 'inLanguage' => $this->context->language, 'author' => $this->context->ref( self::PERSON_ID ), 'publisher' => $this->context->ref( self::PERSON_ID ), 'isPartOf' => $this->context->ref( self::WEBSITE_ID ), 'mainEntityOfPage' => $this->context->ref( $this->context->canonical . '#webpage' ) );
		if ( $this->context->image ) { $node['image'] = $this->context->image; }
		$this->registry->add( $this->clean( $node ) );
	}

	private function add_software( array $facts ) {
		$node = array( '@type' => 'SoftwareApplication', '@id' => $facts['@id'] ?? $this->context->canonical . '#software', 'name' => $facts['name'] ?? get_the_title( $this->context->post_id ), 'description' => $facts['description'] ?? $this->context->description, 'url' => $this->context->canonical, 'author' => $this->context->ref( self::PERSON_ID ), 'mainEntityOfPage' => $this->context->ref( $this->context->canonical . '#webpage' ) );
		foreach ( array( 'applicationCategory', 'operatingSystem', 'softwareVersion', 'license', 'codeRepository', 'downloadUrl', 'softwareRequirements' ) as $key ) { if ( ! empty( $facts[ $key ] ) ) { $node[ $key ] = $facts[ $key ]; } }
		$this->registry->add( $this->clean( $node ) );
	}

	private function add_music( array $facts ) {
		if ( empty( $facts['@type'] ) ) { return; }
		$node = $facts;
		$node['@id'] = $node['@id'] ?? $this->context->canonical . '#music';
		$node['url'] = $node['url'] ?? $this->context->canonical;
		$node['mainEntityOfPage'] = $this->context->ref( $this->context->canonical . '#webpage' );
		$this->registry->add( $this->clean( $node ) );
	}

	private function profile_id() {
		$current = $this->dimipedia_entity();
		if ( $current && self::PERSON_ID === $current['@id'] ) { return $this->context->post_id; }
		foreach ( (array) $this->config['aleksa_profile_ids'] as $id ) { if ( get_post_status( $id ) === 'publish' ) { return (int) $id; } }
		return 0;
	}

	private function is_aleksa_profile() {
		$entity = $this->dimipedia_entity();
		return ( $entity && self::PERSON_ID === $entity['@id'] ) || in_array( (int) $this->context->post_id, array_map( 'intval', (array) $this->config['aleksa_profile_ids'] ), true );
	}

	private function clean( array $node ) {
		return array_filter( $node, static function ( $value ) { return null !== $value && '' !== $value && array() !== $value; } );
	}
}
