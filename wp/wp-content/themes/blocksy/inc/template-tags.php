<?php
/**
 * Custom template tags for this theme
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package Blocksy
 */

/**
 * Single entry title.
 *
 * @param string $tag HTML tag.
 */
if ( ! function_exists( 'blocksy_entry_title' ) ) {
	function blocksy_entry_title( $tag = 'h2', $has_link = true, $classes = array( 'entry-title' ) ) {
		if ( empty( get_the_title() ) ) {
			return '';
		}

		$title = get_the_title();

		if ( $has_link ) {
			$title = blocksy_html_tag(
				'a',
				array(
					'href' => esc_url( get_permalink() ),
					'rel'  => 'bookmark',
				),
				$title
			);
		}

		return blocksy_html_tag(
			esc_attr( $tag ),
			array(
				'class' => esc_attr( implode( ' ', $classes ) ),
			),
			$title
		);
	}
}

function blocksy_get_excerpt_output( $excerpt ) {
	/**
	 * Filters the excerpt rendered by blocksy_entry_excerpt().
	 *
	 * @since 1.8.7
	 *
	 * @param string|null $excerpt The excerpt markup.
	 */
	return apply_filters( 'blocksy:excerpt:output', $excerpt );
}

/**
 * Output entry excerpt.
 *
 * @param number $length Number of words allowed in excerpt.
 */
if ( ! function_exists( 'blocksy_entry_excerpt' ) ) {
	function blocksy_entry_excerpt( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'length'         => 40,
				'container_tag'  => 'div',
				'class'          => 'entry-excerpt',
				'post_id'        => null,

				// excerpt | full | custom
				'source'         => 'excerpt',
				'custom_exceprt' => '', // for custom only
				'skip_container' => false,
			)
		);

		add_filter(
			'excerpt_length',
			'blocksy_excerpt_length',
			PHP_INT_MAX
		);

		$excerpt_additions = '';

		ob_start();
		$post_excerpt      = get_the_excerpt( $args['post_id'] );
		$excerpt_additions = ob_get_clean();

		remove_filter(
			'excerpt_length',
			'blocksy_excerpt_length',
			PHP_INT_MAX
		);

		if ( 'excerpt' === $args['source'] && empty( trim( $post_excerpt ) ) ) {
			return '';
		}

		$post = get_post( $args['post_id'] );

		$has_native_excerpt = $post->post_excerpt;

		// Check for woo product ( wysiwyg editor )
		$is_product = 'product' === $post->post_type;

		$excerpt = null;

		if ( 'excerpt' === $args['source'] ) {
			if ( ! $is_product ) {
				if ( $has_native_excerpt ) {
					$excerpt = $post_excerpt;

					$excerpt = blocksy_get_excerpt_output( $excerpt );
				}

				if ( ! $excerpt ) {
					$excerpt = $post_excerpt;

					ob_start();
					blocksy_trim_excerpt( $excerpt, $args['length'] );
					$excerpt = ob_get_clean();
				}
			}

			if ( $is_product ) {
				$excerpt = apply_filters(
					'woocommerce_short_description', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party hook
					$post->post_excerpt
				);

				if ( ! empty( $excerpt ) ) {
					ob_start();
					blocksy_trim_excerpt( $excerpt, $args['length'] );
					$excerpt = ob_get_clean();
				}

				$excerpt = blocksy_get_excerpt_output( $excerpt );
			}
		}

		if ( 'full' === $args['source'] ) {
			$args['class'] .= ' entry-content is-layout-flow';

			if ( ! $is_product ) {
				ob_start();
				the_content(
					blocksy_safe_sprintf(
						wp_kses(
							/* translators: 1: span open 2: Name of current post. Only visible to screen readers 3: span closing */
							__(
								'Continue reading%1$s "%2$s"%3$s',
								'blocksy'
							),
							array(
								'span' => array(
									'class' => array(),
								),
							)
						),
						'<span class="screen-reader-text">',
						get_the_title(),
						'</span>'
					)
				);
				$excerpt = ob_get_clean();
			}

			if ( $is_product ) {
				$excerpt = apply_filters(
					'woocommerce_short_description', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- third-party hook
					$post->post_excerpt
				);
			}

			$excerpt = blocksy_get_excerpt_output( $excerpt );
		}

		if ( 'custom' === $args['source'] ) {
			ob_start();
			blocksy_trim_excerpt( $args['custom_exceprt'], $args['length'] );
			$excerpt = ob_get_clean();

			$excerpt = blocksy_get_excerpt_output( $excerpt );
		}

		if ( $args['skip_container'] ) {
			return $excerpt_additions . do_shortcode( $excerpt );
		}

		$result = $excerpt_additions . do_shortcode( $excerpt );

		return blocksy_html_tag(
			$args['container_tag'],
			array(
				'class' => esc_attr( $args['class'] ),
			),
			'full' !== $args['source'] ? wpautop( $result ) : $result
		);
	}
}

