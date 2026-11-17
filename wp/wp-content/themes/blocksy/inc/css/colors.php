<?php

/**
 * Generate colors based on input.
 *
 * @param array $color_descriptor Colors array.
 */
if ( ! function_exists( 'blocksy_get_colors' ) ) {
	function blocksy_get_colors( $color_descriptor, $defaults ) {
		$result = array();

		foreach ( $defaults as $id => $default_data ) {
			$data = $default_data;

			if (
				is_array( $color_descriptor )
				&&
				isset( $color_descriptor[ $id ] )
				&&
				is_array( $color_descriptor[ $id ] )
			) {
				$data = $color_descriptor[ $id ];
			}

			$result[ $id ] = blocksy_get_color( $data );
		}

		return $result;
	}
}

/**
 * Extract and validate a color from a descriptor.
 *
 * @param array $color_descriptor Color descriptor with a 'color' value.
 * @return string|null Trimmed color or CT_CSS_SKIP_RULE when invalid.
 *                     Returns null when the color is missing or null.
 */
if ( ! function_exists( 'blocksy_get_color' ) ) {
	function blocksy_get_color( $color_descriptor ) {
		if ( ! isset( $color_descriptor['color'] ) ) {
			return null;
		}

		$color = blocksy_validate_color( $color_descriptor['color'] );

		return $color ? $color : 'CT_CSS_SKIP_RULE';
	}
}

if ( ! function_exists( 'blocksy_output_colors' ) ) {
	function blocksy_output_colors( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'css'                     => null,
				'tablet_css'              => null,
				'mobile_css'              => null,
				'value'                   => null,
				'default'                 => null,
				'variables'               => array(),

				'desktop_selector_prefix' => '',
				'tablet_selector_prefix'  => '',
				'mobile_selector_prefix'  => '',

				'important'               => false,
				'responsive'              => false,
			)
		);

		blocksy_assert_args( $args, array( 'css', 'default' ) );

		if ( $args['responsive'] ) {
			blocksy_assert_args( $args, array( 'tablet_css', 'mobile_css' ) );
		}

		$responsive_value         = blocksy_expand_responsive_value( $args['value'] );
		$responsive_default_value = blocksy_expand_responsive_value( $args['default'] );

		$desktop_colors = blocksy_get_colors(
			$responsive_value['desktop'],
			$responsive_default_value['desktop']
		);

		$tablet_colors = blocksy_get_colors(
			$responsive_value['tablet'],
			$responsive_default_value['tablet']
		);

		$mobile_colors = blocksy_get_colors(
			$responsive_value['mobile'],
			$responsive_default_value['mobile']
		);

		foreach ( $args['variables'] as $key => $descriptor ) {
			if ( ! isset( $descriptor['selector'] ) ) {
				$descriptor['selector'] = ':root';
			}

			$local_args = array(
				'css'                     => $args['css'],
				'tablet_css'              => $args['tablet_css'],
				'mobile_css'              => $args['mobile_css'],

				'desktop_selector_prefix' => $args['desktop_selector_prefix'],
				'tablet_selector_prefix'  => $args['tablet_selector_prefix'],
				'mobile_selector_prefix'  => $args['mobile_selector_prefix'],

				'selector'                => $descriptor['selector'],
				'variableName'            => $descriptor['variable'],
				'value'                   => array(
					'desktop' => $desktop_colors[ $key ],
					'tablet'  => $tablet_colors[ $key ],
					'mobile'  => $mobile_colors[ $key ],
				),

				'responsive'              => $args['responsive'],
			);

			if ( isset( $descriptor['variableType'] ) ) {
				$local_args['variableType'] = $descriptor['variableType'];
			}

			if ( $args['important'] ) {
				$local_args['value_suffix'] = ' !important';
			}

			blocksy_output_css_vars( $local_args );
		}
	}
}

function blocksy_adjust_color_lightness( $hexcolor, $percent ) {
	if ( strlen( $hexcolor ) < 6 ) {
		$hexcolor = $hexcolor[0] . $hexcolor[0] . $hexcolor[1] . $hexcolor[1] . $hexcolor[2] . $hexcolor[2];
	}

	$hexcolor = array_map(
		'hexdec',
		str_split(
			str_pad(
				str_replace(
					'#',
					'',
					$hexcolor
				),
				6,
				'0'
			),
			2
		)
	);

	foreach ( $hexcolor as $i => $color ) {
		$from           = $percent < 0 ? 0 : $color;
		$to             = $percent < 0 ? $color : 255;
		$pvalue         = ceil( ( $to - $from ) * $percent );
		$hexcolor[ $i ] = str_pad( dechex( $color + $pvalue ), 2, '0', STR_PAD_LEFT );
	}

	return '#' . implode( $hexcolor );
}
