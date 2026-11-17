<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- include scope, variables come from blocksy_theme_get_dynamic_styles()

blocksy_theme_get_dynamic_styles(
	array(
		'name'       => 'admin/editor-background',
		'css'        => $css,
		'mobile_css' => $mobile_css,
		'tablet_css' => $tablet_css,
		'context'    => $context,
		'chunk'      => 'admin',
	)
);

$post_type = get_current_screen()->post_type;

$post_id = blocksy_get_editor_post_id();

$prefix = blocksy_manager()->screen->get_admin_prefix( $post_type );

$post_atts = blocksy_get_post_options( $post_id );

$page_structure = blocksy_default_akg(
	'page_structure_type',
	$post_atts,
	'default'
);

$content_block_atts = null;

$has_content_block_structure = 'no';
$template_type               = null;

if ( 'ct_content_block' === $post_type ) {
	$default_content_block_structure = 'yes';
	$template_type                   = get_post_meta( $post_id, 'template_type', true );

	if ( 'hook' === $template_type || 'popup' === $template_type ) {
		$default_content_block_structure = 'no';
	}

	$has_content_block_structure = blocksy_akg(
		'has_content_block_structure',
		$post_atts,
		$default_content_block_structure
	);
}

$maybe_matching_template = blocksy_manager()->companion->get_content_block_that_matches(
	array(
		'template_type'             => 'single',
		'template_subtype'          => 'canvas',
		'match_conditions_strategy' => $prefix,
	)
);

if ( $maybe_matching_template ) {
	$content_block_atts = blocksy_get_post_options( $maybe_matching_template );
}

if ( 'default' === $page_structure ) {
	$page_structure = blocksy_get_theme_mod(
		$prefix . '_structure',
		( 'single_blog_post' === $prefix ) ? 'type-3' : 'type-4'
	);

	if ( $content_block_atts ) {
		$page_structure = blocksy_default_akg(
			'content_block_structure',
			$content_block_atts,
			'type-4'
		);
	}
}

if ( 'ct_content_block' === $post_type ) {
	if ( 'yes' === $has_content_block_structure ) {
		$page_structure = blocksy_default_akg(
			'content_block_structure',
			$post_atts,
			'type-4'
		);
	}
}

if ( 'type-4' === $page_structure ) {
	$css->put(
		'.editor-styles-wrapper',
		'--theme-block-max-width: var(--theme-normal-container-max-width)'
	);

	$css->put(
		'.editor-styles-wrapper',
		'--theme-block-wide-max-width: calc(var(--theme-normal-container-max-width) + var(--theme-wide-offset) * 2)'
	);
} else {
	$css->put(
		'.editor-styles-wrapper',
		'--theme-block-max-width: var(--theme-narrow-container-max-width)'
	);

	$css->put(
		'.editor-styles-wrapper',
		'--theme-block-wide-max-width: calc(var(--theme-narrow-container-max-width) + var(--theme-wide-offset) * 2)'
	);
}

$source = array(
	'strategy' => $post_atts,
);

if (
	'inherit' === blocksy_default_akg(
		'content_style_source',
		$post_atts,
		'inherit'
	)
	&&
	'ct_content_block' !== $post_type
) {
	$source = array(
		'prefix'   => $prefix,
		'strategy' => 'customizer',
	);

	if ( $content_block_atts ) {
		$source = array(
			'strategy' => $content_block_atts,
		);
	}
}

$has_boxed = blocksy_akg_or_customizer(
	'content_style',
	$source,
	blocksy_get_content_style_default( $prefix )
);

if ( 'ct_content_block' === $post_type ) {
	$template_subtype = blocksy_akg( 'template_subtype', $post_atts, 'card' );

	if (
		'yes' !== $has_content_block_structure
		||
		'archive' === $template_type && 'card' === $template_subtype
	) {
		$has_boxed = blocksy_get_content_style_default( $prefix );
	}
}

blocksy_theme_get_dynamic_styles(
	array(
		'name'       => 'admin/content-area-styles',
		'css'        => $css,
		'mobile_css' => $mobile_css,
		'tablet_css' => $tablet_css,
		'context'    => $context,
		'chunk'      => 'admin',
		'has_boxed'  => $has_boxed,
		'source'     => $source,
	)
);

// form styles
$forms_type = blocksy_get_theme_mod( 'forms_type', 'classic-forms' );

