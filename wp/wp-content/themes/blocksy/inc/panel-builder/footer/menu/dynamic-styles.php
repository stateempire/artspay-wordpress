<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- include scope, variables come from blocksy_theme_get_dynamic_styles()

if ( ! function_exists( 'blocksy_assemble_selector' ) ) {
	return;
}

// Items direction
$items_direction = blocksy_akg( 'menu_items_direction', $atts, 'horizontal' );

if ( 'horizontal' !== $items_direction ) {
	$items_direction = blocksy_expand_responsive_value( $items_direction );

	blocksy_output_responsive(
		array(
			'css'          => $css,
			'tablet_css'   => $tablet_css,
			'mobile_css'   => $mobile_css,
			'selector'     => blocksy_assemble_selector( $root_selector ),
			'variableName' => 'menu-item-width',
			'unit'         => '',
			'value'        => array(
				'desktop' => 'vertical' === $items_direction['desktop'] ? '100%' : 'initial',
				'tablet'  => 'vertical' === $items_direction['tablet'] ? '100%' : 'initial',
				'mobile'  => 'vertical' === $items_direction['mobile'] ? '100%' : 'initial',
			),
		)
	);
}

// Items spacing
$footer_menu_items_spacing = blocksy_akg( 'footerMenuItemsSpacing', $atts, 25 );

if ( 25 !== $footer_menu_items_spacing ) {
	blocksy_output_responsive(
		array(
			'css'          => $css,
			'tablet_css'   => $tablet_css,
			'mobile_css'   => $mobile_css,
			'selector'     => blocksy_assemble_selector( $root_selector ),
			'variableName' => 'menu-items-spacing',
			'value'        => $footer_menu_items_spacing,
		)
	);
}

// Horizontal alignment
blocksy_output_responsive(
	array(
		'css'          => $css,
		'tablet_css'   => $tablet_css,
		'mobile_css'   => $mobile_css,
		'selector'     => blocksy_assemble_selector(
			blocksy_mutate_selector(
				array(
					'selector'  => $root_selector,
					'operation' => 'replace-last',
					'to_add'    => $column_selector,
				)
			)
		),
		'variableName' => 'horizontal-alignment',
		'value'        => blocksy_akg( 'footerMenuAlignment', $atts, 'CT_CSS_SKIP_RULE' ),
		'unit'         => '',
	)
);

// Vertical alignment
blocksy_output_responsive(
	array(
		'css'          => $css,
		'tablet_css'   => $tablet_css,
		'mobile_css'   => $mobile_css,
		'selector'     => blocksy_assemble_selector(
			blocksy_mutate_selector(
				array(
					'selector'  => $root_selector,
					'operation' => 'replace-last',
					'to_add'    => $column_selector,
				)
			)
		),
		'variableName' => 'vertical-alignment',
		'value'        => blocksy_akg( 'footerMenuVerticalAlignment', $atts, 'CT_CSS_SKIP_RULE' ),
		'unit'         => '',
	)
);


// Top level font
blocksy_output_font_css(
	array(
		'font_value' => blocksy_akg(
			'footerMenuFont',
			$atts,
			blocksy_typography_default_values(
				array(
					'size'           => '12px',
					'variation'      => 'n7',
					'line-height'    => '1.3',
					'text-transform' => 'uppercase',
				)
			)
		),
		'css'        => $css,
		'tablet_css' => $tablet_css,
		'mobile_css' => $mobile_css,
		'selector'   => blocksy_assemble_selector(
			blocksy_mutate_selector(
				array(
					'selector'  => $root_selector,
					'operation' => 'suffix',
					'to_add'    => 'ul',
				)
			)
		),
	)
);


// Font color
blocksy_output_colors(
	array(
		'value'     => blocksy_akg( 'footerMenuFontColor', $atts ),
		'default'   => array(
			'default' => array( 'color' => 'var(--theme-text-color)' ),
			'hover'   => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
			'active'  => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
		),
		'css'       => $css,
		'variables' => array(
			'default' => array(
				'selector' => blocksy_assemble_selector(
					blocksy_mutate_selector(
						array(
							'selector'  => $root_selector,
							'operation' => 'suffix',
							'to_add'    => '> ul > li > a',
						)
					)
				),
				'variable' => 'theme-link-initial-color',
			),

			'hover'   => array(
				'selector' => blocksy_assemble_selector(
					blocksy_mutate_selector(
						array(
							'selector'  => $root_selector,
							'operation' => 'suffix',
							'to_add'    => '> ul > li > a',
						)
					)
				),
				'variable' => 'theme-link-hover-color',
			),

			'active'  => array(
				'selector' => blocksy_assemble_selector(
					blocksy_mutate_selector(
						array(
							'selector'  => $root_selector,
							'operation' => 'suffix',
							'to_add'    => '> ul > li > a',
						)
					)
				),
				'variable' => 'theme-link-active-color',
			),
		),
	)
);

// Top level margin
blocksy_output_spacing(
	array(
		'css'        => $css,
		'tablet_css' => $tablet_css,
		'mobile_css' => $mobile_css,
		'selector'   => blocksy_assemble_selector( $root_selector ),
		'important'  => true,
		'value'      => blocksy_default_akg(
			'footerMenuMargin',
			$atts,
			blocksy_spacing_value()
		),
	)
);
