<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- include scope, variables come from blocksy_theme_get_dynamic_styles()

$post_type = get_current_screen()->post_type;

$post_id = blocksy_get_editor_post_id();

$prefix = blocksy_manager()->screen->get_admin_prefix( $post_type );

$post_atts = blocksy_get_post_options( $post_id );

$background_source = blocksy_default_akg(
	'background',
	$post_atts,
	blocksy_background_default_value(
		array(
			'backgroundColor' => array(
				'default' => array(
					'color' => Blocksy_Css_Injector::get_skip_rule_keyword(),
				),
			),
		)
	)
);

if ( 'ct_content_block' === $post_type ) {
	$template_type    = get_post_meta( $post_id, 'template_type', true );
	$template_subtype = blocksy_akg( 'template_subtype', $post_atts, 'card' );

	if ( 'popup' === $template_type ) {
		$background_source = blocksy_default_akg(
			'popup_background',
			$post_atts,
			blocksy_background_default_value(
				array(
					'backgroundColor' => array(
						'default' => array(
							'color' => '#ffffff',
						),
					),
				)
			)
		);
	}

	if ( 'popup' !== $template_type ) {
		$default_content_block_structure = 'yes';

		if ( 'hook' === $template_type ) {
			$default_content_block_structure = 'no';
		}

		$has_content_block_structure = blocksy_akg(
			'has_content_block_structure',
			$post_atts,
			$default_content_block_structure
		);

		if (
			'yes' !== $has_content_block_structure
			||
			'archive' === $template_type && 'card' === $template_subtype
		) {
			$background_source = blocksy_background_default_value(
				array(
					'backgroundColor' => array(
						'default' => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword(),
						),
					),
				)
			);
		}
	}
}

$strategy = array(
	'strategy' => 'customizer',
	'prefix'   => $prefix,
);

$maybe_matching_template = blocksy_manager()->companion->get_content_block_that_matches(
	array(
		'template_type'             => 'single',
		'template_subtype'          => 'canvas',
		'match_conditions_strategy' => $prefix,
	)
);

if ( $maybe_matching_template ) {
	$strategy = array(
		'strategy' => blocksy_get_post_options( $maybe_matching_template ),
	);
}

if (
	isset( $background_source['background_type'] )
	&&
	'color' === $background_source['background_type']
	&&
	isset( $background_source['backgroundColor']['default']['color'] )
	&&
	Blocksy_Css_Injector::get_skip_rule_keyword() === $background_source['backgroundColor']['default']['color']
) {
	$background_source = blocksy_akg_or_customizer(
		'background',
		$strategy,
		blocksy_background_default_value(
			array(
				'backgroundColor' => array(
					'default' => array(
						'color' => Blocksy_Css_Injector::get_skip_rule_keyword(),
					),
				),
			)
		)
	);

	if (
		isset( $background_source['background_type'] )
		&&
		'color' === $background_source['background_type']
		&&
		isset( $background_source['backgroundColor']['default']['color'] )
		&&
		Blocksy_Css_Injector::get_skip_rule_keyword() === $background_source['backgroundColor']['default']['color']
	) {
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
	}
}

blocksy_output_background_css(
	array(
		'selector'                => '.block-editor-iframe__html',
		'css'                     => $css,
		'tablet_css'              => $tablet_css,
		'mobile_css'              => $mobile_css,
		'value'                   => $background_source,
		'responsive'              => true,
		'important'               => true,
		'forced_background_image' => true,
	)
);
