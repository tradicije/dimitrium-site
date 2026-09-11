<?php
/** Page facts shared by all schema providers. It never resolves a second canonical URL. */
class Dimitrium_SEO_Schema_Context {
	public $post_id;
	public $canonical;
	public $language;
	public $title;
	public $description;
	public $image;
	public $image_size;
	public $is_news_archive;

	public function __construct() {
		$this->is_news_archive = dimitrium_seo_news_archive();
		$this->post_id         = $this->is_news_archive ? 0 : get_queried_object_id();
		$this->canonical       = dimitrium_seo_canonical_url( $this->post_id );
		$this->language        = function_exists( 'pll_current_language' ) ? pll_current_language() : substr( get_locale(), 0, 2 );
		$this->title           = html_entity_decode( wp_strip_all_tags( dimitrium_seo_title() ), ENT_QUOTES, 'UTF-8' );
		$this->description     = dimitrium_seo_description( $this->post_id );
		$this->image           = $this->is_news_archive ? '' : dimitrium_seo_image( $this->post_id );
		$this->image_size      = $this->image ? dimitrium_seo_image_dimensions( $this->post_id, $this->image ) : array();
	}

	public function ref( $id ) {
		return array( '@id' => $id );
	}
}
