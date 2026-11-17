<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- include scope, variables come from blocksy_get_variables_from_file()
/**
 * Page title options
 *
 * @copyright 2019-present Creative Themes
 * @license   http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @package   Blocksy
 */

if ( ! isset( $has_hero_type ) ) {
	$has_hero_type = true;
}

if ( ! isset( $location_name ) ) {
	$location_name = '';
}

if ( ! isset( $enabled_label ) ) {
	$enabled_label = __( 'Page Title', 'blocksy' );
}

if ( ! isset( $design_options ) ) {
	$design_options = array();
}

if ( ! isset( $enabled_default ) ) {
	$enabled_default = 'yes';
}

if ( ! isset( $is_cpt ) ) {
	$is_cpt = false;
}

if ( ! isset( $has_hero_elements ) ) {
	$has_hero_elements = true;
}

if ( ! isset( $is_bbpress ) ) {
	$is_bbpress = false;
}

if ( ! isset( $is_tutorlms ) ) {
	$is_tutorlms = false;
}

if ( ! isset( $is_eventkoi ) ) {
	$is_eventkoi = false;
}

if ( ! isset( $is_vs_portfolio ) ) {
	$is_vs_portfolio = false;
}

if ( ! isset( $is_tribe_events ) ) {
	$is_tribe_events = false;
}

if ( ! isset( $is_woo ) ) {
	$is_woo = false;
}

if ( ! isset( $is_page ) ) {
	$is_page = false;
}

if ( ! isset( $is_author ) ) {
	$is_author = false;
}

if ( ! isset( $has_default ) ) {
	$has_default = false;
}

if ( ! isset( $is_home ) ) {
	$is_home = false;
}

if ( ! isset( $is_single ) ) {
	$is_single = false;
}

if ( ! isset( $is_search ) ) {
	$is_search = false;
}

if ( ! isset( $is_archive ) ) {
	$is_archive = false;
}

if ( ! isset( $prefix ) ) {
	$prefix = '';
} else {
	$prefix = $prefix . '_';
}

$computed_cpt = $is_cpt || 'product_' === $prefix ? trim( $prefix, '_' ) : false;

if ( $is_cpt && ! $prefix ) {
	$computed_cpt = $is_cpt;
}

/**
 * Filters whether archive screens get the page title (hero) options.
 *
 * @since 1.8.4
 *
 * @param bool $archives_have_hero Whether archives have hero options. Default false.
 */
$archives_have_hero = apply_filters(
	'blocksy:options:page-title:archives-have-hero',
	false
);

$custom_description_layer_name = __( 'Description', 'blocksy' );

if ( $is_author ) {
	$custom_description_layer_name = __( 'Bio', 'blocksy' );
}

if (
	(
		$is_single || $is_home
	) && ! $is_bbpress
) {
	$custom_description_layer_name = __( 'Excerpt', 'blocksy' );
}

if ( $is_search ) {
	$custom_description_layer_name = __( 'Subtitle', 'blocksy' );
}

$default_hero_elements = array();

$default_hero_elements[] = array_merge(
	array(
		'id'          => 'custom_title',
		'enabled'     => 'product_' !== $prefix,
		'heading_tag' => 'h1',
		'title'       => __( 'Home', 'blocksy' ),
	),
	(
	$is_author ? array(
		'has_author_avatar'  => 'yes',
		'author_avatar_size' => 60,
	) : array()
	)
);

if (
	! $is_tutorlms
	&&
	! $is_tribe_events
	&&
	! $is_eventkoi
	&&
	! $is_vs_portfolio
) {
	$default_hero_elements[] = array(
		'id'                     => 'custom_description',
		'enabled'                => 'product_' !== $prefix,
		'description_visibility' => array(
			'desktop' => true,
			'tablet'  => true,
			'mobile'  => false,
		),
	);
}

if (
	(
		$is_single
		||
		$is_author
	)
	&&
	! $is_bbpress
	&&
	! $is_tutorlms
	&&
	! $is_tribe_events
	&&
	! $is_eventkoi
	&&
	! $is_vs_portfolio
) {
	$default_hero_elements[] = array(
		'id'                 => 'custom_meta',
		'enabled'            => ! $is_page && 'product_' !== $prefix,
		'meta_elements'      => blocksy_post_meta_defaults(
			array(
				array(
					'id'                => 'author',
					'has_author_avatar' => 'yes',
					'enabled'           => true,
				),

				array(
					'id'      => 'post_date',
					'enabled' => true,
				),

				array(
					'id'      => 'comments',
					'enabled' => true,
				),

				array(
					'id'      => 'categories',
					'enabled' => ! $is_page,
				),
			)
		),
		'page_meta_elements' => array(
			'joined'         => true,
			'articles_count' => true,
			'comments'       => true,
		),
	);
}

if ( $is_author ) {
	$default_hero_elements[] = array(
		'id'      => 'author_social_channels',
		'enabled' => true,
	);
}

$default_hero_elements[] = array(
	'id'      => 'breadcrumbs',
	'enabled' => 'product_' === $prefix,
);

