<?php

namespace Blocksy;

class Colors {
	public function __construct() {
		add_filter( 'blocksy:options:colors:palette:palettes', array( $this, 'register_color_palettes' ) );

		add_action(
			'wp_ajax_blocksy_get_custom_palettes',
			function () {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error();
				}

				$option = get_option( 'blocksy_custom_palettes', array() );

				if ( empty( $option ) ) {
					$option = array();
				}

				if ( ! isset( $option['palettes'] ) ) {
					$option['palettes'] = array();
				}

				wp_send_json_success(
					array(
						'palettes' => $option['palettes'],
					)
				);
			}
		);

		add_action(
			'wp_ajax_blocksy_sync_custom_palettes',
			function () {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error();
				}

				if ( ! check_ajax_referer( 'ct-dashboard', 'nonce', false ) ) {
					wp_send_json_error();
				}

				$body = json_decode( file_get_contents( 'php://input' ), true );

				if ( ! isset( $body['palettes'] ) ) {
					wp_send_json_error();
				}

				$palettes = $this->sanitize_custom_palettes( $body['palettes'] );

				update_option(
					'blocksy_custom_palettes',
					array(
						'palettes' => $palettes,
					)
				);

				wp_send_json_success(
					array(
						'palettes' => $palettes,
					)
				);
			}
		);
	}

	public function sanitize_custom_palettes( $palettes ) {
		if ( ! is_array( $palettes ) ) {
			return array();
		}

		$result = array();

		foreach ( $palettes as $palette ) {
			$sanitized = $this->sanitize_custom_palette( $palette );

			if ( empty( $sanitized ) ) {
				continue;
			}

			$result[] = $sanitized;
		}

		return $result;
	}

	public function sanitize_color_palette( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'palette' => array(),
				'default' => array(),
			)
		);

		if ( ! is_array( $args['palette'] ) ) {
			return $args['default'];
		}

		$result = array();

		foreach ( $args['palette'] as $key => $value ) {
			if ( ! preg_match( '/^color\d+$/D', $key ) ) {
				continue;
			}

			$color = $this->sanitize_palette_color( $value );

			if ( ! $color && isset( $args['default'][ $key ] ) ) {
				$color = $args['default'][ $key ];
			}

			if ( ! $color ) {
				continue;
			}

			$result[ $key ] = $color;
		}

		return $result;
	}

	public function register_color_palettes() {
		$palettes = array(
			array(
				'id'     => 'palette-1',

				'color1' => array(
					'color' => '#2872fa',
				),

				'color2' => array(
					'color' => '#1559ed',
				),

				'color3' => array(
					'color' => '#3A4F66',
				),

				'color4' => array(
					'color' => '#192a3d',
				),

				'color5' => array(
					'color' => '#e1e8ed',
				),

				'color6' => array(
					'color' => '#f2f5f7',
				),

				'color7' => array(
					'color' => '#FAFBFC',
				),

				'color8' => array(
					'color' => '#ffffff',
				),
			),

			array(
				'id'     => 'palette-2',

				'color1' => array(
					'color' => '#007f5f',
				),

				'color2' => array(
					'color' => '#55a630',
				),

				'color3' => array(
					'color' => '#365951',
				),

				'color4' => array(
					'color' => '#192c27',
				),

				'color5' => array(
					'color' => '#E6F0EE',
				),

				'color6' => array(
					'color' => '#F2F7F6',
				),

				'color7' => array(
					'color' => '#FBFCFC',
				),

				'color8' => array(
					'color' => '#ffffff',
				),
			),

			array(
				'id'     => 'palette-3',

				'color1' => array(
					'color' => '#ff6310',
				),

				'color2' => array(
					'color' => '#fd7c47',
				),

				'color3' => array(
					'color' => '#687279',
				),

				'color4' => array(
					'color' => '#111518',
				),

				'color5' => array(
					'color' => '#E9EBEC',
				),

				'color6' => array(
					'color' => '#F4F5F6',
				),

				'color7' => array(
					'color' => '#ffffff',
				),

				'color8' => array(
					'color' => '#ffffff',
				),
			),

			array(
				'id'     => 'palette-4',

				'color1' => array(
					'color' => '#a8977b',
				),

				'color2' => array(
					'color' => '#7f715c',
				),

				'color3' => array(
					'color' => '#3f4245',
				),

				'color4' => array(
					'color' => '#111518',
				),

				'color5' => array(
					'color' => '#eaeaec',
				),

				'color6' => array(
					'color' => '#f4f4f5',
				),

				'color7' => array(
					'color' => '#ffffff',
				),

				'color8' => array(
					'color' => '#ffffff',
				),
			),

			array(
				'id'     => 'palette-5',

				'color1' => array(
					'color' => '#84a98c',
				),

				'color2' => array(
					'color' => '#52796f',
				),

				'color3' => array(
					'color' => '#cad2c5',
				),

				'color4' => array(
					'color' => '#84a98c',
				),

				'color5' => array(
					'color' => '#384b56',
				),

				'color6' => array(
					'color' => '#212b31',
				),

				'color7' => array(
					'color' => '#29363d',
				),

				'color8' => array(
					'color' => '#314149',
				),
			),

			array(
				'id'     => 'palette-6',

				'color1' => array(
					'color' => '#7456f1',
				),

				'color2' => array(
					'color' => '#5e3fde',
				),

				'color3' => array(
					'color' => '#4d5d6d',
				),

				'color4' => array(
					'color' => '#102136',
				),

				'color5' => array(
					'color' => '#E7EBEE',
				),

				'color6' => array(
					'color' => '#F3F5F7',
				),

				'color7' => array(
					'color' => '#FBFBFC',
				),

				'color8' => array(
					'color' => '#ffffff',
				),
			),

			array(
				'id'     => 'palette-7',

				'color1' => array(
					'color' => '#98c1d9',
				),

				'color2' => array(
					'color' => '#E84855',
				),

				'color3' => array(
					'color' => '#475671',
				),

				'color4' => array(
					'color' => '#293241',
				),

				'color5' => array(
					'color' => '#E7E9EF',
				),

				'color6' => array(
					'color' => '#f3f4f7',
				),

				'color7' => array(
					'color' => '#FBFBFC',
				),

				'color8' => array(
					'color' => '#ffffff',
				),
			),

			array(
				'id'     => 'palette-8',

				'color1' => array(
					'color' => '#ffcd05',
				),

				'color2' => array(
					'color' => '#fcb424',
				),

				'color3' => array(
					'color' => '#504e4a',
				),

				'color4' => array(
					'color' => '#0a0500',
				),

				'color5' => array(
					'color' => '#edeff2',
				),

				'color6' => array(
					'color' => '#f9fafb',
				),

				'color7' => array(
					'color' => '#FDFDFD',
				),

				'color8' => array(
					'color' => '#ffffff',
				),
			),

			array(
				'id'     => 'palette-9',

				'color1' => array(
					'color' => '#006466',
				),

				'color2' => array(
					'color' => '#065A60',
				),

				'color3' => array(
					'color' => '#7F8C9A',
				),

				'color4' => array(
					'color' => '#ffffff',
				),

				'color5' => array(
					'color' => '#1e2933',
				),

				'color6' => array(
					'color' => '#0F141A',
				),

				'color7' => array(
					'color' => '#141b22',
				),

				'color8' => array(
					'color' => '#1B242C',
				),
			),

			array(
				'id'     => 'palette-10',

				'color1' => array(
					'color' => '#00509d',
				),

				'color2' => array(
					'color' => '#003f88',
				),

				'color3' => array(
					'color' => '#828487',
				),

				'color4' => array(
					'color' => '#28292a',
				),

				'color5' => array(
					'color' => '#e8ebed',
				),

				'color6' => array(
					'color' => '#f4f5f6',
				),

				'color7' => array(
					'color' => '#FBFBFC',
				),

				'color8' => array(
					'color' => '#ffffff',
				),
			),

			array(
				'id'     => 'palette-11',

				'color1' => array(
					'color' => '#3eaf7c',
				),

				'color2' => array(
					'color' => '#33a370',
				),

				'color3' => array(
					'color' => '#415161',
				),

				'color4' => array(
					'color' => '#2c3e50',
				),

				'color5' => array(
					'color' => '#E2E7ED',
				),

				'color6' => array(
					'color' => '#edeff2',
				),

				'color7' => array(
					'color' => '#f8f9fb',
				),

				'color8' => array(
					'color' => '#ffffff',
				),
			),

			array(
				'id'     => 'palette-12',

				'color1' => array(
					'color' => '#FB7258',
				),

				'color2' => array(
					'color' => '#F74D67',
				),

				'color3' => array(
					'color' => '#6e6d76',
				),

				'color4' => array(
					'color' => '#0e0c1b',
				),

				'color5' => array(
					'color' => '#DFDFE2',
				),

				'color6' => array(
					'color' => '#F4F4F5',
				),

				'color7' => array(
					'color' => '#FBFBFB',
				),

				'color8' => array(
					'color' => '#ffffff',
				),
			),
		);

		return $palettes;
	}

	public function get_color_palette( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'id'      => 'colorPalette',
				'default' => array(
					'color1' => array(
						'color' => '#2872fa',
					),

					'color2' => array(
						'color' => '#1559ed',
					),

					'color3' => array(
						'color' => '#3A4F66',
					),

					'color4' => array(
						'color' => '#192a3d',
					),

					'color5' => array(
						'color' => '#e1e8ed',
					),

					'color6' => array(
						'color' => '#f2f5f7',
					),

					'color7' => array(
						'color' => '#FAFBFC',
					),

					'color8' => array(
						'color' => '#ffffff',
					),
				),
			)
		);

		$color_palette = $this->sanitize_color_palette(
			array(
				'palette' => blocksy_get_theme_mod( $args['id'], $args['default'] ),
				'default' => $args['default'],
			)
		);

		$result = array();

		foreach ( $color_palette as $key => $value ) {
			if ( strpos( $key, 'color' ) === false ) {
				continue;
			}

			$variable_name = str_replace( 'color', 'theme-palette-color-', $key );

			if ( isset( $value['variable'] ) && ! empty( $value['variable'] ) ) {
				$variable_name = $value['variable'];
			}

			if ( ! $value ) {
				$value = $args['default'][ $key ];
			}

			$title = blocksy_safe_sprintf(
				// translators: %s is the color number.
				__( 'Color %s', 'blocksy' ),
				str_replace( 'color', '', $key )
			);

			if ( isset( $value['title'] ) ) {
				$title = $value['title'];
			}

			$result[ $key ] = array(
				'id'       => $key,
				'slug'     => 'palette-color-' . str_replace( 'color', '', $key ),
				'color'    => $value['color'],
				'variable' => $variable_name,
				'title'    => $title,
			);
		}

		return $result;
	}

	private function sanitize_custom_palette( $palette ) {
		if ( ! is_array( $palette ) ) {
			return null;
		}

		$result = array();

		if ( isset( $palette['id'] ) && is_string( $palette['id'] ) ) {
			$result['id'] = sanitize_text_field( $palette['id'] );
		}

		$has_colors = false;

		foreach ( $palette as $key => $value ) {
			if ( ! preg_match( '/^color\d+$/D', $key ) ) {
				continue;
			}

			$color = $this->sanitize_palette_color( $value );

			if ( ! $color ) {
				return null;
			}

			$result[ $key ] = $color;

			$has_colors = true;
		}

		if ( ! $has_colors ) {
			return null;
		}

		return $result;
	}

	private function sanitize_palette_color( $value ) {
		if (
			! is_array( $value )
			||
			! isset( $value['color'] )
			||
			! blocksy_validate_color( $value['color'] )
		) {
			return null;
		}

		$color = array(
			'color' => $value['color'],
		);

		if ( isset( $value['title'] ) && is_string( $value['title'] ) ) {
			$color['title'] = sanitize_text_field( $value['title'] );
		}

		if (
			isset( $value['variable'] )
			&&
			is_string( $value['variable'] )
			&&
			preg_match( '/^[\w-]+$/D', $value['variable'] )
		) {
			$color['variable'] = $value['variable'];
		}

		return $color;
	}
}
