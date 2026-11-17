<?php

function blocksy_related_posts_card_top() {
	/**
	 * Fires at the top of a related posts card, before its content.
	 *
	 * @since 1.8.29
	 */
	do_action( 'blocksy:single:related_posts:card:top' );
}

function blocksy_related_posts_card_bottom() {
	/**
	 * Fires at the bottom of a related posts card, after its content.
	 *
	 * @since 1.8.29
	 */
	do_action( 'blocksy:single:related_posts:card:bottom' );
}

if ( ! function_exists( 'blocksy_render_related_card' ) ) {
	function blocksy_render_related_card( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'prefix' => blocksy_manager()->screen->get_prefix(
					array(
					// 'allowed_prefixes' => ['blog'],
					// 'default_prefix' => 'blog'
					)
				),
			)
		);

		$card_render = blocksy_get_posts_listing_card_custom_output( $args['prefix'] );

		$related_item_attr = array(
			'id'    => 'post-' . get_the_ID(),
			'class' => implode( ' ', get_post_class() ),
		);

		$entry_open  = '<article ';
		$entry_open .= blocksy_attr_to_html( $args['item_attributes'] ) . ' ';
		$entry_open .= blocksy_schema_org_definitions( 'creative_work:related_posts' );
		$entry_open .= '>';

		$entry_open .= '<div ' . blocksy_attr_to_html( $related_item_attr ) . '>';

		$entry_close = '</div></article>';

		if ( $card_render ) {
			echo $entry_open;
			blocksy_related_posts_card_top();
			echo $card_render['output'];
			blocksy_related_posts_card_bottom();
			echo $entry_close;

			return;
		}

		/**
		 * Filters the default related posts card layers, used when no order is saved.
		 *
		 * @since 2.0.76
		 *
		 * @param array  $default_related_order Default related posts card layers.
		 * @param string $prefix                Current screen prefix.
		 */
		$default_related_order = apply_filters(
			'blocksy:posts-listing:related-order:default',
			array(
				array(
					'id'      => 'featured_image',
					'enabled' => true,
				),

				array(
					'id'      => 'title',
					'enabled' => true,
				),

				array(
					'id'            => 'post_meta',
					'enabled'       => true,
					'meta_elements' => blocksy_post_meta_defaults(
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
					),
					'__id'          => 'meta_1',
				),
			),
			$args['prefix']
		);

		/**
		 * Filters the related posts card layers, after the saved order is resolved.
		 *
		 * @since 2.0.76
		 *
		 * @param array $related_order Related posts card layers.
		 */
		$related_order = apply_filters(
			'blocksy:posts-listing:related-order',
			blocksy_get_theme_mod(
				$args['prefix'] . '_related_order',
				$default_related_order
			)
		);

		$featured_image_settings = null;
		$title_settings          = null;

		$last_enabled_component   = null;
		$enabled_components_count = 0;

		if ( ! $related_order ) {
			$related_order = array();
		}

		foreach ( array_reverse( $related_order ) as $index => $value ) {
			if ( 'featured_image' === $value['id'] ) {
				$featured_image_settings = $value;
			}

			if ( 'title' === $value['id'] ) {
				$title_settings = $value;
			}
		}

		foreach ( array_reverse( $related_order ) as $index => $value ) {
			if ( $value['enabled'] ) {
				if ( ! $last_enabled_component ) {
					if ( ! isset( $value['__id'] ) ) {
						$id = blocksy_rand_md5();

						$related_order[ count( $related_order ) - 1 - $index ]['__id'] = $id;
						$value['__id'] = $id;
					}

					$last_enabled_component = $value['id'] . $value['__id'];
				}

				++$enabled_components_count;
			}
		}

		$image_hover_effect      = blocksy_default_akg( 'image_hover_effect', $featured_image_settings, 'none' );
		$featured_image_size     = blocksy_default_akg( 'image_size', $featured_image_settings, 'medium_large' );
		$featured_image_has_link = blocksy_default_akg( 'has_link', $featured_image_settings, 'yes' ) === 'yes';

		/**
		 * Filters the attachment ID used for the featured image layer of a related posts card.
		 *
		 * @since 2.0.76
		 *
		 * @param int $attachment_id Attachment ID. Default the post thumbnail ID.
		 */
		$featured_image_attachment_id = apply_filters(
			'blocksy:related:render-card-layers:featured_image:attachment_id',
			get_post_thumbnail_id()
		);

		$featured_image_args = array(
			'class'         => trim( 'none' !== $image_hover_effect ? 'has-hover-effect' : '' ),
			'attachment_id' => $featured_image_attachment_id,
			'post_id'       => get_the_ID(),
			'ratio'         => blocksy_default_akg( 'thumb_ratio', $featured_image_settings, '16/9' ),
			'tag_name'      => $featured_image_has_link ? 'a' : 'figure',
			'size'          => $featured_image_size,
			'html_atts'     => $featured_image_has_link ? array(
				'href'       => esc_url( get_permalink() ),
				'aria-label' => wp_strip_all_tags( get_the_title() ),
			) : array(),
			'display_video' => blocksy_default_akg( 'has_related_video_thumbnail', $featured_image_settings, 'no' ) === 'yes',
		);

		$outputs = null;

		echo $entry_open;

		blocksy_related_posts_card_top();

		foreach ( $related_order as $single_component ) {
			if ( ! $single_component['enabled'] ) {
				continue;
			}

			$output = '';

			if ( 'post_meta' === $single_component['id'] ) {
				$post_meta_default = blocksy_post_meta_defaults(
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
				);

				$total_metas = array();

				foreach ( $related_order as $nested_single_component ) {
					if ( 'post_meta' === $nested_single_component['id'] ) {
						$total_metas[] = $nested_single_component;
					}
				}

				$has_term_accent_color = 'yes';

				foreach ( blocksy_akg( 'meta_elements', $single_component, $post_meta_default ) as $meta_element ) {
					if ( 'categories' === $meta_element['id'] ) {
						$has_term_accent_color = blocksy_akg( 'has_term_accent_color', $meta_element, 'yes' );
					}
				}

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
							'data-id' => substr( $single_component['__id'], 0, 6 ),
						),
					)
				);
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
						'allowed' => 'headings_text',
						'default' => 'h4',
					)
				);

				/**
				 * Filters the rendered output of the related posts card layers, keyed by layer ID.
				 *
				 * @since 2.0.76
				 *
				 * @param array  $outputs             Rendered layers: `title`, `featured_image`.
				 * @param string $prefix              Current screen prefix.
				 * @param array  $featured_image_args Arguments used to render the featured image.
				 */
				$outputs = apply_filters(
					'blocksy:related:render-card-layers',
					array(
						'title'          => blocksy_entry_title(
							$title_tag,
							blocksy_default_akg( 'has_link', $title_settings, 'yes' ) === 'yes',
							array( 'related-entry-title' )
						),

						'featured_image' => apply_filters(
							'post_thumbnail_html', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- core hook
							$featured_image_output,
							get_the_ID(),
							$featured_image_args['attachment_id'],
							$featured_image_args['size'],
							''
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
			 * Filters the rendered output of a single related posts card layer.
			 *
			 * @since 2.0.76
			 *
			 * @param string $output           Rendered layer output.
			 * @param array  $single_component Layer settings.
			 */
			$output = apply_filters(
				'blocksy:related:render-card-layer',
				$output,
				$single_component
			);

			if ( ! isset( $single_component['__id'] ) ) {
				$single_component['__id'] = '';
			}

			/**
			 * Note to code reviewers: This line doesn't need to be escaped.
			 * Variabile $output used here escapes the value properly.
			 */
			echo $output;
		}

		blocksy_related_posts_card_bottom();

		echo $entry_close;
	}
}
