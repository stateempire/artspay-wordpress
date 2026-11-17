<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- include scope, variables come from blocksy_get_options()
/**
 * Store notice options
 *
 * @copyright 2019-present Creative Themes
 * @license   http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @package   Blocksy
 */

// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Returned by the options loader.
$options = array(

	'woocommerce_demo_store' => array(
		'label'          => __( 'Store Notice', 'blocksy' ),
		'type'           => 'ct-panel',
		'switch'         => true,
		'value'          => 'no',
		'switchBehavior' => 'boolean',
		'setting'        => array(
			'type'       => 'option',
			'capability' => 'manage_woocommerce',
		),
		'inner-options'  => array(

			blocksy_rand_md5() => array(
				'title'   => __( 'General', 'blocksy' ),
				'type'    => 'tab',
				'options' => array(

					'woocommerce_demo_store_notice' => array(
						'label'               => false,
						'type'                => 'textarea',
						'value'               => __( 'This is a demo store for testing purposes &mdash; no orders shall be fulfilled.', 'blocksy' ),
						'setting'             => array(
							'type'       => 'option',
							'transport'  => 'postMessage',
							'capability' => 'manage_woocommerce',
						),
						'disableRevertButton' => true,
					),

					'store_notice_position'         => array(
						'type'    => 'ct-radio',
						'label'   => __( 'Notice Position', 'blocksy' ),
						'value'   => 'bottom',
						'view'    => 'text',
						// 'disableRevertButton' => true,
						'setting' => array( 'transport' => 'postMessage' ),
						'choices' => array(
							'top'    => __( 'Top', 'blocksy' ),
							'bottom' => __( 'Bottom', 'blocksy' ),
						),
					),

				),
			),

			blocksy_rand_md5() => array(
				'title'   => __( 'Design', 'blocksy' ),
				'type'    => 'tab',
				'options' => array(

					'wooNoticeContent'    => array(
						'label'           => __( 'Notice Font Color', 'blocksy' ),
						'type'            => 'ct-color-picker',
						'design'          => 'inline',
						'divider'         => 'top',
						'skipEditPalette' => true,
						'setting'         => array( 'transport' => 'postMessage' ),

						'value'           => array(
							'default' => array(
								'color' => '#ffffff',
							),
						),

						'pickers'         => array(
							array(
								'title' => __( 'Initial', 'blocksy' ),
								'id'    => 'default',
							),
						),
					),

					'wooNoticeBackground' => array(
						'label'           => __( 'Notice Background Color', 'blocksy' ),
						'type'            => 'ct-color-picker',
						'design'          => 'inline',
						'skipEditPalette' => true,
						'setting'         => array( 'transport' => 'postMessage' ),

						'value'           => array(
							'default' => array(
								'color' => 'var(--theme-palette-color-1)',
							),
						),

						'pickers'         => array(
							array(
								'title' => __( 'Initial', 'blocksy' ),
								'id'    => 'default',
							),
						),
					),

				),
			),

		),
	),

);
