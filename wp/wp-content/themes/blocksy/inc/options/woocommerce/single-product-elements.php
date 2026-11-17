<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, VariableAnalysis.CodeAnalysis.VariableAnalysis.UndefinedVariable -- include scope, variables come from blocksy_get_variables_from_file()

$woo_single_layout_left_value = blocksy_get_woo_single_layout_defaults( 'left' );

$woo_single_layout_right_value = blocksy_get_woo_single_layout_defaults( 'right' );

$woo_single_layout_html_fields = array(
	'product_add_to_cart'     => array( 'add_to_cart_layer_title' ),
	'product_payment_methods' => array( 'payment_methods_title' ),
	'additional_info'         => array(
		'product_additional_info_title',
		'additional_info_items' => array( 'item_title' ),
	),
);

$options = array( // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
	'woo_product_elements' => array(
		'label'         => __( 'Product Elements', 'blocksy' ),
		'type'          => 'ct-panel',
		'inner-options' => array(

			blocksy_rand_md5() => array(
				'title'   => __( 'General', 'blocksy' ),
				'type'    => 'tab',
				'options' => array(
					array(
						blocksy_rand_md5() => array(
							'type'      => 'ct-condition',
							'condition' => array(
								'product_view_type' => 'default-gallery|stacked-gallery',
							),
							'options'   => array(
								'woo_single_layout' => array(
									'label'      => false,
									'type'       => 'ct-layers',
									'manageable' => false,
									'sync'       => array(
										blocksy_sync_whole_page(
											array(
												'prefix' => 'product',
												'loader_selector' => '.entry-summary-items',
											)
										),

										array(
											'prefix' => 'product',
											'id'     => 'woo_single_layout_skip',
											'loader_selector' => 'skip',
											'container_inclusive' => false,
										),

										array(
											'prefix' => 'product',
											'id'     => 'product_payment_methods',
											'loader_selector' => '.entry-summary-items .ct-payment-methods',
											'container_inclusive' => false,
										),

									),
									'value'      => blocksy_get_woo_single_layout_defaults(),
									'settings'   => blocksy_get_options( 'woocommerce/single-product-layers' ),
									'setting'    => array(
										'sanitize_callback' => function ( $input ) use ( $woo_single_layout_html_fields ) {
											return blocksy_sanitize_layers_html_fields(
												array(
													'value'  => $input,
													'fields' => $woo_single_layout_html_fields,
												)
											);
										},
									),
								),
							),
						),

						blocksy_rand_md5() => array(
							'type'      => 'ct-condition',
							'condition' => array(
								'product_view_type' => 'columns-top-gallery|top-gallery',
							),
							'options'   => array(
								'woo_single_split_layout' => array(
									'type'          => 'ct-layers-combined',
									'design'        => 'none',
									'value'         => array(
										'left'  => $woo_single_layout_left_value,
										'right' => $woo_single_layout_right_value,
									),
									'sync'          => array(
										'id' => 'woo_single_layout',
									),
									'setting'       => array(
										'sanitize_callback' => function ( $input ) use ( $woo_single_layout_html_fields ) {
											if ( ! is_array( $input ) ) {
												return $input;
											}

											foreach ( array( 'left', 'right' ) as $side ) {
												if ( ! isset( $input[ $side ] ) ) {
													continue;
												}

												$input[ $side ] = blocksy_sanitize_layers_html_fields(
													array(
														'value' => $input[ $side ],
														'fields' => $woo_single_layout_html_fields,
													)
												);
											}

											return $input;
										},
									),
									'inner-options' => array(
										'left'  => array(
											'label'      => __( 'Left Column', 'blocksy' ),
											'type'       => 'ct-layers',
											'manageable' => false,
											'grouped'    => true,
											'value'      => $woo_single_layout_left_value,
											'settings'   => blocksy_get_options(
												'woocommerce/single-product-layers'
											),
											'attr'       => array(
												'class' => 'ct-layers-split-layout',
											),
										),

										'right' => array(
											'label'      => __( 'Right Column', 'blocksy' ),
											'type'       => 'ct-layers',
											'manageable' => false,
											'grouped'    => true,
											'value'      => $woo_single_layout_right_value,
											'settings'   => blocksy_get_options(
												'woocommerce/single-product-layers'
											),
											'attr'       => array(
												'class' => 'ct-layers-split-layout',
											),
										),
									),
								),
							),
						),

						blocksy_rand_md5() => array(
							'type' => 'ct-divider',
						),
					),

					/**
					 * Filters the options appended after the single product
					 * layers list.
					 *
					 * @since 2.0.1
					 *
					 * @param array $options List of option definitions.
					 */
					apply_filters( 'blocksy_woo_single_options:after_layers', array() ),

					blocksy_rand_md5() => array(
						'type'      => 'ct-condition',
						'condition' => array(
							'product_view_type' => 'default-gallery|stacked-gallery',
						),
						'options'   => array(
							'has_product_sticky_summary' => array(
								'label' => __( 'Sticky Container', 'blocksy' ),
								'type'  => 'ct-switch',
								'value' => 'no',
								'sync'  => 'live',
							),
						),
					),
				),
			),

			blocksy_rand_md5() => array(
				'title'   => __( 'Design', 'blocksy' ),
				'type'    => 'tab',
				'options' => array(
					array(
						blocksy_rand_md5() => array(
							'type'            => 'ct-condition',
							'condition'       => array( 'woo_single_layout:array-ids:product_title:enabled' => '!no' ),
							'computed_fields' => array( 'woo_single_layout' ),
							'options'         => array(

								'singleProductTitleFont'  => array(
									'type'    => 'ct-typography',
									'label'   => __( 'Title Font', 'blocksy' ),
									'value'   => blocksy_typography_default_values(
										array(
											'size' => '30px',
										)
									),
									'setting' => array( 'transport' => 'postMessage' ),
								),

								'singleProductTitleColor' => array(
									'label'   => __( 'Title Font Color', 'blocksy' ),
									'type'    => 'ct-color-picker',
									'design'  => 'inline',
									'setting' => array( 'transport' => 'postMessage' ),

									'value'   => array(
										'default' => array(
											'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
										),
									),

									'pickers' => array(
										array(
											'title'   => __( 'Initial', 'blocksy' ),
											'id'      => 'default',
											'inherit' => 'var(--theme-heading-1-color, var(--theme-headings-color))',
										),
									),
								),

							),
						),

						blocksy_rand_md5() => array(
							'type'            => 'ct-condition',
							'condition'       => array( 'woo_single_layout:array-ids:product_price:enabled' => '!no' ),
							'computed_fields' => array( 'woo_single_layout' ),
							'options'         => array(

								'singleProductPriceFont'  => array(
									'type'    => 'ct-typography',
									'label'   => __( 'Price Font', 'blocksy' ),
									'value'   => blocksy_typography_default_values(
										array(
											'size'      => '20px',
											'variation' => 'n7',
										)
									),
									'setting' => array( 'transport' => 'postMessage' ),
									'divider' => 'top:full',
								),

								'singleProductPriceColor' => array(
									'label'   => __( 'Price Font Color', 'blocksy' ),
									'type'    => 'ct-color-picker',
									'design'  => 'inline',
									'setting' => array( 'transport' => 'postMessage' ),

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
							'type'            => 'ct-condition',
							'condition'       => array( 'woo_single_layout:array-ids:product_breadcrumbs:enabled' => '!no' ),
							'computed_fields' => array( 'woo_single_layout' ),
							'options'         => array(

								'singleProductBreadcrumbsFont' => array(
									'type'    => 'ct-typography',
									'label'   => __( 'Breadcrumbs Font', 'blocksy' ),
									'value'   => blocksy_typography_default_values( array() ),
									'design'  => 'block',
									'sync'    => 'live',
									'divider' => 'top:full',
								),

								'singleProductBreadcrumbsFontColor' => array(
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

						blocksy_rand_md5() => array(
							'type'            => 'ct-condition',
							'condition'       => array( 'woo_single_layout:array-ids:product_add_to_cart:enabled' => '!no' ),
							'computed_fields' => array( 'woo_single_layout' ),
							'options'         => array(

								'quantity_color'         => array(
									'label'   => __( 'Quantity Color', 'blocksy' ),
									'type'    => 'ct-color-picker',
									'design'  => 'inline',
									'divider' => 'top:full',
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
											'inherit' => 'var(--quantity-initial-color, var(--theme-button-background-initial-color))',
										),

										array(
											'title'   => __( 'Hover', 'blocksy' ),
											'id'      => 'hover',
											'inherit' => 'var(--quantity-hover-color, var(--theme-button-background-hover-color))',
										),
									),
								),

								'quantity_arrows'        => array(
									'label'   => __( 'Quantity Arrows Color', 'blocksy' ),
									'type'    => 'ct-color-picker',
									'design'  => 'inline',
									'sync'    => 'live',
									'value'   => array(
										'default'        => array(
											'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
										),

										'default_type_2' => array(
											'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
										),

										'hover'          => array(
											'color' => Blocksy_Css_Injector::get_skip_rule_keyword( 'DEFAULT' ),
										),
									),

									'pickers' => array(
										array(
											'title'     => __( 'Initial', 'blocksy' ),
											'id'        => 'default',
											'inherit'   => 'var(--quantity-arrows-initial-color, #fff)',
											'condition_source' => 'global',
											'condition' => array( 'quantity_type' => 'type-1' ),
										),

										array(
											'title'     => __( 'Initial', 'blocksy' ),
											'id'        => 'default_type_2',
											'inherit'   => 'var(--quantity-arrows-initial-color, var(--theme-text-color))',
											'condition_source' => 'global',
											'condition' => array( 'quantity_type' => 'type-2' ),
										),

										array(
											'title'   => __( 'Hover', 'blocksy' ),
											'id'      => 'hover',
											'inherit' => 'var(--quantity-arrows-hover-color, #fff)',
										),
									),
								),

								blocksy_rand_md5()       => array(
									'type'      => 'ct-title',
									'variation' => 'small-divider',
									'label'     => __( 'Add To Cart Button', 'blocksy' ),
								),

								'add_to_cart_text'       => array(
									'label'   => __( 'Button Font Color', 'blocksy' ),
									'type'    => 'ct-color-picker',
									'design'  => 'inline',
									// 'responsive' => true,
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

								'add_to_cart_background' => array(
									'label'   => __( 'Button Background Color', 'blocksy' ),
									'type'    => 'ct-color-picker',
									'design'  => 'inline',
									// 'responsive' => true,
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

								blocksy_rand_md5()       => array(
									'type'      => 'ct-condition',
									'condition' => array( 'woo_single_layout:array-ids:product_add_to_cart:has_ajax_add_to_cart' => 'yes' ),
									'options'   => array(

										blocksy_rand_md5() => array(
											'type'      => 'ct-title',
											'variation' => 'small-divider',
											'label'     => __( 'View Cart Button', 'blocksy' ),
										),

										'view_cart_button_text' => array(
											'label'   => __( 'Button Font Color', 'blocksy' ),
											'type'    => 'ct-color-picker',
											'design'  => 'inline',
											// 'responsive' => true,
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
													'title' => __( 'Initial', 'blocksy' ),
													'id' => 'default',
													'inherit' => 'var(--theme-text-color)',
												),

												array(
													'title' => __( 'Hover', 'blocksy' ),
													'id' => 'hover',
													'inherit' => 'var(--theme-text-color)',
												),
											),
										),

										'view_cart_button_background' => array(
											'label'   => __( 'Button Background Color', 'blocksy' ),
											'type'    => 'ct-color-picker',
											'design'  => 'inline',
											// 'responsive' => true,
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
													'title' => __( 'Initial', 'blocksy' ),
													'id' => 'default',
													'inherit' => 'rgba(224,229,235,0.6)',
												),

												array(
													'title' => __( 'Hover', 'blocksy' ),
													'id' => 'hover',
													'inherit' => 'rgba(224,229,235,1)',
												),
											),
										),

									),
								),
							),
						),

						blocksy_rand_md5() => array(
							'type'            => 'ct-condition',
							'condition'       => array( 'woo_single_layout:array-ids:divider:enabled' => '!no' ),
							'computed_fields' => array( 'woo_single_layout' ),
							'options'         => array(

								'woo_single_layers_divider' => array(
									'label'   => __( 'Divider', 'blocksy' ),
									'type'    => 'ct-border',
									'sync'    => 'live',
									'design'  => 'inline',
									'divider' => 'top:full',
									'value'   => array(
										'width' => 1,
										'style' => 'solid',
										'color' => array(
											'color' => 'var(--theme-border-color)',
										),
									),
								),

							),
						),

						blocksy_rand_md5() => array(
							'type'            => 'ct-condition',
							'condition'       => array(
								'woo_single_layout:array-ids:product_payment_methods:enabled' => '!no',
								'woo_single_layout:array-ids:product_payment_methods:payment_icons_color' => 'custom',
							),
							'computed_fields' => array(
								'woo_single_layout',
							),
							'options'         => array(

								'payment_method_icons_color' => array(
									'label'   => __( 'Payment Methods Icons Color', 'blocksy' ),
									'type'    => 'ct-color-picker',
									'design'  => 'inline',
									'divider' => 'top:full',
									'setting' => array( 'transport' => 'postMessage' ),

									'value'   => array(
										'default' => array(
											'color' => '#4B4F58',
										),
									),

									'pickers' => array(
										array(
											'title' => __( 'Initial', 'blocksy' ),
											'id'    => 'default',
										),
									),
								),

							),
						),

					),

					/**
					 * Filters the options appended to the end of the single
					 * product elements design tab.
					 *
					 * @since 2.0.1
					 *
					 * @param array $options List of option definitions.
					 */
					apply_filters(
						'blocksy:options:single_product:elements:design_tab:end',
						array()
					),

					array(
						blocksy_rand_md5() => array(
							'type'      => 'ct-condition',
							'condition' => array(
								'product_view_type' => 'columns-top-gallery|top-gallery',
							),
							'options'   => array(

								'entry_summary_container_border' => array(
									'label'   => __( 'Container Border', 'blocksy' ),
									'type'    => 'ct-border',
									'sync'    => 'live',
									'design'  => 'inline',
									'divider' => 'top:full',
									'value'   => array(
										'width' => 1,
										'style' => 'solid',
										'color' => array(
											'color' => 'var(--theme-border-color)',
										),
									),
								),

								'entry_summary_container_border_radius' => array(
									'label'      => __( 'Container Border Radius', 'blocksy' ),
									'sync'       => 'live',
									'type'       => 'ct-spacing',
									'divider'    => 'top',
									'value'      => blocksy_spacing_value(),
									'min'        => 0,
									'responsive' => true,
								),

							),
						),
					),
				),
			),

		),
	),
);
