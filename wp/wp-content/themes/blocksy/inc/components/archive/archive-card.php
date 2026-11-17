<?php

function blocksy_get_posts_listing_card_custom_output( $prefix ) {
	/**
	 * Filters the custom output that replaces a posts listing card.
	 *
	 * Returning an array with `output` and `has_default_layout` keys renders
	 * it in place of the default card layers.
	 *
	 * @since 1.8.24
	 *
	 * @param array|null $card_render Custom card render. Default null.
	 * @param string     $prefix      Current screen prefix.
	 */
	return apply_filters(
		'blocksy:posts-listing:cards:custom-output',
		null,
		$prefix
	);
}

if ( ! function_exists( 'blocksy_render_archive_card' ) ) {
	function blocksy_render_archive_card( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'prefix' => blocksy_manager()->screen->get_prefix(
					array(
						'allowed_prefixes' => array( 'blog' ),
						'default_prefix'   => 'blog',
					)
				),
			)
		);

		$data_reveal_output = '';

		if ( blocksy_get_theme_mod(
			blocksy_manager()->screen->process_allowed_prefixes(
				$args['prefix'],
				array(
					'allowed_prefixes' => array( 'blog', 'search' ),
					'default_prefix'   => 'blog',
				)
			) . '_has_posts_reveal',
			'no'
		) === 'yes' ) {
			$data_reveal_output = 'data-reveal="bottom:no"';
		}

		$card_render = blocksy_get_posts_listing_card_custom_output( $args['prefix'] );

		if ( $card_render ) {
			$entry_open = '<article';
			// $entry_open .= ' id="post-' . get_the_ID() . '"';
			$entry_open .= ' class="' . esc_attr( implode( ' ', get_post_class( $card_render['has_default_layout'] ? 'entry-card' : '' ) ) ) . '"';
			$entry_open .= ' ' . wp_kses_post( $data_reveal_output );
			$entry_open .= '>';

			echo $entry_open;
			echo $card_render['output'];
			echo '</article>';

			return;
		}

		$blog_post_structure = blocksy_listing_page_structure(
			array(
				'prefix' => $args['prefix'],
			)
		);

		/**
		 * Filters the default archive card layers, used when no order is saved.
		 *
		 * @since 1.7.60
		 *
		 * @param array  $default_archive_order Default archive card layers.
		 * @param string $prefix                Current screen prefix.
		 */
		$default_archive_order = apply_filters(
			'blocksy:posts-listing:archive-order:default',
			array(
				array(
					'id'            => 'post_meta',
					'enabled'       => true,
					'meta_elements' => blocksy_post_meta_defaults(
						array(
							array(
								'id'      => 'categories',
								'enabled' => true,
							),
						)
					),
					'__id'          => 'meta_1',
				),

				array(
					'id'      => 'title',
					'enabled' => true,
				),

				array(
					'id'      => 'featured_image',
					'enabled' => true,
				),

				array(
					'id'      => 'excerpt',
					'enabled' => true,
				),

				array(
					'id'      => 'read_more',
					'enabled' => false,
				),

				array(
					'id'            => 'post_meta',
					'enabled'       => true,
					'meta_elements' => blocksy_post_meta_defaults(
						array(
							array(
								'id'      => 'author',
								'enabled' => true,
							),

							array(
								'id'      => 'post_date',
								'enabled' => true,
							),

							array(
								'id'      => 'comments',
								'enabled' => true,
							),
						)
					),
					'__id'          => 'meta_2',
				),

				array(
					'id'      => 'divider',
					'enabled' => false,
				),
			),
			$args['prefix']
		);

		/**
		 * Filters the archive card layers, after the saved order is resolved.
		 *
		 * @since 1.8.43
		 *
		 * @param array $archive_order Archive card layers.
		 */
		$archive_order = apply_filters(
			'blocksy:posts-listing:archive-order',
			blocksy_get_theme_mod(
				$args['prefix'] . '_archive_order',
				$default_archive_order
			)
		);

		$featured_image_settings = null;
		$excerpt_settings        = null;
		$title_settings          = null;
		$read_more_settings      = null;

		$last_enabled_component   = null;
		$enabled_components_count = 0;

		if ( ! $archive_order ) {
			$archive_order = array();
		}

		foreach ( array_reverse( $archive_order ) as $index => $value ) {
			if ( 'featured_image' === $value['id'] ) {
				$featured_image_settings = $value;
			}

			if ( 'read_more' === $value['id'] ) {
				$read_more_settings = $value;
			}

			if ( 'excerpt' === $value['id'] ) {
				$excerpt_settings = $value;
			}

			if ( 'title' === $value['id'] ) {
				$title_settings = $value;
			}
		}

		if ( 'simple' === $blog_post_structure ) {
			foreach ( $archive_order as $index => $value ) {
				if ( 'featured_image' === $value['id'] ) {
					unset( $archive_order[ $index ] );
				}
			}

			array_unshift( $archive_order, $featured_image_settings );
		}

		foreach ( array_reverse( $archive_order ) as $index => $value ) {
			if ( $value['enabled'] ) {
				if ( ! $last_enabled_component ) {
					if ( ! isset( $value['__id'] ) ) {
						$id = blocksy_rand_md5();

						$archive_order[ count( $archive_order ) - 1 - $index ]['__id'] = $id;
						$value['__id'] = $id;
					}

					$last_enabled_component = $value['id'] . $value['__id'];
				}

				++$enabled_components_count;
			}
		}

		$is_boundles         = blocksy_default_akg( 'is_boundless', $featured_image_settings, 'yes' );
		$image_hover_effect  = blocksy_default_akg( 'image_hover_effect', $featured_image_settings, 'none' );
		$featured_image_size = blocksy_default_akg( 'image_size', $featured_image_settings, 'medium_large' );

		$has_link = blocksy_default_akg( 'has_link', $featured_image_settings, 'yes' ) === 'yes';

		/**
		 * Filters the attachment ID used for the featured image layer of an archive card.
		 *
		 * @since 1.8.7
		 *
		 * @param int $attachment_id Attachment ID. Default the post thumbnail ID.
		 */
		$featured_image_attachment_id = apply_filters(
			'blocksy:archive:render-card-layers:featured_image:attachment_id',
			get_post_thumbnail_id()
		);

		$featured_image_args = array(
			'attachment_id' => $featured_image_attachment_id,
			'post_id'       => get_the_ID(),
			'ratio'         => blocksy_default_akg( 'thumb_ratio', $featured_image_settings, '4/3' ),
			'tag_name'      => $has_link ? 'a' : 'figure',
			'size'          => $featured_image_size,
			'html_atts'     => $has_link ? array(
				'href'       => esc_url( get_permalink() ),
				'aria-label' => wp_strip_all_tags( get_the_title() ),
			) : array(),
			'display_video' => blocksy_default_akg( 'has_archive_video_thumbnail', $featured_image_settings, 'no' ) === 'yes',
		);

		$card_type = blocksy_get_theme_mod( $args['prefix'] . '_card_type', 'boxed' );

		if (
			'simple' === $blog_post_structure
			&&
			'cover' === $card_type
		) {
			$card_type = 'boxed';
		}

		if ( 'cover' === $card_type ) {
			$featured_image_args['ratio'] = 'original';
		}

		$featured_image_args['class'] = '';

		if (
			'yes' === $is_boundles
			&&
			'boxed' === $card_type
			&&
			'gutenberg' !== $blog_post_structure
		) {
			$featured_image_args['class'] .= ' boundless-image';
		}

		if ( 'none' !== $image_hover_effect ) {
			$featured_image_args['class'] .= ' has-hover-effect';
		}

		$featured_image_args['class'] = trim( $featured_image_args['class'] );

		$read_more_text = blocksy_sanitize_html_for_display(
			array(
				'html' => blocksy_translate_dynamic(
					blocksy_default_akg(
						'read_more_text',
						$read_more_settings,
						__( 'Read More', 'blocksy' )
					),
					$args['prefix'] . '_archive_read_more_text'
				),
			)
		);

		$read_more_arrow = '<svg width="17px" height="17px" viewBox="0 0 32 32" fill="currentColor"><path d="M 21.1875 9.28125 L 19.78125 10.71875 L 24.0625 15 L 4 15 L 4 17 L 24.0625 17 L 19.78125 21.28125 L 21.1875 22.71875 L 27.90625 16 Z "></path></svg>';

		if ( blocksy_default_akg( 'read_more_arrow', $read_more_settings, 'no' ) === 'yes' ) {
			$read_more_text .= $read_more_arrow;
		}

		$button_class = 'entry-button';

		$button_type = blocksy_default_akg(
			'button_type',
			$read_more_settings,
			'background'
		);

		if ( 'background' === $button_type ) {
			$button_class .= ' wp-element-button ct-button';
		}

		if ( 'outline' === $button_type ) {
			$button_class .= ' wp-element-button ct-button-ghost';
		}

		$outputs = null;

		$data_reveal_output = '';

		if ( blocksy_get_theme_mod(
			blocksy_manager()->screen->process_allowed_prefixes(
				$args['prefix'],
				array(
					'allowed_prefixes' => array( 'blog', 'search' ),
					'default_prefix'   => 'blog',
				)
			) . '_has_posts_reveal',
			'no'
		) === 'yes' ) {
			$data_reveal_output = 'data-reveal="bottom:no"';
		}

		$card_content_classes = get_post_class( 'entry-card' );

		if ( 'simple' !== $blog_post_structure ) {
			$card_content_classes = get_post_class( 'entry-card card-content' );
		}

		$entry_open = '<article';
		// $entry_open .= ' id="post-' . get_the_ID() . '"';
		$entry_open .= ' class="' . esc_attr( implode( ' ', $card_content_classes ) ) . '"';
		$entry_open .= ' ' . wp_kses_post( $data_reveal_output );
		$entry_open .= '>';

		/**
		 * Fires before an archive card is opened.
		 *
		 * @since 2.1.5
		 */
		do_action( 'blocksy:loop:card:before' );

		echo $entry_open;

		/**
		 * Fires at the start of an archive card, before its layers.
		 *
		 * @since 1.8.0
		 */
		do_action( 'blocksy:loop:card:start' );

		$had_a_meta = false;

		foreach ( $archive_order as $single_component ) {
			if ( ! $single_component['enabled'] ) {
				if (
					'simple' === $blog_post_structure
					&&
					'featured_image' === $single_component['id']
				) {
					echo '<div class="card-content">';
				}

				continue;
			}

			$post_meta_default = null;

			if ( 'post_meta' === $single_component['id'] ) {
				$total_metas = array();

				foreach ( $archive_order as $nested_single_component ) {
					if ( 'post_meta' === $nested_single_component['id'] ) {
						$total_metas[] = $nested_single_component;
					}
				}

				if ( count( $total_metas ) > 1 && ! $had_a_meta ) {
					$post_meta_default = blocksy_post_meta_defaults(
						array(
							array(
								'id'      => 'categories',
								'enabled' => true,
							),
						)
					);
				} else {
					$post_meta_default = blocksy_post_meta_defaults(
						array(
							array(
								'id'      => 'author',
								'enabled' => true,
							),

							array(
								'id'      => 'post_date',
								'enabled' => true,
							),

							array(
								'id'      => 'comments',
								'enabled' => true,
							),
						)
					);
				}

				$had_a_meta = true;
			}

			$output = '';

			if ( 'post_meta' === $single_component['id'] ) {
				$has_term_accent_color = 'yes';

				foreach ( blocksy_akg( 'meta_elements', $single_component, $post_meta_default ) as $meta_element ) {
					if ( 'categories' === $meta_element['id'] ) {
						$has_term_accent_color = blocksy_akg( 'has_term_accent_color', $meta_element, 'yes' );
					}
				}

				$id = substr( isset( $single_component['__id'] ) ? $single_component['__id'] : 'default', 0, 6 );

				$output = blocksy_post_meta(
					blocksy_akg(
						'meta_elements',
						$single_component,
						$post_meta_default
					),
					array(
						'meta_type'      => blocksy_akg( 'meta_type', $single_component, 'simple' ),
						'meta_divider'   => blocksy_akg( 'meta_divider', $single_component, 'slash' ),
						'has_term_class' => 'yes' === $has_term_accent_color,
						'attr'           => array(
							'data-id' => $id,
						),
					)
				);
			}

			if ( 'divider' === $single_component['id'] ) {
				$id = substr( isset( $single_component['__id'] ) ? $single_component['__id'] : 'default', 0, 6 );

				$output = blocksy_html_tag(
					'div',
					array(
						'class'   => 'entry-divider',
						'data-id' => $id,
					),
					''
				);
			}

			if ( 'read_more' === $single_component['id'] ) {
				$output = blocksy_html_tag(
					'a',
					array(
						'class' => $button_class,
						// 'data-type' => $button_type,
						'href'  => esc_url( get_permalink() ),
					),
					$read_more_text .

					blocksy_html_tag(
						'span',
						array(
							'class' => 'screen-reader-text',
						),
						get_the_title()
					)
				);

				if ( 'gutenberg' === $blog_post_structure ) {
					$output = blocksy_html_tag(
						'div',
						array(
							'class' => 'entry-button-container',
						),
						$output
					);
				}
			}

			if ( ! $outputs ) {
				$featured_image_output = '';

				if (
					get_the_post_thumbnail( $featured_image_args['attachment_id'] )
					||
					wp_get_attachment_image_url( $featured_image_args['attachment_id'] )
				) {
					$featured_image_output = blocksy_media( $featured_image_args );
				}

				$title_tag = blocksy_validate_html_tag(
					array(
						'tag'     => blocksy_akg( 'heading_tag', $title_settings ),
						'allowed' => 'headings',
						'default' => 'h2',
					)
				);

				/**
				 * Filters the rendered output of the archive card layers, keyed by layer ID.
				 *
				 * @since 1.7.60
				 *
				 * @param array  $outputs             Rendered layers: `title`, `featured_image`, `excerpt`.
				 * @param string $prefix              Current screen prefix.
				 * @param array  $featured_image_args Arguments used to render the featured image.
				 */
				$outputs = apply_filters(
					'blocksy:archive:render-card-layers',
					array(
						'title'          => blocksy_entry_title(
							$title_tag,
							blocksy_default_akg( 'has_link', $title_settings, 'yes' ) === 'yes'
						),

						'featured_image' => apply_filters(
							'post_thumbnail_html', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook
							$featured_image_output,
							get_the_ID(),
							$featured_image_args['attachment_id'],
							$featured_image_args['size'],
							''
						),

						'excerpt'        => blocksy_entry_excerpt(
							array(
								'length' => intval(
									blocksy_default_akg( 'excerpt_length', $excerpt_settings, '40' )
								),
								'source' => blocksy_default_akg(
									'excerpt_source',
									$excerpt_settings,
									'excerpt'
								),
							)
						),
					),
					$args['prefix'],
					$featured_image_args
				);
			}

			if ( isset( $outputs[ $single_component['id'] ] ) ) {
				$output = $outputs[ $single_component['id'] ];
			}

			/**
			 * Filters the rendered output of a single archive card layer.
			 *
			 * @since 1.8.4
			 *
			 * @param string $output           Rendered layer output.
			 * @param array  $single_component Layer settings.
			 */
			$output = apply_filters(
				'blocksy:archive:render-card-layer',
				$output,
				$single_component
			);

			if ( ! isset( $single_component['__id'] ) ) {
				$single_component['__id'] = '';
			}

			// if (
			//  $enabled_components_count > 1
			//  &&
			//  $card_type !== 'cover'
			//  &&
			//  $last_enabled_component === $single_component['id'] . $single_component['__id'] && (
			//      strpos($last_enabled_component, 'post_meta') !== false
			//      ||
			//      strpos($last_enabled_component, 'featured_image') !== false
			//  )
			// ) {
			//  echo '<div class="ct-ghost"></div>';
			// }

			/**
			 * Note to code reviewers: This line doesn't need to be escaped.
			 * Variabile $output used here escapes the value properly.
			 */
			echo $output;

			if (
				'simple' === $blog_post_structure
				&&
				'featured_image' === $single_component['id']
			) {
				echo '<div class="card-content">';
			}

			if (
				'simple' === $blog_post_structure
				&&
				$last_enabled_component === $single_component['id'] . $single_component['__id']
			) {
				echo '</div>';
			}
		}

		/**
		 * Fires at the end of an archive card, after its layers.
		 *
		 * @since 1.8.0
		 */
		do_action( 'blocksy:loop:card:end' );

		echo '</article>';

		/**
		 * Fires after an archive card is closed.
		 *
		 * @since 2.1.5
		 */
		do_action( 'blocksy:loop:card:after' );
	}
}
