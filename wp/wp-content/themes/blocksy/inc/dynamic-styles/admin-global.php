<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- include scope, variables come from blocksy_theme_get_dynamic_styles()

if ( ! isset( $selector ) ) {
	$selector = ':root';
}

$max_site_width = blocksy_get_theme_mod( 'maxSiteWidth', 1290 );
$css->put(
	':root',
	'--theme-normal-container-max-width: ' . $max_site_width . 'px'
);

$narrow_container_width = blocksy_get_theme_mod( 'narrowContainerWidth', 750 );
$css->put(
	':root',
	'--theme-narrow-container-max-width: ' . $narrow_container_width . 'px'
);

$wide_offset = blocksy_get_theme_mod( 'wideOffset', 130 );
$css->put(
	':root',
	'--theme-wide-offset: ' . $wide_offset . 'px'
);

$content_spacing_map = array(
	'none'        => '0px',
	'compact'     => '0.8em',
	'comfortable' => '1.5em',
	'spacious'    => '2em',
);

$content_spacing = blocksy_get_theme_mod( 'contentSpacing', 'comfortable' );

$content_spacing_result = isset(
	$content_spacing_map[ $content_spacing ]
) ? $content_spacing_map[ $content_spacing ] : $content_spacing_map['comfortable'];

$css->put( ':root', '--theme-content-spacing: ' . $content_spacing_result );

if ( 'none' === $content_spacing ) {
	$css->put( ':root', '--has-theme-content-spacing: 0' );
}


blocksy_theme_get_dynamic_styles(
	array(
		'name'       => 'admin/colors',
		'css'        => $css,
		'mobile_css' => $mobile_css,
		'tablet_css' => $tablet_css,
		'context'    => $context,
		'chunk'      => 'admin',
		'selector'   => $selector,
	)
);

global $wp_customize;

if (
	function_exists( 'get_current_screen' )
	&&
	get_current_screen()
	&&
	get_current_screen()->is_block_editor()
	||
	// Allow styles in customizer for the widget area blocks
	$wp_customize
) {
	if ( get_current_screen()->base === 'widgets' ) {
		$background_source = blocksy_get_theme_mod(
			'site_background',
			blocksy_background_default_value(
				array(
					'backgroundColor' => array(
						'default' => array(
							'color' => 'var(--theme-palette-color-7)',
						),
					),
				)
			)
		);

		$background_source = blocksy_expand_responsive_value( $background_source );

		$background_source['desktop']['background_image'] = array(
			'attachment_id' => null,
			'x'             => 0,
			'y'             => 0,
		);

		blocksy_output_background_css(
			array(
				'selector'   => '.wp-block-widget-area__inner-blocks',
				'css'        => $css,
				'value'      => $background_source['desktop'],
				'responsive' => false,
				'important'  => true,
			)
		);
	}

	if ( get_current_screen()->base === 'post' ) {
		blocksy_theme_get_dynamic_styles(
			array(
				'name'       => 'admin/editor',
				'css'        => $css,
				'mobile_css' => $mobile_css,
				'tablet_css' => $tablet_css,
				'context'    => $context,
				'chunk'      => 'admin',
			)
		);
	}

	blocksy_theme_get_dynamic_styles(
		array(
			'name'       => 'global/typography',
			'css'        => $css,
			'mobile_css' => $mobile_css,
			'tablet_css' => $tablet_css,
			'context'    => 'inline',
			'chunk'      => 'admin',
		)
	);

	blocksy_output_responsive(
		array(
			'css'          => $css,
			'tablet_css'   => $tablet_css,
			'mobile_css'   => $mobile_css,
			'selector'     => ':root',
			'variableName' => 'theme-button-min-height',
			'value'        => blocksy_get_theme_mod( 'buttonMinHeight', 40 ),
		)
	);

	blocksy_output_border(
		array(
			'css'                     => $css,
			'selector'                => ':root',
			'variableName'            => 'theme-button-border',
			'secondColorVariableName' => 'theme-button-border-hover-color',
			'value'                   => get_theme_mod( 'buttonBorder' ),
			'default'                 => array(
				'width'       => 1,
				'style'       => 'none',
				'color'       => array(
					'color' => 'rgba(224, 229, 235, 0.5)',
				),
				'secondColor' => array(
					'color' => 'rgba(224, 229, 235, 0.7)',
				),
			),
		)
	);

	blocksy_output_spacing(
		array(
			'css'         => $css,
			'tablet_css'  => $tablet_css,
			'mobile_css'  => $mobile_css,
			'selector'    => ':root',
			'property'    => 'theme-button-border-radius',
			'value'       => blocksy_get_theme_mod(
				'buttonRadius',
				blocksy_spacing_value()
			),
			'empty_value' => 3,
		)
	);

	blocksy_output_spacing(
		array(
			'css'        => $css,
			'tablet_css' => $tablet_css,
			'mobile_css' => $mobile_css,
			'selector'   => ':root',
			'property'   => 'theme-button-padding',
			'value'      => blocksy_get_theme_mod(
				'buttonPadding',
				blocksy_spacing_value(
					array(
						'top'    => '5px',
						'left'   => '20px',
						'right'  => '20px',
						'bottom' => '5px',
					)
				)
			),
		)
	);
}

$post_id = blocksy_get_editor_post_id();

if ( $post_id ) {
	$post_atts = blocksy_get_post_options( $post_id );

	$template_type    = get_post_meta( $post_id, 'template_type', true );
	$template_subtype = blocksy_akg( 'template_subtype', $post_atts, 'card' );

	if ( 'archive' === $template_type && 'card' === $template_subtype ) {
		$source = array(
			'strategy' => $post_atts,
		);

		$template_editor_width_source = blocksy_akg_or_customizer(
			'template_editor_width_source',
			$source,
			'small'
		);

		$template_editor_width = blocksy_akg_or_customizer(
			'template_editor_width',
			$source,
			'1290'
		);

		if ( 'small' === $template_editor_width_source ) {
			$template_editor_width = 500;
		}

		if ( 'medium' === $template_editor_width_source ) {
			$template_editor_width = 900;
		}

		$css->put(
			':root',
			'--theme-block-max-width: ' . $template_editor_width . 'px !important'
		);
	}
}