$when_enabled_general_settings = array(
	$has_hero_type ? array(
		$prefix . 'hero_section' => array(
			'label'   => $has_default ? __( 'Type', 'blocksy' ) : false,
			'type'    => 'ct-image-picker',
			'value'   => ( $is_woo || $is_author ) ? 'type-2' : 'type-1',
			'design'  => 'block',
			'sync'    => blocksy_sync_whole_page(
				array(
					'prefix'        => $prefix,
					'prefix_custom' => 'hero',
				)
			),
			'choices' => array(
				'type-1' => array(
					'src'   => blocksy_image_picker_url( 'hero-type-1.svg' ),
					'title' => __( 'Type 1', 'blocksy' ),
				),

				'type-2' => array(
					'src'   => blocksy_image_picker_url( 'hero-type-2.svg' ),
					'title' => __( 'Type 2', 'blocksy' ),
				),
			),
		),
	) : array(
		$prefix . 'hero_section' => array(
			'type'  => 'hidden',
			'value' => ( $is_woo || $is_author ) ? 'type-2' : 'type-1',
		),
	),

	array(
		/**
		 * Filters the page title (hero) elements layers option.
		 *
		 * @since 1.8.4
		 *
		 * @param array  $option The hero elements option definition.
		 * @param string $prefix The screen prefix, without the trailing underscore.
		 */
		$prefix . 'hero_elements' => apply_filters(
			'blocksy:options:page-title:hero-elements',
			array(
				'label'    => __( 'Elements', 'blocksy' ),
				'type'     => $has_hero_elements ? 'ct-layers' : 'hidden',
				'design'   => 'block',
				'value'    => $default_hero_elements,
				'setting'  => array(
					'sanitize_callback' => function ( $input ) {
						return blocksy_sanitize_layers_html_fields(
							array(
								'value'  => $input,
								'fields' => array(
									'custom_title'       => array( 'title' ),
									'custom_description' => array( 'description' ),
								),
							)
						);
					},
				),
				'sync'     => array(
					array(
						'selector'            => blocksy_prefix_custom_selector( '.hero-section', 'hero' ),
						'container_inclusive' => true,
						'prefix'              => $prefix,
						'render'              => function ( $args ) {
							echo blocksy_output_hero_section(
								array(
									'type' => blocksy_get_theme_mod(
										$args['prefix'] . '_hero_section',
										'type-1'
									),
								)
							);
						},
						'prefix_custom'       => 'hero',
					),

					array(
						'prefix'          => $prefix,
						'id'              => $prefix . 'hero_elements_heading_tag',
						'loader_selector' => '.page-title',
						'prefix_custom'   => 'hero',
					),

					array(
						'prefix'          => $prefix,
						'id'              => $prefix . 'hero_elements_meta_first',
						'loader_selector' => '.entry-meta:1',
						'prefix_custom'   => 'hero',
					),

					array(
						'prefix'          => $prefix,
						'id'              => $prefix . 'hero_elements_meta_second',
						'loader_selector' => '.entry-meta:2',
						'prefix_custom'   => 'hero',
					),

					array(
						'prefix'          => $prefix,
						'id'              => $prefix . 'hero_elements_spacing',
						'loader_selector' => 'skip',
						'prefix_custom'   => 'hero',
					),

					array(
						'prefix'          => $prefix,
						'id'              => $prefix . 'hero_elements_author_avatar',
						'loader_selector' => '.ct-author-name',
						'prefix_custom'   => 'hero',
					),
				),

				'settings' => array(
					'breadcrumbs'            => array(
						'label'   => __( 'Breadcrumbs', 'blocksy' ),
						'options' => array(
							blocksy_rand_md5()       => array(
								'type'      => 'ct-condition',
								'condition' => array( 'itemIndex' => '!0' ),
								'options'   => array(

									'hero_item_spacing' => array(
										'label'      => __( 'Top Spacing', 'blocksy' ),
										'type'       => 'ct-slider',
										'value'      => 20,
										'min'        => 0,
										'max'        => 100,
										'responsive' => true,
										'sync'       => array(
											'id' => $prefix . 'hero_elements_spacing',
										),
									),

								),
							),

							'breadcrumbs_visibility' => array(
								'label'   => __( 'Visibility', 'blocksy' ),
								'type'    => 'ct-visibility',
								'design'  => 'block',
								'value'   => blocksy_default_responsive_value(
									array(
										'desktop' => true,
										'tablet'  => true,
										'mobile'  => true,
									)
								),
								'choices' => blocksy_ordered_keys(
									array(
										'desktop' => __( 'Desktop', 'blocksy' ),
										'tablet'  => __( 'Tablet', 'blocksy' ),
										'mobile'  => __( 'Mobile', 'blocksy' ),
									)
								),

								'sync'    => array(
									'id' => $prefix . 'hero_elements_spacing',
								),
							),

						),
					),

					'custom_title'           => array(
						'label'   => $is_author ? __( 'Name & Avatar', 'blocksy' ) : __( 'Title', 'blocksy' ),
						'options' => array(
							array(
								'heading_tag' => array(
									'label'   => __( 'Heading tag', 'blocksy' ),
									'type'    => 'ct-select',
									'value'   => 'h1',
									'view'    => 'text',
									'design'  => 'inline',
									'sync'    => array(
										'id' => $prefix . 'hero_elements_heading_tag',
									),
									'choices' => blocksy_ordered_keys(
										array(
											'h1' => 'H1',
											'h2' => 'H2',
											'h3' => 'H3',
											'h4' => 'H4',
											'h5' => 'H5',
											'h6' => 'H6',
										)
									),
								),
							),

							array(
								$is_home ? array(
									blocksy_rand_md5() => array(
										'type'          => 'ct-condition',
										'condition'     => array( 'show_on_front' => 'posts' ),
										'values_source' => 'global',
										'options'       => array(
											'title' => array(
												'label'  => __( 'Title', 'blocksy' ),
												'type'   => 'text',
												'value'  => __( 'Home', 'blocksy' ),
												'disableRevertButton' => true,
												'design' => 'inline',
											),
										),
									),
								) : array(),
							),

							array(
								(
									$is_archive
									||
									$is_bbpress
									||
									$is_home
									||
									$is_tribe_events
								) ? array(
									'has_category_label' => array(
										'label' => __( 'Archive Label', 'blocksy' ),
										'type'  => 'ct-switch',
										'value' => 'yes',
									),
								) : array(),
							),

							array(
								$is_author ? array(
									blocksy_rand_md5() => array(
										'type'    => 'ct-group',
										'attr'    => array( 'data-columns' => '1' ),
										'options' => array(
											'has_author_avatar' => array(
												'label' => __( 'Author avatar', 'blocksy' ),
												'type'  => 'ct-switch',
												'value' => 'yes',
												'sync'  => array(
													'id' => $prefix . 'hero_elements_author_avatar',
												),
											),

											blocksy_rand_md5() => array(
												'type'    => 'ct-condition',
												'condition' => array( 'has_author_avatar' => 'yes' ),
												'options' => array(
													'author_avatar_size' => array(
														'label' => __( 'Avatar Size', 'blocksy' ),
														'type' => 'ct-number',
														'design' => 'inline',
														'value' => 60,
														'min'  => 15,
														'max'  => 300,
														'sync' => array(
															'id' => $prefix . 'hero_elements_spacing',
														),
													),
												),
											),

										),
									),
								) : array(),
							),

							'custom_attribute' => array(
								'label' => __( 'Custom Attribute', 'blocksy' ),
								'type'  => 'text',
								'value' => '',
							),

							blocksy_rand_md5() => array(
								'type'      => 'ct-condition',
								'condition' => array( 'itemIndex' => '!0' ),
								'options'   => array(
									'hero_item_spacing' => array(
										'label'      => __( 'Top Spacing', 'blocksy' ),
										'type'       => 'ct-slider',
										'value'      => 20,
										'min'        => 0,
										'max'        => 100,
										'responsive' => true,
										'sync'       => array(
											'id' => $prefix . 'hero_elements_spacing',
										),
									),
								),
							),

						),
					),

					'custom_description'     => array(
						'label'   => $custom_description_layer_name,
						'options' => array(
							array(
								$is_home ? array(
									blocksy_rand_md5() => array(
										'type'          => 'ct-condition',
										'condition'     => array( 'show_on_front' => 'posts' ),
										'values_source' => 'global',
										'options'       => array(
											'description' => array(
												'label'  => false,
												'type'   => 'textarea',
												'value'  => '',
												'disableRevertButton' => true,
												'design' => 'block',
											),
										),
									),
								) : array(),
							),

							blocksy_rand_md5()       => array(
								'type'          => 'ct-condition',
								'condition'     => array( $prefix . 'hero_section' => 'type-1' ),
								'values_source' => 'parent',
								'options'       => array(

									'hero_item_max_width' => array(
										'label'       => __( 'Max Width', 'blocksy' ),
										'type'        => 'ct-slider',
										'value'       => 100,
										'min'         => 10,
										'max'         => 100,
										'defaultUnit' => '%',
										'responsive'  => true,
										'sync'        => array(
											'id' => $prefix . 'hero_elements_spacing',
										),
									),

								),
							),

							blocksy_rand_md5()       => array(
								'type'      => 'ct-condition',
								'condition' => array( 'itemIndex' => '!0' ),
								'options'   => array(
									'hero_item_spacing' => array(
										'label'      => __( 'Top Spacing', 'blocksy' ),
										'type'       => 'ct-slider',
										'value'      => 20,
										'min'        => 0,
										'max'        => 100,
										'responsive' => true,
										'sync'       => array(
											'id' => $prefix . 'hero_elements_spacing',
										),
									),
								),
							),

							'description_visibility' => array(
								'label'   => __( 'Visibility', 'blocksy' ),
								'type'    => 'ct-visibility',
								'design'  => 'block',
								'value'   => blocksy_default_responsive_value(
									array(
										'desktop' => true,
										'tablet'  => true,
										'mobile'  => false,
									)
								),
								'choices' => blocksy_ordered_keys(
									array(
										'desktop' => __( 'Desktop', 'blocksy' ),
										'tablet'  => __( 'Tablet', 'blocksy' ),
										'mobile'  => __( 'Mobile', 'blocksy' ),
									)
								),

								'sync'    => array(
									'id' => $prefix . 'hero_elements_spacing',
								),
							),
						),
					),

					'custom_meta'            => array(
						// 'label' => __('Post Meta', 'blocksy'),
						'label'   => $is_author ? __( 'Author Meta', 'blocksy' ) : __( 'Post Meta', 'blocksy' ),
						'clone'   => true,
						'sync'    => array(
							'id' => $prefix . 'hero_elements_meta',
						),
						'options' => array(
							$is_author ? array(
								'page_meta_elements' => array(
									'label'       => __( 'Meta Elements', 'blocksy' ),
									'type'        => 'ct-checkboxes',
									'design'      => 'block',
									'attr'        => array( 'data-columns' => '2' ),
									'allow_empty' => true,
									'choices'     => blocksy_ordered_keys(
										array(
											'joined'   => __( 'Joined Date', 'blocksy' ),
											'articles_count' => __( 'Articles', 'blocksy' ),
											'comments' => __( 'Comments', 'blocksy' ),
										)
									),

									'value'       => array(
										'joined'         => true,
										'articles_count' => true,
										'comments'       => true,
									),
								),
							) : array(),

							array(
								$is_single ? array(
									blocksy_get_options(
										'general/meta',
										array(
											'skip_sync_id' => array(
												'id' => $prefix . 'hero_elements_spacing',
											),
											'is_page'      => $is_page,
											'is_cpt'       => $is_cpt,
											'computed_cpt' => $computed_cpt,
										)
									),
								) : array(),
							),

							blocksy_rand_md5() => array(
								'type'      => 'ct-condition',
								'condition' => array( 'itemIndex' => '!0' ),
								'options'   => array(
									'hero_item_spacing' => array(
										'label'      => __( 'Top Spacing', 'blocksy' ),
										'type'       => 'ct-slider',
										'value'      => 20,
										'min'        => 0,
										'max'        => 100,
										'responsive' => true,
										'sync'       => array(
											'id' => $prefix . 'hero_elements_spacing',
										),
									),
								),
							),
						),
					),

					'author_social_channels' => array(
						'label'   => __( 'Social Channels', 'blocksy' ),
						'options' => array(
							blocksy_rand_md5() => array(
								'type'      => 'ct-condition',
								'condition' => array( 'itemIndex' => '!0' ),
								'options'   => array(
									'hero_item_spacing' => array(
										'label'      => __( 'Top Spacing', 'blocksy' ),
										'type'       => 'ct-slider',
										'value'      => 20,
										'min'        => 0,
										'max'        => 100,
										'responsive' => true,
										'sync'       => array(
											'id' => $prefix . 'hero_elements_spacing',
										),
									),
								),
							),

							'link_target'      => array(
								'type'  => 'ct-switch',
								'label' => __( 'Open links in new tab', 'blocksy' ),
								'value' => 'no',
							),

							'link_nofollow'    => array(
								'type'  => 'ct-switch',
								'label' => __( 'Set links to nofollow', 'blocksy' ),
								'value' => 'no',
							),
						),
					),
				),
			),
			trim( $prefix, '_' )
		),

		blocksy_rand_md5()        => array(
			'type' => 'ct-divider',
		),

		blocksy_rand_md5()        => array(
			'type'      => 'ct-condition',
			'condition' => array( $prefix . 'hero_section' => 'type-1' ),
			'options'   => array(

				$prefix . 'hero_alignment1' => array(
					'type'       => 'ct-radio',
					'label'      => __( 'Horizontal Alignment', 'blocksy' ),
					/**
					 * Filters the default horizontal alignment of the type 1 hero.
					 *
					 * @since 1.7.60
					 *
					 * @param string $alignment The default alignment. Default 'CT_CSS_SKIP_RULE'.
					 * @param string $prefix    The screen prefix, without the trailing underscore.
					 */
					'value'      => apply_filters(
						'blocksy:hero:type-1:default-alignment',
						'CT_CSS_SKIP_RULE',
						trim( $prefix, '_' )
					),
					'view'       => 'text',
					'attr'       => array( 'data-type' => 'alignment' ),
					'responsive' => true,
					'design'     => 'block',
					'sync'       => 'live',
					'choices'    => array(
						'left'   => '',
						'center' => '',
						'right'  => '',
					),
				),

				$prefix . 'hero_margin'     => array(
					'label'      => __( 'Container Bottom Spacing', 'blocksy' ),
					'type'       => 'ct-slider',
					'value'      => '40px',
					'units'      => blocksy_units_config(
						array(
							array(
								'unit' => 'px',
								'min'  => 0,
								'max'  => 300,
							),
							array(
								'unit' => '',
								'type' => 'custom',
							),
						)
					),
					'responsive' => true,
					'sync'       => 'live',
					'divider'    => 'top',
				),
			),
		),

		blocksy_rand_md5()        => array(
			'type'      => 'ct-condition',
			'condition' => array( $prefix . 'hero_section' => 'type-2' ),
			'options'   => array_merge(
				array(

					$prefix . 'hero_alignment2'         => array(
						'type'       => 'ct-radio',
						'label'      => __( 'Horizontal Alignment', 'blocksy' ),
						'value'      => 'center',
						'view'       => 'text',
						'attr'       => array( 'data-type' => 'alignment' ),
						'responsive' => true,
						'design'     => 'block',
						'sync'       => 'live',
						'choices'    => array(
							'start'  => '',
							'center' => '',
							'end'    => '',
						),
					),

					$prefix . 'hero_vertical_alignment' => array(
						'type'       => 'ct-radio',
						'label'      => __( 'Vertical Alignment', 'blocksy' ),
						'value'      => 'center',
						'view'       => 'text',
						'design'     => 'block',
						'responsive' => true,
						'attr'       => array( 'data-type' => 'vertical-alignment' ),
						'sync'       => 'live',

						'choices'    => array(
							'flex-start' => '',
							'center'     => '',
							'flex-end'   => '',
						),
					),
				)
			),
		),

	),

	array(

		blocksy_rand_md5() => array(
			'type'      => 'ct-condition',
			'condition' => array( $prefix . 'hero_section' => 'type-2' ),
			'options'   => array(

				blocksy_rand_md5()             => array(
					'type' => 'ct-divider',
				),

				$prefix . 'hero_structure'     => array(
					'label'   => __( 'Container Width', 'blocksy' ),
					'type'    => 'ct-radio',
					'value'   => 'narrow',
					'view'    => 'text',
					'design'  => 'block',
					'sync'    => 'live',
					'choices' => array(
						'normal' => __( 'Default', 'blocksy' ),
						'narrow' => __( 'Narrow', 'blocksy' ),
					),
				),

				blocksy_rand_md5()             => array(
					'type' => 'ct-divider',
				),

				$prefix . 'page_title_bg_type' => array(
					'label'   => __( 'Container Background Image', 'blocksy' ),
					'type'    => 'ct-radio',
					'value'   => ( $is_archive && ! $archives_have_hero || $is_author || $is_search ) ? 'color' : 'featured_image',
					'view'    => 'text',
					'design'  => 'block',
					'attr'    => array( 'data-radio-text' => 'small' ),
					'choices' => array_merge(
						( $is_author || $is_search || $is_archive && ! $archives_have_hero ) ? array() : array(
							'featured_image' => __( 'Featured', 'blocksy' ),
						),
						array(
							'custom_image' => __( 'Custom', 'blocksy' ),
							'color'        => __( 'None', 'blocksy' ),
						)
					),
					'sync'    => array(
						'id' => $prefix . 'hero_elements',
					),
				),

				blocksy_rand_md5()             => array(
					'type'      => 'ct-condition',
					'condition' => array(
						$prefix . 'page_title_bg_type' => 'custom_image',
					),
					'options'   => array(
						$prefix . 'custom_hero_background' => array(
							'label'       => __( 'Custom Image', 'blocksy' ),
							'type'        => 'ct-image-uploader',
							'design'      => false,
							'divider'     => 'top',
							'value'       => array( 'attachment_id' => null ),
							'emptyLabel'  => __( 'Select Image', 'blocksy' ),
							'filledLabel' => __( 'Change Image', 'blocksy' ),
							'sync'        => array(
								'id' => $prefix . 'hero_elements',
							),
						),
					),
				),

				blocksy_rand_md5()             => array(
					'type'      => 'ct-condition',
					'condition' => array(
						$prefix . 'page_title_bg_type' => 'custom_image | featured_image',
					),
					'options'   => array(

						$prefix . 'page_title_image_size' => array(
							'label'   => __( 'Image Size', 'blocksy' ),
							'type'    => 'ct-select',
							'value'   => 'full',
							'view'    => 'text',
							'design'  => 'inline',
							'divider' => 'top',
							'choices' => blocksy_ordered_keys( blocksy_get_all_image_sizes() ),
							'sync'    => array(
								'id' => $prefix . 'hero_elements',
							),
						),

						$prefix . 'parallax'              => array(
							'label'       => __( 'Image Parallax Effect', 'blocksy' ),
							'desc'        => __( 'Choose for which devices you want to enable the parallax effect.', 'blocksy' ),
							'type'        => 'ct-visibility',
							'design'      => 'block',
							'divider'     => 'top',
							'allow_empty' => true,
							'sync'        => 'live',
							'value'       => blocksy_default_responsive_value(
								array(
									'desktop' => false,
									'tablet'  => false,
									'mobile'  => false,
								)
							),
							'choices'     => blocksy_ordered_keys(
								array(
									'desktop' => __( 'Desktop', 'blocksy' ),
									'tablet'  => __( 'Tablet', 'blocksy' ),
									'mobile'  => __( 'Mobile', 'blocksy' ),
								)
							),
						),
					),
				),

				blocksy_rand_md5()             => array(
					'type' => 'ct-divider',
				),

				$prefix . 'hero_height'        => array(
					'label'      => __( 'Container Min Height', 'blocksy' ),
					'type'       => 'ct-slider',
					'value'      => '250px',
					'design'     => 'block',
					'units'      => array(
						array(
							'unit' => 'px',
							'min'  => 0,
							'max'  => 1000,
						),
						array(
							'unit' => '',
							'type' => 'custom',
						),
					),
					'responsive' => true,
					'sync'       => 'live',
				),
			),
		),
	),
);

