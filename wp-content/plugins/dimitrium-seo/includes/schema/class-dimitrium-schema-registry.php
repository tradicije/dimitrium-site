<?php
/** A deliberately small @id registry: one real-world entity, one graph node. */
class Dimitrium_SEO_Schema_Registry {
	private $nodes = array();

	public function add( array $node ) {
		if ( empty( $node['@id'] ) ) {
			return;
		}
		$id = $node['@id'];
		$this->nodes[ $id ] = isset( $this->nodes[ $id ] ) ? $this->merge( $this->nodes[ $id ], $node ) : $node;
	}

	public function graph() {
		return array_values( $this->nodes );
	}

	private function merge( array $old, array $new ) {
		foreach ( $new as $key => $value ) {
			if ( '@id' === $key || '' === $value || array() === $value || null === $value ) {
				continue;
			}
			if ( ! isset( $old[ $key ] ) || '' === $old[ $key ] || array() === $old[ $key ] ) {
				$old[ $key ] = $value;
				continue;
			}
			if ( is_array( $old[ $key ] ) && is_array( $value ) && $this->is_list( $old[ $key ] ) && $this->is_list( $value ) ) {
				$old[ $key ] = array_values( array_unique( array_merge( $old[ $key ], $value ), SORT_REGULAR ) );
			}
		}
		return $old;
	}

	private function is_list( array $value ) {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