if ( 'classic-forms' === $forms_type ) {
	$css->put(
		'.editor-styles-wrapper',
		'--has-classic-forms: var(--true)'
	);

	$css->put(
		'.editor-styles-wrapper',
		'--has-modern-forms: var(--false)'
	);
} else {
	$css->put(
		'.editor-styles-wrapper',
		'--has-classic-forms: var(--false)'
	);

	$css->put(
		'.editor-styles-wrapper',
		'--has-modern-forms: var(--true)'
	);
}

blocksy_output_colors(
	array(
		'value'     => blocksy_get_theme_mod( 'formTextColor' ),
		'default'   => array(
			'default' => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
			'focus'   => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ) ),
		),
		'css'       => $css,
		'variables' => array(
			'default' => array(
				'selector' => '.editor-styles-wrapper',
				'variable' => 'theme-form-text-initial-color',
			),

			'focus'   => array(
				'selector' => '.editor-styles-wrapper',
				'variable' => 'theme-form-text-focus-color',
			),
		),
	)
);

$form_font_size = blocksy_get_theme_mod( 'formFontSize', 16 );

if ( 16 !== $form_font_size ) {
	$css->put( '.editor-styles-wrapper', '--theme-form-font-size: ' . $form_font_size . 'px' );
}

blocksy_output_colors(
	array(
		'value'     => blocksy_get_theme_mod( 'formBackgroundColor' ),
		'default'   => array(
			'default' => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword() ),
			'focus'   => array( 'color' => Blocksy_Css_Injector::get_skip_rule_keyword() ),
		),
		'css'       => $css,
		'variables' => array(
			'default' => array(
				'selector' => '.editor-styles-wrapper',
				'variable' => 'theme-form-field-background-initial-color',
			),

			'focus'   => array(
				'selector' => '.editor-styles-wrapper',
				'variable' => 'theme-form-field-background-focus-color',
			),
		),
	)
);

$form_input_height = blocksy_get_theme_mod( 'formInputHeight', 40 );

if ( 40 !== $form_input_height ) {
	$css->put( '.editor-styles-wrapper', '--theme-form-field-height: ' . $form_input_height . 'px' );
}

$form_field_border_radius = blocksy_get_theme_mod( 'formFieldBorderRadius', 3 );

if ( 3 !== $form_field_border_radius ) {
	$css->put( '.editor-styles-wrapper', '--theme-form-field-border-radius: ' . $form_field_border_radius . 'px' );
}

blocksy_output_colors(
	array(
		'value'     => blocksy_get_theme_mod( 'formBorderColor' ),
		'default'   => array(
			'default' => array( 'color' => 'var(--theme-border-color)' ),
			'focus'   => array( 'color' => 'var(--theme-palette-color-1)' ),
		),
		'css'       => $css,
		'variables' => array(
			'default' => array(
				'selector' => '.editor-styles-wrapper',
				'variable' => 'theme-form-field-border-initial-color',
			),

			'focus'   => array(
				'selector' => '.editor-styles-wrapper',
				'variable' => 'theme-form-field-border-focus-color',
			),
		),
	)
);

$form_border_size = blocksy_get_theme_mod( 'formBorderSize', 1 );

if ( 'classic-forms' === $forms_type ) {
	if ( 1 !== $form_border_size ) {
		$css->put(
			'.editor-styles-wrapper',
			'--theme-form-field-border-width: ' . $form_border_size . 'px'
		);
	}
} else {
	$css->put(
		'.editor-styles-wrapper',
		'--theme-form-field-border-width: 0 0 ' . $form_border_size . 'px 0'
	);

	$css->put(
		'.editor-styles-wrapper',
		'--form-selection-control-border-width: ' . $form_border_size . 'px'
	);
}

blocksy_output_colors(
	array(
		'value'     => blocksy_get_theme_mod( 'radioCheckboxColor' ),
		'default'   => array(
			'default' => array( 'color' => 'var(--theme-border-color)' ),
			'accent'  => array( 'color' => 'var(--theme-palette-color-1)' ),
		),
		'css'       => $css,
		'variables' => array(
			'default' => array(
				'selector' => '.editor-styles-wrapper',
				'variable' => 'theme-form-selection-field-initial-color',
			),

			'accent'  => array(
				'selector' => '.editor-styles-wrapper',
				'variable' => 'theme-form-selection-field-active-color',
			),
		),
	)
);