$when_enabled_design_settings = array(
	$design_options ? $design_options : array(
		blocksy_rand_md5() => array(
			'type'      => 'ct-condition',
			'condition' => array(
				$prefix . 'hero_elements:array-ids:custom_title:enabled' => 'true',
			),
			'options'   => array(
				$prefix . 'pageTitleFont'      => array(
					'type'   => 'ct-typography',
					'label'  => __( 'Title Font', 'blocksy' ),
					'value'  => blocksy_typography_default_values(
						array(
							'size' => '30px',
						)
					),
					'design' => 'block',
					'sync'   => 'live',
				),

				$prefix . 'pageTitleFontColor' => array(
					'label'   => __( 'Title Font Color', 'blocksy' ),
					'type'    => 'ct-color-picker',
					'design'  => 'inline',
					'sync'    => 'live',

					'value'   => array(
						'default' => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
						),
					),

					'pickers' => array(
						array(
							'title'   => __( 'Initial', 'blocksy' ),
							'id'      => 'default',
							'inherit' => array(
								'var(--theme-heading-1-color, var(--theme-headings-color))' => array(
									$prefix . 'hero_elements:array-ids:custom_title:heading_tag' => 'h1',
								),

								'var(--theme-heading-2-color, var(--theme-headings-color))' => array(
									$prefix . 'hero_elements:array-ids:custom_title:heading_tag' => 'h2',
								),

								'var(--theme-heading-3-color, var(--theme-headings-color))' => array(
									$prefix . 'hero_elements:array-ids:custom_title:heading_tag' => 'h3',
								),

								'var(--theme-heading-4-color, var(--theme-headings-color))' => array(
									$prefix . 'hero_elements:array-ids:custom_title:heading_tag' => 'h4',
								),

								'var(--theme-heading-5-color, var(--theme-headings-color))' => array(
									$prefix . 'hero_elements:array-ids:custom_title:heading_tag' => 'h5',
								),

								'var(--theme-heading-6-color, var(--theme-headings-color))' => array(
									$prefix . 'hero_elements:array-ids:custom_title:heading_tag' => 'h6',
								),
							),
						),
					),
				),
			),
		),

		blocksy_rand_md5() => array(
			'type'      => 'ct-condition',
			'condition' => array(
				'all' => array(
					$prefix . 'hero_elements:array-ids-true:custom_meta:enabled' => 'true',
					'any' => array(
						$prefix . 'hero_elements:array-ids:custom_meta:single_meta_elements/author' => 'true',
						$prefix . 'hero_elements:array-ids:custom_meta:single_meta_elements/comments' => 'true',
						$prefix . 'hero_elements:array-ids:custom_meta:single_meta_elements/date' => 'true',
						$prefix . 'hero_elements:array-ids:custom_meta:single_meta_elements/updated' => 'true',
						$prefix . 'hero_elements:array-ids:custom_meta:single_meta_elements/categories' => 'true',
						$prefix . 'hero_elements:array-ids:custom_meta:single_meta_elements/tags' => 'true',
						$prefix . 'hero_elements:array-ids:custom_meta:page_meta_elements/joined' => 'true',
						$prefix . 'hero_elements:array-ids:custom_meta:page_meta_elements/articles_count' => 'true',
						$prefix . 'hero_elements:array-ids:custom_meta:page_meta_elements/comments' => 'true',
					),
				),
			),

			'options'   => array(
				$prefix . 'pageMetaFont'      => array(
					'type'    => 'ct-typography',
					'label'   => __( 'Meta Font', 'blocksy' ),
					'value'   => blocksy_typography_default_values(
						array(
							'size'           => '12px',
							'variation'      => 'n6',
							'line-height'    => '1.3',
							'text-transform' => 'uppercase',
						)
					),
					'design'  => 'block',
					'sync'    => 'live',
					'divider' => 'top:full',
				),

				$prefix . 'pageMetaFontColor' => array(
					'label'   => __( 'Meta Font Color', 'blocksy' ),
					'type'    => 'ct-color-picker',
					'design'  => 'inline',
					'sync'    => 'live',

					'value'   => array(
						'default' => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
						),

						'hover'   => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
						),
					),

					'pickers' => array(
						array(
							'title'   => __( 'Initial', 'blocksy' ),
							'id'      => 'default',
							'inherit' => 'var(--theme-text-color)',
						),

						array(
							'title'   => __( 'Hover', 'blocksy' ),
							'id'      => 'hover',
							'inherit' => 'var(--theme-link-hover-color)',
						),
					),
				),

				blocksy_rand_md5()            => array(
					'type'     => 'ct-has-meta-category-button',
					'optionId' => $prefix . 'hero_elements',
					'options'  => array(
						$prefix . 'page_meta_button_type_font_colors' => array(
							'label'   => __( 'Meta Button Font', 'blocksy' ),
							'type'    => 'ct-color-picker',
							'design'  => 'inline',
							'divider' => 'top',
							'noColor' => array( 'background' => 'var(--theme-text-color)' ),
							'sync'    => 'live',
							'value'   => array(
								'default' => array(
									'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
								),

								'hover'   => array(
									'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
								),
							),

							'pickers' => array(
								array(
									'title'   => __( 'Initial', 'blocksy' ),
									'id'      => 'default',
									'inherit' => 'var(--theme-button-text-initial-color)',
								),

								array(
									'title'   => __( 'Hover', 'blocksy' ),
									'id'      => 'hover',
									'inherit' => 'var(--theme-button-text-hover-color)',
								),
							),
						),

						$prefix . 'page_meta_button_type_background_colors' => array(
							'label'   => __( 'Meta Button Background', 'blocksy' ),
							'type'    => 'ct-color-picker',
							'design'  => 'inline',
							'noColor' => array( 'background' => 'var(--theme-text-color)' ),
							'sync'    => 'live',
							'value'   => array(
								'default' => array(
									'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
								),

								'hover'   => array(
									'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
								),
							),

							'pickers' => array(
								array(
									'title'   => __( 'Initial', 'blocksy' ),
									'id'      => 'default',
									'inherit' => 'var(--theme-button-background-initial-color)',
								),

								array(
									'title'   => __( 'Hover', 'blocksy' ),
									'id'      => 'hover',
									'inherit' => 'var(--theme-button-background-hover-color)',
								),
							),
						),
					),
				),

			),
		),

		blocksy_rand_md5() => array(
			'type'      => 'ct-condition',
			'condition' => array(
				'all' => array(
					$prefix . 'hero_elements:array-ids:custom_description:enabled' => 'true',
					'any' => array(
						$prefix . 'hero_elements:array-ids:custom_description:description_visibility/desktop' => 'true',
						$prefix . 'hero_elements:array-ids:custom_description:description_visibility/tablet' => 'true',
						$prefix . 'hero_elements:array-ids:custom_description:description_visibility/mobile' => 'true',
					),
				),
			),
			'options'   => array(
				$prefix . 'pageExcerptFont'  => array(
					'type'    => 'ct-typography',
					'label'   => $is_single ? __( 'Excerpt Font', 'blocksy' ) : blocksy_safe_sprintf(
						// translators: %s entity of font
						__( '%s Font', 'blocksy' ),
						$custom_description_layer_name
					),
					'value'   => blocksy_typography_default_values( array() ),
					'design'  => 'block',
					'sync'    => 'live',
					'divider' => 'top:full',
				),

				$prefix . 'pageExcerptColor' => array(
					'label'   => $is_single ? __( 'Excerpt Font Color', 'blocksy' ) : blocksy_safe_sprintf(
						// translators: %s entity of font color
						__( '%s Font Color', 'blocksy' ),
						$custom_description_layer_name
					),
					'type'    => 'ct-color-picker',
					'design'  => 'inline',
					'sync'    => 'live',

					'value'   => array(
						'default' => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
						),
					),

					'pickers' => array(
						array(
							'title'   => __( 'Initial', 'blocksy' ),
							'id'      => 'default',
							'inherit' => 'var(--theme-text-color)',
						),
					),
				),
			),
		),

		blocksy_rand_md5() => array(
			'type'      => 'ct-condition',
			'condition' => array(
				$prefix . 'hero_elements:array-ids:author_social_channels:enabled' => 'true',
			),
			'options'   => array(

				$prefix . 'page_social_icons_color'      => array(
					'label'   => __( 'Icons Color', 'blocksy' ),
					'type'    => 'ct-color-picker',
					'design'  => 'inline',
					'divider' => 'top:full',
					'setting' => array( 'transport' => 'postMessage' ),

					'value'   => array(
						'default' => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
						),

						'hover'   => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
						),
					),

					'pickers' => array(
						array(
							'title'   => __( 'Initial', 'blocksy' ),
							'id'      => 'default',
							'inherit' => '#fff',
						),

						array(
							'title'   => __( 'Hover', 'blocksy' ),
							'id'      => 'hover',
							'inherit' => '#fff',
						),
					),
				),

				$prefix . 'page_social_icons_background' => array(
					'label'   => __( 'Icons Background Color', 'blocksy' ),
					'type'    => 'ct-color-picker',
					'design'  => 'inline',
					'setting' => array( 'transport' => 'postMessage' ),

					'value'   => array(
						'default' => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
						),

						'hover'   => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
						),
					),

					'pickers' => array(
						array(
							'title'   => __( 'Initial', 'blocksy' ),
							'id'      => 'default',
							'inherit' => 'var(--theme-palette-color-1)',
						),

						array(
							'title'   => __( 'Hover', 'blocksy' ),
							'id'      => 'hover',
							'inherit' => 'var(--theme-palette-color-2)',
						),
					),
				),

			),
		),

		blocksy_rand_md5() => array(
			'type'      => 'ct-condition',
			'condition' => array(
				$prefix . 'hero_elements:array-ids:breadcrumbs:enabled' => 'true',
			),
			'options'   => array(
				$prefix . 'breadcrumbsFont'      => array(
					'type'    => 'ct-typography',
					'label'   => __( 'Breadcrumbs Font', 'blocksy' ),
					'value'   => blocksy_typography_default_values( array() ),
					'design'  => 'block',
					'sync'    => 'live',
					'divider' => 'top:full',
				),

				$prefix . 'breadcrumbsFontColor' => array(
					'label'   => __( 'Breadcrumbs Font Color', 'blocksy' ),
					'type'    => 'ct-color-picker',
					'design'  => 'inline',
					'sync'    => 'live',

					'value'   => array(
						'default' => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
						),

						'initial' => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
						),

						'hover'   => array(
							'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
						),
					),

					'pickers' => array(
						array(
							'title'   => __( 'Text', 'blocksy' ),
							'id'      => 'default',
							'inherit' => 'var(--theme-text-color)',
						),

						array(
							'title'   => __( 'Link Initial', 'blocksy' ),
							'id'      => 'initial',
							'inherit' => 'var(--theme-link-initial-color)',
						),

						array(
							'title'   => __( 'Link Hover', 'blocksy' ),
							'id'      => 'hover',
							'inherit' => 'var(--theme-link-hover-color)',
						),
					),
				),
			),
		),
	),

	/**
	 * Filters the page title design options printed before the breadcrumbs options.
	 *
	 * @since 1.8.4.3
	 *
	 * @param array  $options The options to insert. Default empty array.
	 * @param string $prefix  The screen prefix, without the trailing underscore.
	 */
	apply_filters(
		'blocksy:options:page-title:design:before_breadcrumbs',
		array(),
		trim( $prefix, '_' )
	),

	blocksy_rand_md5() => array(
		'type'      => 'ct-condition',
		'condition' => array(
			$prefix . 'hero_section' => 'type-2',
		),
		'options'   => array(

			blocksy_rand_md5()              => array(
				'type'      => 'ct-condition',
				'condition' => array( $prefix . 'page_title_bg_type' => '!color' ),
				'options'   => array(

					$prefix . 'pageTitleOverlay' => array(
						'label'                 => __( 'Image Overlay Color', 'blocksy' ),
						'type'                  => 'ct-background',
						'design'                => 'inline',
						'sync'                  => 'live',
						'divider'               => 'top:full',
						'has_no_color'          => true,
						'default_inherit_color' => 'rgba(18, 21, 25, 0.5)',
						'activeTabs'            => array( 'color', 'gradient' ),
						'value'                 => blocksy_background_default_value(
							array(
								'backgroundColor' => array(
									'default' => array(
										'color' => 'CT_CSS_SKIP_RULE',
									),
								),
							)
						),
					),
				),
			),

			$prefix . 'pageTitleBackground' => array(
				'label'   => __( 'Container Background', 'blocksy' ),
				'type'    => 'ct-background',
				'design'  => 'inline',
				'sync'    => 'live',
				'divider' => 'top:full',
				'value'   => blocksy_background_default_value(
					array(
						'backgroundColor' => array(
							'default' => array(
								'color' => 'var(--theme-palette-color-6)',
							),
						),
					)
				),
			),

			$prefix . 'pageTitlePadding'    => array(
				'label'      => __( 'Container Padding', 'blocksy' ),
				'type'       => 'ct-spacing',
				'divider'    => 'top:full',
				'setting'    => array( 'transport' => 'postMessage' ),
				'value'      => blocksy_spacing_value(
					array(
						'top'    => '50px',
						'left'   => 'auto',
						'right'  => 'auto',
						'bottom' => '50px',
					)
				),
				'responsive' => true,
			),

		),
	),
);

