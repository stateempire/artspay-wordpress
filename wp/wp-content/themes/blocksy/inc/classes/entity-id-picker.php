<?php

namespace Blocksy;

class EntityIdPicker {
	public function mount_ajax_action() {
		add_action(
			'wp_ajax_blocksy_get_all_entities',
			function () {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_send_json_error();
				}

				$maybe_input = json_decode( file_get_contents( 'php://input' ), true );

				if (
				! $maybe_input
				||
				! isset( $maybe_input['entity'] )
				) {
					wp_send_json_error();
				}

				$result = false;

				if ( 'posts' === $maybe_input['entity'] ) {
					$result = $this->get_all_posts( $maybe_input );
				}

				if ( 'users' === $maybe_input['entity'] ) {
					$result = $this->get_all_users( $maybe_input );
				}

				if ( 'taxonomies' === $maybe_input['entity'] ) {
					$result = $this->get_all_taxonomies( $maybe_input );
				}

				if ( ! $result ) {
					wp_send_json_error();
				}

				wp_send_json_success( $result );
			}
		);
	}

	public function get_default_value( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				// posts | users | taxonomies
				'entity'    => 'posts',

				'post_type' => 'post',

				// ID | entity
				'return'    => 'ID',
			)
		);

		$input = array(
			'post_type' => $args['post_type'],
		);

		$result = null;

		if ( 'posts' === $args['entity'] ) {
			$result = $this->get_all_posts(
				$input,
				array(
					'limit' => 1,
				)
			);
		}

		if ( 'users' === $args['entity'] ) {
			$result = $this->get_all_users(
				$input,
				array(
					'limit'               => 1,
					'has_published_posts' => true,
				)
			);
		}

		if ( 'taxonomies' === $args['entity'] ) {
			$result = $this->get_all_taxonomies(
				$input,
				array(
					'limit' => 1,
				)
			);
		}

		if ( $result && ! empty( $result['entities'] ) ) {
			if ( 'ID' === $args['return'] ) {
				return $result['entities'][0]['id'];
			}

			return $result['entities'][0];
		}

		return null;
	}

	private function get_all_taxonomies( $maybe_input, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit' => 20,
			)
		);

		$cpts = blocksy_manager()->post_types->get_supported_post_types();

		$cpts[] = 'post';
		$cpts[] = 'page';
		// $cpts[] = 'product';

		$taxonomies = array();

		if (
			isset( $maybe_input['post_type'] )
			&&
			'product' === $maybe_input['post_type']
		) {
			$cpts = array( 'product' );
		}

		foreach ( $cpts as $cpt ) {
			$taxonomies = array_merge(
				$taxonomies,
				array_values(
					array_diff(
						get_object_taxonomies( $cpt ),
						array( 'post_format' )
					)
				)
			);
		}

		$terms            = array();
		$terms_query_args = array(
			'number'     => $args['limit'],
			'hide_empty' => false,
		);

		if (
			isset( $maybe_input['search_query'] )
			&&
			! empty( $maybe_input['search_query'] )
		) {
			$terms_query_args['search'] = $maybe_input['search_query'];
		}

		foreach ( array_unique( $taxonomies ) as $taxonomy ) {
			$taxonomy_object = get_taxonomy( $taxonomy );

			if ( ! $taxonomy_object->public ) {
				continue;
			}

			$local_terms = array_map(
				function ( $tax ) {
					return array(
						'id'         => $tax->term_id,
						'label'      => htmlspecialchars_decode( $tax->name ),
						'group'      => get_taxonomy( $tax->taxonomy )->label,
						'post_types' => get_taxonomy( $tax->taxonomy )->object_type,
					);
				},
				blocksy_get_terms(
					array_merge(
						array(
							'taxonomy' => $taxonomy,
						),
						$terms_query_args
					),
					array(
						'all_languages' => true,
					)
				)
			);

			if ( empty( $local_terms ) ) {
				continue;
			}

			$terms = array_merge( $terms, $local_terms );
		}

		if ( isset( $maybe_input['alsoInclude'] ) ) {
			$maybe_term = get_term( $maybe_input['alsoInclude'] );

			if ( $maybe_term ) {
				$terms[] = array(
					'id'         => $maybe_term->term_id,
					'label'      => htmlspecialchars_decode( $maybe_term->name ),
					'group'      => get_taxonomy( $maybe_term->taxonomy )->label,
					'post_types' => get_taxonomy( $maybe_term->taxonomy )->object_type,
				);
			}
		}

		return array(
			'entities' => $this->with_uniq_ids( $terms ),
		);
	}

	private function get_all_users( $maybe_input, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit'               => 10,
				'has_published_posts' => null,
			)
		);

		$users = array();

		$user_query_args = array(
			'number'              => $args['limit'],
			'fields'              => array( 'ID', 'user_nicename' ),
			'has_published_posts' => $args['has_published_posts'],
		);

		if (
			isset( $maybe_input['search_query'] )
			&&
			! empty( $maybe_input['search_query'] )
		) {
			$user_query_args['search'] = '*' . $maybe_input['search_query'] . '*';
		}

		$user_query = new \WP_User_Query( $user_query_args );

		foreach ( $user_query->get_results() as $user ) {
			$users[] = array(
				'id'    => $user->ID,
				'label' => $user->user_nicename,
			);
		}

		if ( isset( $maybe_input['alsoInclude'] ) ) {
			$maybe_user = get_user_by( 'id', $maybe_input['alsoInclude'] );

			if ( $maybe_user ) {
				$users[] = array(
					'id'    => $maybe_user->ID,
					'label' => $maybe_user->user_nicename,
				);
			}
		}

		return array(
			'entities' => $this->with_uniq_ids( $users ),
		);
	}

	private function get_all_posts( $maybe_input, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit' => 10,
			)
		);

		if ( ! isset( $maybe_input['post_type'] ) ) {
			return false;
		}

		$query_args = array(
			'posts_per_page'   => $args['limit'],
			'post_type'        => $maybe_input['post_type'],
			'suppress_filters' => true,
			'lang'             => '',
		);

		if (
			isset( $maybe_input['search_query'] )
			&&
			! empty( $maybe_input['search_query'] )
		) {
			if ( intval( $maybe_input['search_query'] ) ) {
				$query_args['p'] = intval( $maybe_input['search_query'] );
			} else {
				$query_args['s'] = $maybe_input['search_query'];
			}
		}

		$initial_query_args_post_type = $query_args['post_type'];

		if ( strpos( $initial_query_args_post_type, 'ct_cpt' ) !== false ) {
			$query_args['post_type'] = blocksy_manager()->post_types->get_all(
				array(
					'exclude_built_in' => true,
					'exclude_woo'      => true,
				)
			);
		}

		if ( strpos( $initial_query_args_post_type, 'ct_all_posts' ) !== false ) {
			$query_args['post_type'] = blocksy_manager()->post_types->get_all();
		}

		if ( ! is_array( $query_args['post_type'] ) ) {
			$query_args['post_type'] = array( $query_args['post_type'] );
		}

		$query = new \WP_Query( $query_args );

		$posts_result = $query->posts;

		if ( isset( $maybe_input['alsoInclude'] ) ) {
			$maybe_post = get_post( $maybe_input['alsoInclude'], 'display' );

			if (
				$maybe_post
				&&
				in_array( $maybe_post->post_type, $query_args['post_type'], true )
			) {
				$posts_result[] = $maybe_post;
			}
		}

		$posts_result = array_map(
			function ( $post ) {
				return array(
					'id'        => $post->ID,
					'label'     => htmlspecialchars_decode( $post->post_title ),
					'post_type' => $post->post_type,
				);
			},
			$posts_result
		);

		return array(
			'entities' => $this->with_uniq_ids( $posts_result ),
		);
	}

	private function with_uniq_ids( $items = array() ) {
		$uniq_ids = array();

		return array_values(
			array_filter(
				$items,
				function ( $item ) use ( &$uniq_ids ) {
					$key = (string) $item['id'];

					if ( isset( $uniq_ids[ $key ] ) ) {
						return false;
					}

					$uniq_ids[ $key ] = true;

					return true;
				}
			)
		);
	}
}