/**
 * Output post navigation.
 */
if ( ! function_exists( 'blocksy_get_post_navigation_boundary_post' ) ) {
	function blocksy_get_post_navigation_boundary_post( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'previous'     => true,
				'in_same_term' => false,
				'taxonomy'     => 'category',
			)
		);

		$post = get_post();

		if ( ! $post ) {
			return null;
		}

		$order = $args['previous'] ? 'DESC' : 'ASC';

		$query_args = array(
			'fields'              => 'ids',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
			'orderby'             => array(
				'post_date' => $order,
				'ID'        => $order,
			),
			'post__not_in'        => array( $post->ID ),
			'post_status'         => array( 'publish' ),
			'post_type'           => $post->post_type,
			'posts_per_page'      => 1,
		);

		if ( is_user_logged_in() ) {
			$query_args['perm']        = 'readable';
			$query_args['post_status'] = array_merge(
				$query_args['post_status'],
				get_post_stati( array( 'private' => true ) )
			);
		}

		if ( $args['in_same_term'] ) {
			if (
			! taxonomy_exists( $args['taxonomy'] )
			||
			! is_object_in_taxonomy( $post->post_type, $args['taxonomy'] )
			) {
				return null;
			}

			$term_ids = wp_get_object_terms(
				$post->ID,
				$args['taxonomy'],
				array(
					'fields' => 'ids',
				)
			);

			if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
				return null;
			}

			$query_args['tax_query'] = array(
				array(
					'field'    => 'term_id',
					'taxonomy' => $args['taxonomy'],
					'terms'    => $term_ids,
				),
			);
		}

		$boundary_posts = get_posts( $query_args );

		if ( empty( $boundary_posts ) ) {
			return null;
		}

		return get_post( $boundary_posts[0] );
	}
}

function blocksy_get_post_navigation_next_post( $post ) {
	/**
	 * Filters the post linked as next in the single post navigation.
	 *
	 * @since 1.7.54
	 *
	 * @param WP_Post|null|string $post The adjacent post, or the boundary post when infinite navigation is enabled.
	 */
	return apply_filters( 'blocksy:post-navigation:next-post', $post );
}

function blocksy_get_post_navigation_previous_post( $post ) {
	/**
	 * Filters the post linked as previous in the single post navigation.
	 *
	 * @since 1.7.54
	 *
	 * @param WP_Post|null|string $post The adjacent post, or the boundary post when infinite navigation is enabled.
	 */
	return apply_filters( 'blocksy:post-navigation:previous-post', $post );
}