$when_enabled_settings = array(
	blocksy_rand_md5() => array(
		'title'   => __( 'General', 'blocksy' ),
		'type'    => 'tab',
		'options' => $when_enabled_general_settings,
	),

	blocksy_rand_md5() => array(
		'title'   => __( 'Design', 'blocksy' ),
		'type'    => 'tab',
		'options' => $when_enabled_design_settings,
	),
);

$options_when_not_default = array(
	$prefix . 'hero_enabled' => array(
		'label'         => $enabled_label,
		'type'          => 'ct-panel',
		'switch'        => true,
		'value'         => $enabled_default,
		'wrapperAttr'   => array( 'data-label' => 'heading-label' ),
		'sync'          => blocksy_sync_whole_page(
			array(
				'prefix'        => $prefix,
				'prefix_custom' => 'hero',
			)
		),
		'inner-options' => $when_enabled_settings,

		// 'disabled_notice' => empty($location_name) ? __(
		//  'This page may not fully meet accessibility requirements, consider manually adding a heading tag to ensure compliance.',
		//  'blocksy'
		// ) : blocksy_safe_sprintf(
		//  __(
		//      'The "%s" page may not fully meet accessibility requirements, consider manually adding a heading tag to ensure compliance.',
		//      'blocksy'
		//  ),
		//  $location_name
		// )
	),
);

