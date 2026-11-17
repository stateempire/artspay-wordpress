<?php

if ( ! function_exists( 'blocksy_validate_single_slider' ) ) {
	function blocksy_validate_single_slider( $value ) {
		if ( ! intval( $value ) && intval( $value ) !== 0 ) {
			return null;
		}

		return true;
	}
}

if ( ! function_exists( 'blocksy_validate_for' ) ) {
	function blocksy_validate_for( $option, $input ) {
		if (
			'ct-header-builder' === $option['type']
			||
			'ct-footer-builder' === $option['type']
		) {
			return blocksy_sanitize_builder_value( $option, $input );
		}

		if (
			'ct-switch' === $option['type']
			||
			'ct-panel' === $option['type']
		) {
			$allowed_values = array( 'yes', 'no' );

			if ( isset( $option['behavior'] ) && 'bool' === $option['behavior'] ) {
				$allowed_values = array( true, false );
			}

			if ( in_array( $input, $allowed_values, true ) ) {
				return $input;
			}

			return $option['value'];
		}

		if ( 'text' === $option['type'] || 'textarea' === $option['type'] ) {
			return wp_kses_post( $input );
		}

		if ( 'ct-color-picker' === $option['type'] ) {
			if ( ! is_array( $input ) ) {
				return $option['value'];
			}

			foreach ( $input as $single_color ) {
				if ( ! isset( $single_color['color'] ) ) {
					return $option['value'];
				}

				if ( ! blocksy_validate_color( $single_color['color'] ) ) {
					return $option['value'];
				}
			}
		}

		if (
			'ct-select' === $option['type']
			||
			'ct-image-picker' === $option['type']
			||
			'ct-radio' === $option['type']
		) {
			if ( ! in_array(
				$input,
				array_reduce(
					blocksy_ordered_keys( $option['choices'] ),
					function ( $current, $item ) {
						return array_merge( $current, array( $item['key'] ) );
					},
					array()
				),
				true
			) ) {
				return $option['value'];
			}
		}

		if (
			'ct-checkboxes' === $option['type']
			||
			'ct-visibility' === $option['type']
		) {
			foreach ( $input as $key => $value ) {
				if (
					! is_bool( $value )
					||
					! in_array(
						$key,
						array_reduce(
							blocksy_ordered_keys( $option['choices'] ),
							function ( $current, $item ) {
								return array_merge( $current, array( $item['key'] ) );
							},
							array()
						),
						true
					)
				) {
					return $option['value'];
				}
			}
		}

		if ( 'ct-number' === $option['type'] ) {
			if ( isset( $option['responsive'] ) && $option['responsive'] ) {
				$values_to_validate = array( 'desktop', 'tablet', 'mobile' );

				foreach ( blocksy_expand_responsive_value( $input ) as $single_key => $single_value ) {
					if ( ! in_array( $single_key, $values_to_validate, true ) ) {
						continue;
					}

					if ( ! is_numeric( $single_value ) ) {
						return $option['value'];
					}
				}

				return $input;
			}

			if ( ! is_numeric( $input ) ) {
				return $option['value'];
			}

			return max(
				intval( $option['min'] ),
				min( intval( $option['max'] ), intval( $input ) )
			);
		}

		if ( 'ct-slider' === $option['type'] ) {
			if ( isset( $option['responsive'] ) && $option['responsive'] ) {
				$values_to_validate = array( 'desktop', 'tablet', 'mobile' );

				foreach ( blocksy_expand_responsive_value( $input ) as $single_key => $single_value ) {
					if ( ! in_array( $single_key, $values_to_validate, true ) ) {
						continue;
					}

					if ( ! blocksy_validate_single_slider( $single_value ) ) {
						return $option['value'];
					}
				}

				return $input;
			}

			if (
				is_array( $input )
				||
				! blocksy_validate_single_slider( $input )
			) {
				return $option['value'];
			}
		}

		if ( 'ct-image-uploader' === $option['type'] ) {
			if (
				! is_array( $input )
				||
				! isset( $input['attachment_id'] )
				||
				! wp_attachment_is_image( $input['attachment_id'] )
			) {
				return $option['value'];
			}
		}

		return $input;
	}
}

if ( ! function_exists( 'blocksy_include_sanitizer' ) ) {
	function blocksy_include_sanitizer( $option ) {
		if ( ! isset( $option['setting'] ) ) {
			return $option;
		}

		if ( isset( $option['setting']['sanitize_callback'] ) ) {
			return $option;
		}

		$option['setting']['sanitize_callback'] = function ( $input ) use ( $option ) {
			return blocksy_validate_for( $option, $input );
		};

		return $option;
	}
}