if ( ! function_exists( 'blocksy_post_navigation' ) ) {
	function blocksy_post_navigation() {
		$prefix = blocksy_manager()->screen->get_prefix();

		$in_same_term      = false;
		$post_nav_taxonomy = 'category';

		$next_adjacent_post = get_adjacent_post( false, '', true );

		$next_post = blocksy_get_post_navigation_next_post(
			$next_adjacent_post
		);

		$previous_adjacent_post = get_adjacent_post( false, '', false );

		$previous_post = blocksy_get_post_navigation_previous_post(
			$previous_adjacent_post
		);

		$post_nav_criteria = blocksy_get_theme_mod( $prefix . '_post_nav_criteria', 'default' );

		if ( 'default' !== $post_nav_criteria ) {
			$in_same_term              = true;
			$post_type                 = get_post_type();
			$post_nav_taxonomy_default = array_keys(
				blocksy_get_taxonomies_for_cpt(
					$post_type
				)
			)[0];

			$post_nav_taxonomy = blocksy_get_theme_mod(
				$prefix . '_post_nav_taxonomy',
				$post_nav_taxonomy_default
			);

			$next_adjacent_post = get_adjacent_post( true, '', true, $post_nav_taxonomy );

			$next_post = blocksy_get_post_navigation_next_post(
				$next_adjacent_post
			);

			$previous_adjacent_post = get_adjacent_post( true, '', false, $post_nav_taxonomy );

			$previous_post = blocksy_get_post_navigation_previous_post(
				$previous_adjacent_post
			);
		}

		$post_nav_infinite = blocksy_get_theme_mod( $prefix . '_post_nav_infinite', 'no' );

		if ( 'yes' === $post_nav_infinite ) {
			if ( ! $next_adjacent_post && ! $next_post ) {
				$next_post = blocksy_get_post_navigation_next_post(
					blocksy_get_post_navigation_boundary_post(
						array(
							'in_same_term' => $in_same_term,
							'previous'     => true,
							'taxonomy'     => $post_nav_taxonomy,
						)
					)
				);
			}

			if ( ! $previous_adjacent_post && ! $previous_post ) {
				$previous_post = blocksy_get_post_navigation_previous_post(
					blocksy_get_post_navigation_boundary_post(
						array(
							'in_same_term' => $in_same_term,
							'previous'     => false,
							'taxonomy'     => $post_nav_taxonomy,
						)
					)
				);
			}
		}

		if ( ! $next_post && ! $previous_post ) {
			return '';
		}

		$title_class = 'item-title';

		$title_class .= ' ' . blocksy_visibility_classes(
			blocksy_get_theme_mod(
				$prefix . '_post_nav_title_visibility',
				array(
					'desktop' => true,
					'tablet'  => true,
					'mobile'  => false,
				)
			)
		);

		$thumb_size = blocksy_get_theme_mod( $prefix . '_post_nav_thumb_size', 'medium' );

		$thumb_class = '';

		$thumb_class .= ' ' . blocksy_visibility_classes(
			blocksy_get_theme_mod(
				$prefix . '_post_nav_thumb_visibility',
				array(
					'desktop' => true,
					'tablet'  => true,
					'mobile'  => true,
				)
			)
		);

		$container_class = 'post-navigation is-width-constrained';

		$container_class .= ' ' . blocksy_visibility_classes(
			blocksy_get_theme_mod(
				$prefix . '_post_nav_visibility',
				array(
					'desktop' => true,
					'tablet'  => true,
					'mobile'  => true,
				)
			)
		);

		$post_slug = get_post_type_object( get_post_type() )->labels->singular_name;
		$post_slug = '<span>' . $post_slug . '</span>';

		$next_post_image_output     = '';
		$previous_post_image_output = '';

		if ( $next_post ) {
			$next_title = '';

			$next_title = $next_post->post_title;

			if ( get_post_thumbnail_id( $next_post ) ) {
				$next_post_image_output = blocksy_media(
					array(
						'attachment_id' => get_post_thumbnail_id( $next_post ),
						'post_id'       => $next_post->ID,
						'ratio'         => '1/1',
						'size'          => $thumb_size,
						'class'         => $thumb_class,
						'inner_content' => '<svg width="20px" height="15px" viewBox="0 0 20 15" fill="#ffffff"><polygon points="0,7.5 5.5,13 6.4,12.1 2.4,8.1 20,8.1 20,6.9 2.4,6.9 6.4,2.9 5.5,2 "/></svg>',
						'tag_name'      => 'figure',
					)
				);
			}
		}

		if ( $previous_post ) {
			$previous_title = '';

			$previous_title = $previous_post->post_title;

			if ( get_post_thumbnail_id( $previous_post ) ) {
				$previous_post_image_output = blocksy_media(
					array(
						'attachment_id' => get_post_thumbnail_id( $previous_post ),
						'post_id'       => $previous_post->ID,
						'ratio'         => '1/1',
						'size'          => $thumb_size,
						'class'         => $thumb_class,
						'inner_content' => '<svg width="20px" height="15px" viewBox="0 0 20 15" fill="#ffffff"><polygon points="14.5,2 13.6,2.9 17.6,6.9 0,6.9 0,8.1 17.6,8.1 13.6,12.1 14.5,13 20,7.5 "/></svg>',
						'tag_name'      => 'figure',
					)
				);
			}
		}

		$prefix = blocksy_manager()->screen->get_prefix();

		$deep_link_args = array(
			'prefix' => $prefix,
			'suffix' => $prefix . '_has_post_nav',
		);

		ob_start();

		?>

		<nav class="<?php echo esc_attr( $container_class ); ?>" <?php echo blocksy_generic_get_deep_link( $deep_link_args ); ?>>
			<?php if ( $next_post ) { ?>
				<a href="<?php echo esc_url( get_permalink( $next_post ) ); ?>" class="nav-item-prev">
					<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $next_post_image_output;
					?>

					<div class="item-content">
						<span class="item-label">
							<?php
								echo wp_kses_post(
									blocksy_safe_sprintf(
										/**
										 * Filters the label of the previous post link. `%s` is replaced with the post type name.
										 *
										 * @since 2.0.1
										 *
										 * @param string $label The link label.
										 */
										apply_filters(
											'blocksy:post-navigation:previous-post:label',
											// translators: post title
											__( 'Previous %s', 'blocksy' )
										),
										$post_slug
									)
								);
							?>
						</span>

						<?php if ( ! empty( $next_title ) ) { ?>
							<span class="<?php echo esc_attr( $title_class ); ?>">
								<?php echo wp_kses_post( $next_title ); ?>
							</span>
						<?php } ?>
					</div>

				</a>
			<?php } else { ?>
				<div class="nav-item-prev"></div>
			<?php } ?>

				<?php if ( $previous_post ) { ?>
				<a href="<?php echo esc_url( get_permalink( $previous_post ) ); ?>" class="nav-item-next">
					<div class="item-content">
						<span class="item-label">
							<?php
								echo wp_kses_post(
									blocksy_safe_sprintf(
										/**
										 * Filters the label of the next post link. `%s` is replaced with the post type name.
										 *
										 * @since 2.0.1
										 *
										 * @param string $label The link label.
										 */
										apply_filters(
											'blocksy:post-navigation:next-post:label',
											// translators: post title
											__( 'Next %s', 'blocksy' )
										),
										$post_slug
									)
								);
							?>
						</span>

						<?php if ( ! empty( $previous_title ) ) { ?>
							<span class="<?php echo esc_attr( $title_class ); ?>">
								<?php echo wp_kses_post( $previous_title ); ?>
							</span>
						<?php } ?>
					</div>

					<?php
						// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						echo $previous_post_image_output;
					?>
				</a>
			<?php } else { ?>
				<div class="nav-item-next"></div>
			<?php } ?>

		</nav>

		<?php

		return ob_get_clean();
	}
}