// options output for posts/pages
$options_when_default = array(
	$prefix . 'has_hero_section' => array(
		// 'label' => $is_page ? __('Page Title', 'blocksy') : __(
		//  'Post Title', 'blocksy'
		// ),
		'label'               => false,
		'type'                => 'ct-radio',
		'value'               => 'default',
		'view'                => 'text',
		'disableRevertButton' => true,
		'design'              => $is_single ? 'block' : 'inline',
		'wrapperAttr'         => array( 'data-spacing' => 'custom' ),
		'choices'             => array(
			'default'  => __( 'Inherit', 'blocksy' ),
			'enabled'  => __( 'Custom', 'blocksy' ),
			'disabled' => __( 'Disabled', 'blocksy' ),
		),
	),

	blocksy_rand_md5()           => array(
		'type'      => 'ct-condition',
		'condition' => array( $prefix . 'has_hero_section' => 'default' ),
		'options'   => array(
			blocksy_rand_md5() => array(
				'type' => 'ct-notification',
				'attr' => array( 'data-spacing' => 'custom' ),
				'text' => __( 'By default these options are inherited from Customizer options.', 'blocksy' ),
			),
		),
	),

	blocksy_rand_md5()           => array(
		'type'      => 'ct-condition',
		'condition' => array( $prefix . 'has_hero_section' => 'enabled' ),
		'options'   => $when_enabled_settings,
	),
);

