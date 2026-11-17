<?php

namespace Blocksy;

class SearchModifications {
	use WordPressActionsManager;

	private $filters = array(
		array(
			'action'   => 'rest_post_search_query',
			'priority' => 999,
			'args'     => 2,
		),

		array(
			'action' => 'pre_get_posts',
		),

		array(
			'action' => 'rest_api_init',
		),
	);

	public function __construct() {
		$this->attach_hooks();
	}

	public function rest_api_init() {
		if ( ! isset( $_GET['ct_live_search'] ) || 'true' !== $_GET['ct_live_search'] ) {
			return;
		}

		register_rest_field(
			'search-result',
			'ct_featured_media',
			array(
				'get_callback' => function ( $post, $field_name, $request ) {
					$image_id = get_post_thumbnail_id( $post['id'] );

					if ( ! $image_id ) {
						return null;
					}

					$image = get_post( $image_id );

					if ( ! $image ) {
						return null;
					}

					$controller = new \WP_REST_Attachments_Controller( 'attachment' );

					$attachment = $controller->prepare_item_for_response(
						$image,
						$request
					);

					return $attachment->data;
				},
			)
		);

		/**
		 * Fires after the live search REST fields are registered.
		 *
		 * @since 2.0.69
		 */
		do_action( 'blocksy:rest_api:live_search:fields' );
	}

	public function rest_post_search_query( $args, $request ) {
		// Patch WP_Query args for search only when it's a live search
		if (
			! isset( $_GET['ct_live_search'] )
			||
			'true' !== $_GET['ct_live_search']
		) {
			return $args;
		}

		if (
			is_array( $args['post_type'] )
			&&
			in_array( 'product', $args['post_type'], true )
		) {
			if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
				$meta_query = array();

				if ( isset( $args['meta_query'] ) ) {
					$meta_query = $args['meta_query'];
				}

				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => '_stock_status',
						'value'   => 'outofstock',
						'compare' => '!=',
					),

					array(
						'key'     => '_stock_status',
						'compare' => 'NOT EXISTS',
					),
				);

				$args['meta_query'] = $meta_query;
			}

			if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
				$product_visibility_term_ids = wc_get_product_visibility_term_ids();

				$tax_query = array();

				if ( isset( $args['tax_query'] ) ) {
					$tax_query = $args['tax_query'];
				}

				$tax_query['relation'] = 'AND';

				$tax_query[] = array(
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'term_taxonomy_id',
						'terms'    => $product_visibility_term_ids['exclude-from-search'],
						'operator' => 'NOT IN',
					),
				);

				$args['tax_query'] = $tax_query;
			}

			if ( class_exists( 'Addify_Products_Visibility_Front' ) ) {
				$visibility = new \Addify_Products_Visibility_Front();

				$q = new \WP_Query();

				$visibility->afpvu_custom_pre_get_posts_query( $q );

				foreach ( $q->query_vars as $key => $value ) {
					$args[ $key ] = $value;
				}
			}
		}

		$tax_query = array();

		if ( isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ) {
			$tax_query = $args['tax_query'];
		}

		$tax_query_clause = null;

		if ( isset( $request['ct_tax_query'] ) ) {
			$tax_query_clause = $this->get_tax_query_clause(
				$request['ct_tax_query']
			);
		}

		if ( $tax_query_clause ) {
			$tax_query[] = array(
				'relation' => 'AND',
				$tax_query_clause,
			);
		}

		$args['tax_query'] = $tax_query;

		return $args;
	}

	public function pre_get_posts( $query ) {
		if ( is_admin() || ! $query->is_search ) {
			return $query;
		}

		if (
			! is_search()
			&&
			! wp_doing_ajax()
		) {
			return $query;
		}

		$this->maybe_apply_post_type( $query );
		$this->maybe_apply_tax_query( $query );

		return $query;
	}

	private function maybe_apply_post_type( $query ) {
		if ( empty( $_GET['ct_post_type'] ) ) {
			return;
		}

		$custom_post_types = blocksy_manager()->post_types->get_supported_post_types();

		if ( function_exists( 'is_bbpress' ) ) {
			$custom_post_types[] = 'forum';
			$custom_post_types[] = 'topic';
			$custom_post_types[] = 'reply';
		}

		if ( class_exists( 'Tribe__Events__Main' ) ) {
			$custom_post_types[] = 'tribe_events';
		}

		$allowed_post_types = array();

		$post_types = explode(
			':',
			sanitize_text_field( $_GET['ct_post_type'] )
		);

		$known_cpts = array( 'post', 'page' );

		if ( get_post_type_object( 'product' ) ) {
			$known_cpts[] = 'product';
		}

		foreach ( $post_types as $single_post_type ) {
			if (
				in_array( $single_post_type, $custom_post_types, true )
				||
				in_array( $single_post_type, $known_cpts, true )
			) {
				$allowed_post_types[] = $single_post_type;
			}
		}

		$query->set( 'post_type', $allowed_post_types );

		if ( in_array( 'product', $allowed_post_types, true ) ) {
			if ( 'yes' === get_option( 'woocommerce_hide_out_of_stock_items' ) ) {
				$meta_query = array();

				if ( ! empty( $query->get( 'meta_query' ) ) ) {
					$meta_query = $query->get( 'meta_query' );
				}

				$meta_query[] = array(
					'key'     => '_stock_status',
					'value'   => 'outofstock',
					'compare' => '!=',
				);

				$query->set( 'meta_query', $meta_query );
			}

			if ( function_exists( 'wc_get_product_visibility_term_ids' ) ) {
				$product_visibility_term_ids = wc_get_product_visibility_term_ids();

				$tax_query = array();

				if ( ! empty( $query->get( 'tax_query' ) ) ) {
					$tax_query = $query->get( 'tax_query' );
				}

				$tax_query['relation'] = 'AND';

				$tax_query[] = array(
					array(
						'taxonomy' => 'product_visibility',
						'field'    => 'term_taxonomy_id',
						'terms'    => $product_visibility_term_ids['exclude-from-search'],
						'operator' => 'NOT IN',
					),
				);

				$query->set( 'tax_query', $tax_query );
			}
		}
	}

	// TODO: check if existing tax query exists before overriding new one
	private function maybe_apply_tax_query( $query ) {
		if ( empty( $_GET['ct_tax_query'] ) ) {
			return;
		}

		$tax_query_clause = $this->get_tax_query_clause(
			wp_unslash( $_GET['ct_tax_query'] )
		);

		if ( ! $tax_query_clause ) {
			return;
		}

		$tax_query = array(
			'relation' => 'AND',
		);

		$tax_query[] = array(
			$tax_query_clause,
		);

		$query->set(
			'tax_query',
			array(
				'relation' => 'AND',
				$tax_query,
			)
		);
	}

	private function get_tax_query_clause( $value ) {
		$tax_params = explode( ':', sanitize_text_field( $value ) );

		if (
			count( $tax_params ) !== 2
			||
			! is_taxonomy_viewable( $tax_params[0] )
		) {
			return null;
		}

		return array(
			'taxonomy' => $tax_params[0],
			'field'    => 'id',
			'terms'    => absint( $tax_params[1] ),
			'operator' => 'IN',
		);
	}
}
