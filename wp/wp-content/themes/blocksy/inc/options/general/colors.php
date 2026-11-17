<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- include scope, variables come from blocksy_get_variables_from_file()
/**
 * Colors options
 *
 * @copyright 2019-present Creative Themes
 * @license   http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @package   Blocksy
 */

$options = array( // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	'colors_section_options' => array(
		'type'          => 'ct-options',
		'setting'       => array( 'transport' => 'postMessage' ),
		'inner-options' => array(

			array(
				'colorPalette' => array(
					'label'       => __( 'Global Color Palette', 'blocksy' ),
					'type'        => 'ct-color-palettes-picker',
					'design'      => 'block',
					'desc'        => blocksy_safe_sprintf(
						// translators: The interpolations addes a html link around the word.
						__( 'Learn more about palettes and colors %shere%s.', 'blocksy' ), // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText -- keeps the translated string
						'<a href="https://creativethemes.com/blocksy/docs/general-options/colors/" target="_blank">',
						'</a>'
					),
					'setting'     => array(
						'transport'         => 'postMessage',
						'sanitize_callback' => function ( $input, $setting ) {
							return blocksy_manager()->colors->sanitize_color_palette(
								array(
									'palette' => $input,
									'default' => $setting->default,
								)
							);
						},
					),
					'predefined'  => true,
					'wrapperAttr' => array(
						'data-label' => 'heading-label',
					),

					'value'       => array(
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

					/**
					 * Filters the predefined palettes offered by the global
					 * color palette option.
					 *
					 * @since 2.1.16
					 *
					 * @param array $palettes List of palette definitions.
					 */
					'palettes'    => apply_filters( 'blocksy:options:colors:palette:palettes', array() ),
				),

			),

			/**
			 * Filters the options appended after the global color palette.
			 *
			 * @since 2.0.1
			 *
			 * @param array $options List of option definitions.
			 */
			apply_filters( 'blocksy:options:colors:palette:after', array() ),

			blocksy_rand_md5() => array(
				'type'  => 'ct-title',
				'label' => __( 'Global Colors', 'blocksy' ),
			),

			'fontColor'        => array(
				'label'           => __( 'Base Text', 'blocksy' ),
				'type'            => 'ct-color-picker',
				'skipEditPalette' => true,
				'design'          => 'inline',
				'setting'         => array( 'transport' => 'postMessage' ),

				'value'           => array(
					'default' => array(
						'color' => 'var(--theme-palette-color-3)',
					),
				),

				'pickers'         => array(
					array(
						'title' => __( 'Initial', 'blocksy' ),
						'id'    => 'default',
					),
				),
			),

			'linkColor'        => array(
				'label'           => __( 'Links', 'blocksy' ),
				'type'            => 'ct-color-picker',
				'skipEditPalette' => true,
				'design'          => 'inline',
				'setting'         => array( 'transport' => 'postMessage' ),

				'value'           => array(
					'default' => array(
						'color' => 'var(--theme-palette-color-1)',
					),

					'hover'   => array(
						'color' => 'var(--theme-palette-color-2)',
					),
				),

				'pickers'         => array(
					array(
						'title' => __( 'Initial', 'blocksy' ),
						'id'    => 'default',
					),

					array(
						'title' => __( 'Hover', 'blocksy' ),
						'id'    => 'hover',
					),
				),
			),

			'selectionColor'   => array(
				'label'           => __( 'Text Selection', 'blocksy' ),
				'type'            => 'ct-color-picker',
				'skipEditPalette' => true,
				'design'          => 'inline',
				'setting'         => array( 'transport' => 'postMessage' ),

				'value'           => array(
					'default' => array(
						'color' => '#ffffff',
					),

					'hover'   => array(
						'color' => 'var(--theme-palette-color-1)',
					),
				),

				'pickers'         => array(
					array(
						'title' => __( 'Text', 'blocksy' ),
						'id'    => 'default',
					),

					array(
						'title' => __( 'Background', 'blocksy' ),
						'id'    => 'hover',
					),
				),
			),

			'border_color'     => array(
				'label'   => __( 'Borders', 'blocksy' ),
				'type'    => 'ct-color-picker',
				'design'  => 'inline',
				'setting' => array( 'transport' => 'postMessage' ),

				'value'   => array(
					'default' => array(
						'color' => 'var(--theme-palette-color-5)',
					),
				),

				'pickers' => array(
					array(
						'title' => __( 'Initial', 'blocksy' ),
						'id'    => 'default',
					),
				),
			),

			'headingColor'     => array(
				'label'           => __( 'All Headings (H1 - H6)', 'blocksy' ),
				'type'            => 'ct-color-picker',
				'skipEditPalette' => true,
				'divider'         => 'top',
				'design'          => 'inline',
				'setting'         => array( 'transport' => 'postMessage' ),

				'value'           => array(
					'default' => array(
						'color' => 'var(--theme-palette-color-4)',
					),
				),

				'pickers'         => array(
					array(
						'title' => __( 'Initial', 'blocksy' ),
						'id'    => 'default',
					),
				),
			),

			'heading_1_color'  => array(
				'label'           => __( 'Heading 1 (H1)', 'blocksy' ),
				'type'            => 'ct-color-picker',
				'skipEditPalette' => true,
				'design'          => 'inline',
				'setting'         => array( 'transport' => 'postMessage' ),

				'value'           => array(
					'default' => array(
						'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
					),
				),

				'pickers'         => array(
					array(
						'title'   => __( 'Initial', 'blocksy' ),
						'id'      => 'default',
						'inherit' => 'var(--theme-headings-color)',
					),
				),
			),

			'heading_2_color'  => array(
				'label'           => __( 'Heading 2 (H2)', 'blocksy' ),
				'type'            => 'ct-color-picker',
				'skipEditPalette' => true,
				'design'          => 'inline',
				'setting'         => array( 'transport' => 'postMessage' ),

				'value'           => array(
					'default' => array(
						'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
					),
				),

				'pickers'         => array(
					array(
						'title'   => __( 'Initial', 'blocksy' ),
						'id'      => 'default',
						'inherit' => 'var(--theme-headings-color)',
					),
				),
			),

			'heading_3_color'  => array(
				'label'           => __( 'Heading 3 (H3)', 'blocksy' ),
				'type'            => 'ct-color-picker',
				'skipEditPalette' => true,
				'design'          => 'inline',
				'setting'         => array( 'transport' => 'postMessage' ),

				'value'           => array(
					'default' => array(
						'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
					),
				),

				'pickers'         => array(
					array(
						'title'   => __( 'Initial', 'blocksy' ),
						'id'      => 'default',
						'inherit' => 'var(--theme-headings-color)',
					),
				),
			),

			'heading_4_color'  => array(
				'label'           => __( 'Heading 4 (H4)', 'blocksy' ),
				'type'            => 'ct-color-picker',
				'skipEditPalette' => true,
				'design'          => 'inline',
				'setting'         => array( 'transport' => 'postMessage' ),

				'value'           => array(
					'default' => array(
						'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
					),
				),

				'pickers'         => array(
					array(
						'title'   => __( 'Initial', 'blocksy' ),
						'id'      => 'default',
						'inherit' => 'var(--theme-headings-color)',
					),
				),
			),

			'heading_5_color'  => array(
				'label'           => __( 'Heading 5 (H5)', 'blocksy' ),
				'type'            => 'ct-color-picker',
				'skipEditPalette' => true,
				'design'          => 'inline',
				'setting'         => array( 'transport' => 'postMessage' ),

				'value'           => array(
					'default' => array(
						'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
					),
				),

				'pickers'         => array(
					array(
						'title'   => __( 'Initial', 'blocksy' ),
						'id'      => 'default',
						'inherit' => 'var(--theme-headings-color)',
					),
				),
			),

			'heading_6_color'  => array(
				'label'           => __( 'Heading 6 (H6)', 'blocksy' ),
				'type'            => 'ct-color-picker',
				'skipEditPalette' => true,
				'design'          => 'inline',
				'setting'         => array( 'transport' => 'postMessage' ),

				'value'           => array(
					'default' => array(
						'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
					),
				),

				'pickers'         => array(
					array(
						'title'   => __( 'Initial', 'blocksy' ),
						'id'      => 'default',
						'inherit' => 'var(--theme-headings-color)',
					),
				),
			),

			'site_background'  => array(
				'label'      => __( 'Site Background', 'blocksy' ),
				'type'       => 'ct-background',
				'design'     => 'block:right',
				'responsive' => true,
				'divider'    => 'top',
				'setting'    => array( 'transport' => 'postMessage' ),
				'value'      => blocksy_background_default_value(
					array(
						'backgroundColor' => array(
							'default' => array(
								'color' => 'var(--theme-palette-color-7)',
							),
						),
					)
				),
			),

		),
	),
);