// options output for taxonomies
if ( ! $is_single ) {
	$options_when_default = array(
		blocksy_rand_md5() => array(
			'title'   => __( 'General', 'blocksy' ),
			'type'    => 'tab',
			'options' => array(
				$prefix . 'has_hero_section' => array(
					'label'               => __( 'Page Title', 'blocksy' ),
					'type'                => 'ct-radio',
					'value'               => 'default',
					'view'                => 'text',
					'disableRevertButton' => true,
					'design'              => $is_single ? 'block' : 'inline',
					'wrapperAttr'         => array( 'data-spacing' => 'custom' ),
					'choices'             => array(
						'default'  => __( 'Inherit', 'blocksy' ),
						'enabled'  => __( 'Custom', 'blocksy' ),
						'disabled' => __( 'Disabled', 'blocksy' ),
					),
				),

				blocksy_rand_md5()           => array(
					'type'      => 'ct-condition',
					'condition' => array( $prefix . 'has_hero_section' => 'enabled' ),
					'options'   => $when_enabled_general_settings,
				),
			),
		),

		blocksy_rand_md5() => array(
			'title'   => __( 'Design', 'blocksy' ),
			'type'    => 'tab',
			'options' => array(

				blocksy_rand_md5() => array(
					'type'      => 'ct-condition',
					'condition' => array( $prefix . 'has_hero_section' => 'enabled' ),
					'options'   => $when_enabled_design_settings,
				),

				blocksy_rand_md5() => array(
					'type'      => 'ct-condition',
					'condition' => array( $prefix . 'has_hero_section' => '!enabled' ),
					'options'   => array(
						blocksy_rand_md5() => array(
							'type' => 'ct-notification',
							'attr' => array( 'data-label' => 'no-label' ),
							'text' => __( 'Options will appear here only if you will set Custom in Page Title option.', 'blocksy' ),
						),
					),
				),
			),
		),
	);
}

$options = $has_default ? $options_when_default : $options_when_not_default; // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