/**
 * Output related posts for a single post.
 *
 * @param number $per_page Number of posts to output.
 */
if ( ! function_exists( 'blocksy_related_posts' ) ) {
	function blocksy_related_posts( $location = null ) {
		global $post;

		$prefix   = blocksy_manager()->screen->get_prefix();
		$per_page = intval( blocksy_get_theme_mod( $prefix . '_related_posts_count', 3 ) );

		$related_posts_slideshow = blocksy_get_theme_mod(
			$prefix . '_related_posts_slideshow',
			'default'
		);

		if ( 'slider' === $related_posts_slideshow ) {
			$per_page = intval(
				blocksy_get_theme_mod(
					$prefix . '_related_posts_slideshow_number_of_items',
					6
				)
			);
		}

		$post_type = get_post_type( $post );

		$taxonomy = blocksy_get_theme_mod(
			$prefix . '_related_criteria',
			array_keys( blocksy_get_taxonomies_for_cpt( $post_type ) )[0]
		);

		$all_taxonomy_ids = array();

		if ( $taxonomy ) {
			$all_taxonomies = get_the_terms( $post->ID, $taxonomy );

			if ( $all_taxonomies ) {
				foreach ( $all_taxonomies as $current_taxonomy ) {
					if ( ! isset( $current_taxonomy->term_id ) ) {
						continue;
					}

					$current_term_id = $current_taxonomy->term_id;

					if ( function_exists( 'pll_get_term' ) ) {
						$current_lang    = blocksy_get_current_language();
						$current_term_id = pll_get_term( $current_term_id, $current_lang );
					}

					if ( ! $current_term_id ) {
						continue;
					}

					$all_taxonomy_ids[] = $current_term_id;
				}
			}
		}

		$query_args = array_merge(
			array(
				'ignore_sticky_posts' => 0,
				'posts_per_page'      => $per_page,
				'post__not_in'        => array( $post->ID ),
				'post_type'           => $post_type,
				'fields'              => 'ids',
				'no_found_rows'       => true,
			),
			! empty( $all_taxonomy_ids ) ? array(
				'tax_query' => array(
					array(
						'field'    => 'id',
						'taxonomy' => $taxonomy,
						'terms'    => $all_taxonomy_ids,
					),
				),
			) : array()
		);

		$sort = blocksy_get_theme_mod(
			$prefix . '_related_sort',
			'recent'
		);

		if ( 'default' !== $sort ) {
			$orderby_map = array(
				'random'    => 'rand',
				'recent'    => 'post_date',
				'commented' => 'comment_count',
			);

			if ( isset( $orderby_map[ $sort ] ) ) {
				$query_args['orderby'] = $orderby_map[ $sort ];
			}
		}

		/**
		 * Filters the WP_Query arguments used to fetch the related posts.
		 *
		 * @since 1.7.54
		 *
		 * @param array $query_args The query arguments.
		 */
		$query_args = apply_filters( 'blocksy:related-posts:query-args', $query_args );

		/**
		 * Filters the query that provides the related posts.
		 *
		 * @since 1.8.0
		 *
		 * @param WP_Query $query      The related posts query.
		 * @param array    $query_args The query arguments.
		 * @param string   $prefix     Current screen prefix.
		 */
		$query = apply_filters(
			'blocksy:related-posts:query',
			new WP_Query( $query_args ),
			$query_args,
			$prefix
		);

		$label = do_shortcode(
			/**
			 * Filters the related posts module title.
			 *
			 * @since 1.8.7
			 *
			 * @param string $label The module title.
			 */
			apply_filters(
				'blocksy:related-posts:module-label',
				blocksy_get_theme_mod(
					$prefix . '_related_label',
					__( 'Related Posts', 'blocksy' )
				)
			)
		);

		$meta_elements = blocksy_get_theme_mod(
			$prefix . '_related_single_meta_elements',
			blocksy_post_meta_defaults(
				array(
					array(
						'id'      => 'post_date',
						'enabled' => true,
					),

					array(
						'id'      => 'comments',
						'enabled' => true,
					),
				)
			)
		);

		$related_visibility = blocksy_visibility_classes(
			blocksy_get_theme_mod(
				$prefix . '_related_visibility',
				array(
					'desktop' => true,
					'tablet'  => true,
					'mobile'  => true,
				)
			)
		);

		$class = trim(
			'ct-related-posts-container' . ' ' . $related_visibility
		);

		if ( ! $query->have_posts() ) {
			wp_reset_postdata();
			return;
		}

		$boxed_container_class = array( 'ct-related-posts' );

		if ( 'separated' !== $location ) {
			$boxed_container_class[] = 'is-width-constrained';
			$boxed_container_class[] = $related_visibility;
		}

		if ( 'slider' === $related_posts_slideshow ) {
			$boxed_container_class[] = 'is-layout-slider';

			wp_enqueue_style( 'ct-flexy-styles' );
		}

		$boxed_container_class = implode( ' ', $boxed_container_class );

		$label_tag = blocksy_validate_html_tag(
			array(
				'tag'     => blocksy_get_theme_mod( $prefix . '_related_label_wrapper' ),
				'allowed' => 'headings_text',
				'default' => 'h3',
			)
		);

		$posts_title_tag = blocksy_get_theme_mod( $prefix . '_related_posts_title_tag', 'h4' );

		$container_class = 'ct-container';

		if ( blocksy_get_theme_mod( $prefix . '_related_structure', 'normal' ) === 'narrow' ) {
			$container_class = 'ct-container-narrow';
		}

		$container_attributes = array(
			'class'       => 'ct-related-posts-items',
			'data-layout' => 'grid',
		);

		$related_order = blocksy_get_theme_mod(
			$prefix . '_related_order',
			array(
				array(
					'id'      => 'featured_image',
					'enabled' => true,
				),
			)
		);

		foreach ( $related_order as $related_layer ) {
			if (
				! $related_layer['enabled']
				||
				'featured_image' !== $related_layer['id']
			) {
				continue;
			}

			$hover_effect = blocksy_akg(
				'image_hover_effect',
				$related_layer,
				'none'
			);

			if ( 'none' !== $hover_effect ) {
					$container_attributes['data-hover'] = $hover_effect;
			}
		}

		/**
		 * Filters the HTML attributes of the related posts container.
		 *
		 * @since 2.0.1
		 *
		 * @param array $container_attributes Attributes keyed by name.
		 */
		$container_attributes = apply_filters(
			'blocksy:related-posts:container-attributes',
			$container_attributes
		);

		/**
		 * Filters the HTML attributes of each related post card.
		 *
		 * @since 2.0.1
		 *
		 * @param array $item_attributes Attributes keyed by name. Default empty array.
		 */
		$item_attributes = apply_filters(
			'blocksy:related-posts:item-attributes',
			array()
		);

		$prefix = blocksy_manager()->screen->get_prefix();

		$deep_link_args = array(
			'prefix' => $prefix,
			'suffix' => $prefix . '_has_related_posts',
		);

		?>

		<?php if ( 'separated' === $location ) { ?>
	<div class="<?php echo esc_attr( $class ); ?>" <?php echo blocksy_generic_get_deep_link( $deep_link_args ); ?>>
		<div class="<?php echo $container_class; ?>">
	<?php } ?>

		<div
			class="<?php echo $boxed_container_class; ?>"
			<?php echo 'separated' === $location || empty( $location ) ? '' : blocksy_generic_get_deep_link( $deep_link_args ); ?>
		>
			<?php
			/**
			 * Fires at the top of the related posts module.
			 *
			 * @since 1.8.29
			 */
			do_action( 'blocksy:single:related_posts:top' );
			?>

			<?php if ( ! empty( $label ) ) { ?>
				<?php
				/**
				 * Fires before the related posts module title.
				 *
				 * @since 1.8.29
				 */
				do_action( 'blocksy:single:related_posts:title:before' );
				?>
				<<?php echo $label_tag; ?> class="ct-module-title">
					<?php echo wp_kses_post( $label ); ?>
				</<?php echo $label_tag; ?>>
				<?php
				/**
				 * Fires after the related posts module title.
				 *
				 * @since 1.8.29
				 */
				do_action( 'blocksy:single:related_posts:title:after' );
				?>
			<?php } ?>

			<?php
			/**
			 * Fires before the related posts loop.
			 *
			 * @since 2.0.1
			 */
			do_action( 'blocksy:single:related_posts:before_loop' );
			?>

			<div <?php echo blocksy_attr_to_html( $container_attributes ); ?>>
			<?php while ( $query->have_posts() ) { ?>
				<?php $query->the_post(); ?>

				<?php
					blocksy_render_related_card(
						array(
							'item_attributes' => $item_attributes,
							'meta_elements'   => $meta_elements,
							'posts_title_tag' => $posts_title_tag,
						)
					);
				?>

			<?php } ?>
			</div>

				<?php
				/**
				 * Fires after the related posts loop.
				 *
				 * @since 2.0.1
				 */
				do_action( 'blocksy:single:related_posts:after_loop' );
				?>

				<?php
				/**
				 * Fires at the bottom of the related posts module.
				 *
				 * @since 1.8.29
				 */
				do_action( 'blocksy:single:related_posts:bottom' );
				?>
		</div>

		<?php if ( 'separated' === $location ) { ?>
		</div>
	</div>
	<?php } ?>

		<?php

		wp_reset_postdata();
	}
}

function blocksy_before_current_template() {
	/**
	 * Fires before the current template content is rendered.
	 *
	 * @since 1.7.17
	 */
	do_action( 'blocksy:template:before' );
}

function blocksy_after_current_template() {
	/**
	 * Fires after the current template content is rendered.
	 *
	 * @since 1.7.17
	 */
	do_action( 'blocksy:template:after' );
}
