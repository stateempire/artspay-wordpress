<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- include scope, variables come from blocksy_get_options()

$page_title_options = blocksy_get_options(
	'general/page-title',
	array(
		'prefix'        => 'woo_categories',
		'is_woo'        => true,
		'location_name' => __( 'Product Archives', 'blocksy' ),
	)
);

// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Returned by the options loader.
$options = array(
	'woo_categories_section_options' => array(
		'type'          => 'ct-options',
		'setting'       => array( 'transport' => 'postMessage' ),
		'inner-options' => array(
			blocksy_manager()->get_prefix_title_actions(
				array(
					'prefix' => 'woo_categories',
					'areas'  => array(
						array(
							'title'   => __( 'Page Title', 'blocksy' ),
							'options' => $page_title_options,
							'sources' => array_merge(
								blocksy_manager()
								->screen
								->get_archive_prefixes_with_human_labels(
									array(
										'has_categories'  => true,
										'has_author'      => true,
										'has_search'      => true,
										'has_woocommerce' => true,
									)
								),
								blocksy_manager()
									->screen
									->get_single_prefixes_with_human_labels(
										array(
											'has_woocommerce' => true,
										)
									)
							),
						),
					),
				)
			),

			$page_title_options,

			array(
				blocksy_rand_md5() => array(
					'type'  => 'ct-title',
					'label' => __( 'Shop Settings', 'blocksy' ),
				),

				blocksy_rand_md5() => array(
					'title'   => __( 'General', 'blocksy' ),
					'type'    => 'tab',
					'options' => array(

						array(
							'shop_cards_type'             => array(
								'label'   => false,
								'type'    => 'ct-image-picker',
								'value'   => 'type-1',
								'divider' => 'bottom',
								'setting' => array( 'transport' => 'postMessage' ),
								'choices' =>
								/**
								 * Filters the product card type choices of the Product
								 * Archives section.
								 *
								 * @since 2.0.1
								 *
								 * @param array $choices Card type id => image picker choice.
								 */
								apply_filters(
									'blocksy:options:woocommerce:archive:card-type:choices',
									array(
										'type-1' => array(
											'src'   => blocksy_image_picker_url( 'woo-type-1.svg' ),
											'title' => __( 'Type 1', 'blocksy' ),
										),

										'type-2' => array(
											'src'   => blocksy_image_picker_url( 'woo-type-2.svg' ),
											'title' => __( 'Type 2', 'blocksy' ),
										),
									)
								),

								'sync'    => blocksy_sync_whole_page(
									array(
										'prefix'          => 'woo_categories',
										'loader_selector' => '.products > li',
									)
								),
							),

							'blocksy_woo_columns'         => array(
								'label'      => __( 'Columns & Rows', 'blocksy' ),
								'type'       => 'ct-woocommerce-columns-and-rows',
								'value'      => array(
									'desktop'   => 4,
									'tablet'    => 3,
									'mobile'    => 1,
									'__changed' => array( 'tablet', 'mobile' ),
								),
								'min'        => 1,
								'max'        => 6,
								'responsive' => true,
								'setting'    => array(
									'transport' => 'postMessage',
								),
							),

							'woocommerce_catalog_columns' => array(
								'type'    => 'hidden',
								'value'   => 4,
								'setting' => array(
									'type'       => 'option',
									'transport'  => 'postMessage',
									'capability' => 'manage_woocommerce',
								),
							),

							'woocommerce_catalog_rows'    => array(
								'type'    => 'hidden',
								'value'   => 4,
								'setting' => array(
									'type'       => 'option',
									'capability' => 'manage_woocommerce',
								),

								'sync'    => blocksy_sync_whole_page(
									array(
										'prefix'          => 'woo_categories',
										'loader_selector' => '.products > li',
									)
								),
							),

							blocksy_rand_md5()            => array(
								'type' => 'ct-divider',
								'attr' => array( 'data-type' => 'small' ),
							),
						),

						blocksy_get_options( 'woocommerce/card-product-elements' ),

						'woo_categories_content_area_spacing' => array(
							'label'       => __( 'Content Area Vertical Spacing', 'blocksy' ),
							'type'        => 'ct-radio',
							'value'       => 'both',
							'view'        => 'text',
							'design'      => 'block',
							'divider'     => 'top:full',
							'attr'        => array( 'data-type' => 'content-spacing' ),
							'choice_attr' => array( 'data-tooltip-reveal' => 'top' ),
							'sync'        => 'live',
							'choices'     => array(
								'both'   => '<span></span>
								<i class="ct-tooltip">' . __( 'Top & Bottom', 'blocksy' ) . '</i>',

								'top'    => '<span></span>
								<i class="ct-tooltip">' . __( 'Only Top', 'blocksy' ) . '</i>',

								'bottom' => '<span></span>
								<i class="ct-tooltip">' . __( 'Only Bottom', 'blocksy' ) . '</i>',

								'none'   => '<span></span>
								<i class="ct-tooltip">' . __( 'Disabled', 'blocksy' ) . '</i>',
							),
							'desc'        => blocksy_safe_sprintf(
								// translators: placeholder here means the actual URL.
								__( 'You can customize the global spacing value in General ➝ Layout ➝ %sContent Area Spacing%s.', 'blocksy' ), // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText
								blocksy_safe_sprintf(
									'<a data-trigger-section="general:layout_panel" href="%s">',
									admin_url( '/customize.php?autofocus[section]=general&ct_autofocus=general:layout_panel' )
								),
								'</a>'
							),
						),

					),
				),

				blocksy_rand_md5() => array(
					'title'   => __( 'Design', 'blocksy' ),
					'type'    => 'tab',
					'options' => array(
						'shop_archive_background' => array(
							'label'      => __( 'Page Background', 'blocksy' ),
							'type'       => 'ct-background',
							'design'     => 'block:right',
							'responsive' => true,
							'sync'       => 'live',
							'divider'    => 'bottom',
							'value'      => blocksy_background_default_value(
								array(
									'backgroundColor' => array(
										'default' => array(
											'color' => Blocksy_Css_Injector::get_skip_rule_keyword(),
										),
									),
								)
							),
							'desc'       => blocksy_safe_sprintf(
								// translators: placeholder here means the actual URL.
								__( 'Please note, by default this option is inherited from Colors ➝ %sSite Background%s.', 'blocksy' ), // phpcs:ignore WordPress.WP.I18n.UnorderedPlaceholdersText
								blocksy_safe_sprintf(
									'<a data-trigger-section="color" href="%s">',
									admin_url( '/customize.php?autofocus[section]=color' )
								),
								'</a>'
							),
						),
					),
				),

				blocksy_rand_md5() => array(
					'type'  => 'ct-title',
					'label' => __( 'Page Elements', 'blocksy' ),
				),
			),

			/**
			 * Filters the options the companion adds for the off-canvas filters
			 * of the Product Archives section.
			 *
			 * @since 2.0.1
			 *
			 * @param array $options Options, empty by default.
			 */
			apply_filters(
				'blocksy:options:woocommerce:archive:filters-canvas',
				array()
			),

			/**
			 * Filters the options the companion adds for the active filters of
			 * the Product Archives section.
			 *
			 * @since 2.0.1
			 *
			 * @param array $options Options, empty by default.
			 */
			apply_filters(
				'blocksy:options:woocommerce:archive:active-filters',
				array()
			),

			blocksy_get_options( 'woocommerce/results-count' ),

			blocksy_get_options( 'woocommerce/products-sorting' ),

			blocksy_get_options(
				'general/sidebar-particular',
				array(
					'prefix' => 'woo_categories',
				)
			),

			blocksy_get_options(
				'general/pagination',
				array(
					'prefix' => 'woo_categories',
				)
			),

			array(
				blocksy_rand_md5() => array(
					'type'  => 'ct-title',
					'label' => __( 'Functionality Options', 'blocksy' ),
				),
			),

			/**
			 * Filters the options the companion adds for AJAX filtering in the
			 * Product Archives section.
			 *
			 * @since 2.0.1
			 *
			 * @param array $options Options, empty by default.
			 */
			apply_filters(
				'blocksy:options:woocommerce:archive:ajax-filtering',
				array()
			),

			array(
				'product_catalog_panel' => array(
					'label'         => __( 'Product Catalog', 'blocksy' ),
					'type'          => 'ct-panel',
					'wrapperAttr'   => array( 'data-panel' => 'only-arrow' ),
					'setting'       => array( 'transport' => 'postMessage' ),
					'inner-options' => array(

						'woocommerce_shop_page_display' => array(
							'label'       => __( 'Shop page display', 'blocksy' ),
							'type'        => 'ct-select',
							'value'       => '',
							'view'        => 'text',
							'placeholder' => __( 'Show products', 'blocksy' ),
							'design'      => 'block',
							'setting'     => array(
								'type'       => 'option',
								'capability' => 'manage_woocommerce',
							),
							'desc'        => __( 'Choose what to display on the main shop page.', 'blocksy' ),
							'choices'     => blocksy_ordered_keys(
								array(
									''              => __( 'Show products', 'blocksy' ),
									'subcategories' => __( 'Show categories', 'blocksy' ),
									'both'          => __( 'Show categories & products', 'blocksy' ),
								)
							),
						),

						'woocommerce_category_archive_display' => array(
							'label'       => __( 'Category display', 'blocksy' ),
							'type'        => 'ct-select',
							'value'       => '',
							'view'        => 'text',
							'placeholder' => __( 'Show products', 'blocksy' ),
							'design'      => 'block',
							'setting'     => array(
								'type'       => 'option',
								'capability' => 'manage_woocommerce',
							),
							'desc'        => __( 'Choose what to display on product category pages.', 'blocksy' ),
							'choices'     => blocksy_ordered_keys(
								array(
									''              => __( 'Show products', 'blocksy' ),
									'subcategories' => __( 'Show subcategories', 'blocksy' ),
									'both'          => __( 'Show subcategories & products', 'blocksy' ),
								)
							),
						),

						'woocommerce_default_catalog_orderby' => array(
							'label'   => __( 'Default product sorting', 'blocksy' ),
							'type'    => 'ct-select',
							'value'   => 'menu_order',
							'view'    => 'text',
							'design'  => 'block',
							'desc'    => __( 'How should products be sorted in the catalog by default?', 'blocksy' ),
							'setting' => array(
								'type'       => 'option',
								'capability' => 'manage_woocommerce',
							),
							'choices' => blocksy_ordered_keys(
								apply_filters(
									// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook.
									'woocommerce_default_catalog_orderby_options',
									array(
										'menu_order' => __( 'Default sorting (custom ordering + name)', 'blocksy' ),
										'popularity' => __( 'Popularity (sales)', 'blocksy' ),
										'rating'     => __( 'Average rating', 'blocksy' ),
										'date'       => __( 'Sort by most recent', 'blocksy' ),
										'price'      => __( 'Sort by price (asc)', 'blocksy' ),
										'price-desc' => __( 'Sort by price (desc)', 'blocksy' ),
									)
								)
							),
						),

					),
				),

			),

		),
	),
);
