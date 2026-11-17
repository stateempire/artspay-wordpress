<?php
/**
 * Checkout page options
 *
 * @copyright 2019-present Creative Themes
 * @license   http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @package   Blocksy
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- include scope, variables come from blocksy_get_options()

$pages = get_pages(
	array(
		'post_type'   => 'page',
		'post_status' => 'publish,private,draft',
		'number'      => 0,
		'child_of'    => 0,
		'parent'      => -1,
		'exclude'     => array(
			wc_get_page_id( 'cart' ),
			wc_get_page_id( 'checkout' ),
			wc_get_page_id( 'myaccount' ),
		),
		'sort_order'  => 'asc',
		'sort_column' => 'post_title',
	)
);

$page_choices_result = array();

$page_choices = array(
	'' => __( 'No page set', 'blocksy' ),
) + array_combine(
	array_map(
		'strval',
		wp_list_pluck( $pages, 'ID' )
	),
	wp_list_pluck( $pages, 'post_title' )
);

foreach ( $page_choices as $page_id => $page_label ) {
	$page_choices_result[ strval( $page_id ) ] = $page_label;
}

// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable -- Returned by the options loader.
$options = array(

	blocksy_rand_md5() => array(
		'label'         => __( 'Checkout Page', 'blocksy' ),
		'type'          => 'ct-panel',
		'setting'       => array( 'transport' => 'postMessage' ),
		'inner-options' => array(

			blocksy_rand_md5() => array(
				'title'   => __( 'General', 'blocksy' ),
				'type'    => 'tab',
				'options' => array(

					array(
						'blocksy_has_checkout_coupon' => array(
							'label'    => __( 'Coupon Form', 'blocksy' ),
							'type'     => 'ct-switch',
							'value'    => false,
							'divider'  => 'bottom:full',
							'behavior' => 'bool',
							'setting'  => array(),
						),
					),

					/**
					 * Filters the options rendered after the checkout coupon form switch.
					 *
					 * @since 2.0.1
					 *
					 * @param array $options Additional checkout options.
					 */
					apply_filters(
						'blocksy_customizer_options:woocommerce:general:coupon:after',
						array()
					),

					'woocommerce_checkout_highlight_required_fields' => array(
						'label'    => __( 'Highlight Required Fields', 'blocksy' ),
						'type'     => 'ct-switch',
						'value'    => 'yes',
						'behavior' => 'bool',
						'divider'  => 'bottom',
						'setting'  => array(
							'type'              => 'option',
							'capability'        => 'manage_woocommerce',
							'sanitize_callback' => 'wc_bool_to_string',
						),
					),

					'woocommerce_checkout_company_field'   => array(
						'label'   => __( 'Company Name Field', 'blocksy' ),
						'type'    => 'ct-select',
						'value'   => 'optional',
						'view'    => 'text',
						'design'  => 'block',
						'choices' => blocksy_ordered_keys(
							array(
								'hidden'   => __( 'Hidden', 'blocksy' ),
								'optional' => __( 'Optional', 'blocksy' ),
								'required' => __( 'Required', 'blocksy' ),
							)
						),
						'setting' => array(
							'type'       => 'option',
							'capability' => 'manage_woocommerce',
						),
					),

					'woocommerce_checkout_address_2_field' => array(
						'label'   => __( 'Address Line 2 Field', 'blocksy' ),
						'type'    => 'ct-select',
						'value'   => 'optional',
						'view'    => 'text',
						'design'  => 'block',
						'choices' => blocksy_ordered_keys(
							array(
								'hidden'   => __( 'Hidden', 'blocksy' ),
								'optional' => __( 'Optional', 'blocksy' ),
								'required' => __( 'Required', 'blocksy' ),
							)
						),
						'setting' => array(
							'type'       => 'option',
							'capability' => 'manage_woocommerce',
						),
					),

					'woocommerce_checkout_phone_field'     => array(
						'label'   => __( 'Phone Field', 'blocksy' ),
						'type'    => 'ct-select',
						'value'   => 'required',
						'view'    => 'text',
						'design'  => 'block',
						'choices' => blocksy_ordered_keys(
							array(
								'hidden'   => __( 'Hidden', 'blocksy' ),
								'optional' => __( 'Optional', 'blocksy' ),
								'required' => __( 'Required', 'blocksy' ),
							)
						),
						'setting' => array(
							'type'       => 'option',
							'capability' => 'manage_woocommerce',
						),
					),

					'wp_page_for_privacy_policy'           => array(
						'label'   => __( 'Privacy Policy Page', 'blocksy' ),
						'type'    => 'ct-select',
						'value'   => '',
						'view'    => 'text',
						'design'  => 'block',
						'divider' => 'top:full',
						'choices' => blocksy_ordered_keys( $page_choices_result ),
						'setting' => array(
							'type'       => 'option',
							'capability' => 'manage_privacy_options',
						),
					),

					'woocommerce_terms_page_id'            => array(
						'label'   => __( 'Terms And Conditions Page', 'blocksy' ),
						'type'    => 'ct-select',
						'value'   => '',
						'view'    => 'text',
						'design'  => 'block',
						'choices' => blocksy_ordered_keys( $page_choices_result ),
						'setting' => array(
							'type'       => 'option',
							'capability' => 'manage_woocommerce',
						),
					),

					'woocommerce_checkout_privacy_policy_text' => array(
						'label'               => __( 'Privacy policy', 'blocksy' ),
						'desc'                => __( 'Optionally add some text about your store privacy policy to show during checkout.', 'blocksy' ),
						'type'                => 'wp-editor',
						'value'               => __( 'Your personal data will be used to process your order, support your experience throughout this website, and for other purposes described in our [privacy_policy].', 'blocksy' ),
						'disableRevertButton' => true,
						'quicktags'           => false,
						'mediaButtons'        => false,
						'tinymce'             => array(
							'toolbar1' => 'bold,italic,link,alignleft,aligncenter,alignright,undo,redo',
						),
						'setting'             => array(
							'type'       => 'option',
							'capability' => 'manage_woocommerce',
						),
					),

					'woocommerce_checkout_terms_and_conditions_checkbox_text' => array(
						'label'               => __( 'Terms and conditions', 'blocksy' ),
						'desc'                => __( 'Optionally add some text for the terms checkbox that customers must accept.', 'blocksy' ),
						'type'                => 'text',
						'value'               => blocksy_safe_sprintf(
							// translators: %s is the terms and conditions page placeholder.
							__(
								'I have read and agree to the website %s',
								'blocksy'
							),
							'[terms]'
						),
						'disableRevertButton' => true,
						'setting'             => array(
							'type'       => 'option',
							'capability' => 'manage_woocommerce',
						),
					),

				),
			),

			blocksy_rand_md5() => array(
				'title'   => __( 'Design', 'blocksy' ),
				'type'    => 'tab',
				'options' => array(

					'checkout_order_border'        => array(
						'label'      => __( 'Order Review Border', 'blocksy' ),
						'type'       => 'ct-border',
						'design'     => 'block',
						'sync'       => 'live',
						'value'      => array(
							'width' => 2,
							'style' => 'solid',
							'color' => array(
								'color' => 'var(--theme-border-color)',
							),
						),
						'responsive' => true,
					),

					'checkout_order_border_radius' => array(
						'label'      => __( 'Order Review Border Radius', 'blocksy' ),
						'type'       => 'ct-spacing',
						'divider'    => 'top',
						'setting'    => array( 'transport' => 'postMessage' ),
						'value'      => blocksy_spacing_value(),
						'min'        => 0,
						'responsive' => true,
					),

					'checkout_order_padding'       => array(
						'label'      => __( 'Order Review Padding', 'blocksy' ),
						'type'       => 'ct-spacing',
						'divider'    => 'top',
						'value'      => array(
							'desktop' => blocksy_spacing_value(
								array(
									'top'    => '35px',
									'left'   => '35px',
									'right'  => '35px',
									'bottom' => '35px',
								)
							),
							'tablet'  => blocksy_spacing_value(
								array(
									'top'    => '30px',
									'left'   => '30px',
									'right'  => '30px',
									'bottom' => '30px',
								)
							),
							'mobile'  => blocksy_spacing_value(
								array(
									'top'    => '25px',
									'left'   => '25px',
									'right'  => '25px',
									'bottom' => '25px',
								)
							),
						),
						'min'        => 0,
						'responsive' => true,
						'sync'       => 'live',
					),

					blocksy_rand_md5()             => array(
						'type'      => 'ct-condition',
						'condition' => array( 'blocksy_has_image_toggle' => 'yes' ),
						'options'   => array(

							'checkout_product_image_border_radius' => array(
								'label'      => __( 'Image Border Radius', 'blocksy' ),
								'type'       => 'ct-spacing',
								'divider'    => 'top',
								'setting'    => array( 'transport' => 'postMessage' ),
								'value'      => blocksy_spacing_value(),
								'inputAttr'  => array(
									'placeholder' => '3',
								),
								'min'        => 0,
								'responsive' => true,
							),

						),
					),

				),
			),
		),
	),

);

if ( ! current_user_can( 'manage_privacy_options' ) ) {
	foreach ( $options as $panel_id => $panel ) {
		foreach ( $panel['inner-options'] as $tab_id => $tab ) {
			unset( $options[ $panel_id ]['inner-options'][ $tab_id ]['options']['wp_page_for_privacy_policy'] );
		}
	}
}
