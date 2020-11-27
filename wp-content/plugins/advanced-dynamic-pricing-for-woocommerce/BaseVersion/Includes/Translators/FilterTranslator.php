<?php

namespace ADP\BaseVersion\Includes\Translators;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class FilterTranslator {
	public function translateByType( $type, $value, $language_code ) {
		$return_as_array = is_array( $value );
		$values          = is_array( $value ) ? $value : array( $value );

		if ( 'products' === $type ) {
			$values = $this->translateProduct( $values, $language_code );
		} elseif ( 'product_categories' === $type ) {
			$values = $this->translateCategory( $values, $language_code );
		} elseif ( 'product_category_slug' === $type ) {
			$values = $this->translateCategorySlug( $values, $language_code );
		} elseif ( 'product_attributes' === $type ) {
			$values = $this->translateAttribute( $values, $language_code );
		} elseif ( 'product_tags' === $type ) {
			$values = $this->translateTag( $values, $language_code );
		} elseif ( 'product_skus' === $type ) {
			// do not translate
		} elseif ( 'product_custom_fields' === $type ) {
			// do not translate
		} else {
			$values = $this->translateCustomTax( $values, $type, $language_code );
		}

		return $return_as_array ? $values : reset( $values );
	}

	public function translateProduct( $the_value, $language_code ) {
		$return_as_array = is_array( $the_value );
		$ids             = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $ids as &$id ) {
			$transl_value = apply_filters( 'translate_object_id', $id, 'post', false, $language_code );
			if ( $transl_value ) {
				$id = $transl_value;
			}
		}

		return $return_as_array ? $ids : reset( $ids );
	}

	public function translateCategory( $the_value, $language_code ) {
		$return_as_array = is_array( $the_value );
		$ids             = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $ids as &$id ) {
			$transl_value = apply_filters( 'translate_object_id', $id, 'product_cat', false, $language_code );
			if ( $transl_value ) {
				$id = $transl_value;
			}
		}

		return $return_as_array ? $ids : reset( $ids );
	}

	public function translateCategorySlug( $the_value, $language_code ) {
		$return_as_array = is_array( $the_value );
		$slugs           = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $slugs as &$slug ) {
			// translated in get_term_by
			$term = get_term_by( 'slug', $slug, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$slug = $term->slug;
			}
		}

		return $return_as_array ? $slugs : reset( $slugs );
	}

	public function translateAttribute( $the_value, $language_code ) {
		$return_as_array = is_array( $the_value );
		$ids             = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $ids as &$id ) {
			// translated in get_term
			$term = get_term( $id );
			if ( $term && ! is_wp_error( $term ) ) {
				$id = $term->term_id;
			}
		}

		return $return_as_array ? $ids : reset( $ids );
	}

	public function translateTag( $the_value, $language_code ) {
		$return_as_array = is_array( $the_value );
		$ids             = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $ids as &$id ) {
			$transl_value = apply_filters( 'translate_object_id', $id, 'product_tag', false, $language_code );
			if ( $transl_value ) {
				$id = $transl_value;
			}
		}

		return $return_as_array ? $ids : reset( $ids );
	}

	public function translateCustomTax( $the_value, $tax, $language_code ) {
		$return_as_array = is_array( $the_value );
		$ids             = is_array( $the_value ) ? $the_value : array( $the_value );

		foreach ( $ids as &$id ) {
			$transl_value = apply_filters( 'translate_object_id', $id, $tax, false, $language_code );
			if ( $transl_value ) {
				$id = $transl_value;
			}
		}

		return $return_as_array ? $ids : reset( $ids );
	}

}