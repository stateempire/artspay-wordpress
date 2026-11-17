<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- include scope, variables come from blocksy_theme_get_dynamic_styles()

if ( blocksy_get_theme_mod( $prefix . '_has_comments', 'yes' ) !== 'yes' ) {
	return;
}

$comments_narrow_width = blocksy_get_theme_mod( $prefix . '_comments_narrow_width', 750 );

if ( 750 !== $comments_narrow_width ) {
	$css->put(
		blocksy_prefix_selector( '.ct-comments-container', $prefix ),
		'--theme-narrow-container-max-width: ' . $comments_narrow_width . 'px'
	);
}

blocksy_output_colors(
	array(
		'value'     => blocksy_get_theme_mod(
			$prefix . '_comments_author_name_font_color',
			array(
				'default' => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
				'hover'   => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
			)
		),
		'default'   => array(
			'default' => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
			'hover'   => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
		),
		'css'       => $css,
		'variables' => array(
			'default' => array(
				'selector' => blocksy_prefix_selector( '.ct-comments .ct-comment-author', $prefix ),
				'variable' => 'theme-heading-color',
			),

			'hover'   => array(
				'selector' => blocksy_prefix_selector( '.ct-comments .ct-comment-author', $prefix ),
				'variable' => 'theme-link-hover-color',
			),
		),
	)
);


blocksy_output_colors(
	array(
		'value'     => blocksy_get_theme_mod(
			$prefix . '_comments_font_color',
			array(
				'default' => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
				'hover'   => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
			)
		),
		'default'   => array(
			'default' => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
			'hover'   => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
		),
		'css'       => $css,
		'variables' => array(
			'default' => array(
				'selector' => blocksy_prefix_selector( '.ct-comments', $prefix ),
				'variable' => 'theme-text-color',
			),

			'hover'   => array(
				'selector' => blocksy_prefix_selector( '.ct-comments', $prefix ),
				'variable' => 'theme-link-hover-color',
			),
		),
	)
);

blocksy_output_background_css(
	array(
		'selector' => blocksy_prefix_selector( '.ct-comments-container', $prefix ),
		'css'      => $css,
		'value'    => blocksy_get_theme_mod(
			$prefix . '_comments_background',
			blocksy_background_default_value(
				array(
					'backgroundColor' => array(
						'default' => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword(),
						),
					),
				)
			)
		),
	)
);
