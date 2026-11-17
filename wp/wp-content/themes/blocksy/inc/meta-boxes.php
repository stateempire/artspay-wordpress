<?php
/**
 * Implement meta boxes
 *
 * @copyright 2019-present Creative Themes
 * @license   http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @package   Blocksy
 */

function blocksy_get_post_options( $post_id = null, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'meta_id' => 'blocksy_post_meta_options',
		)
	);

	static $post_opts = array();

	if ( ! $post_id ) {
		global $post;

		if ( $post && is_singular() ) {
			$post_id = $post->ID;
		}

		$maybe_page = blocksy_is_page();

		if ( $maybe_page ) {
			$post_id = $maybe_page;
		}

		if ( ! $post_id ) {
			$object = get_queried_object();

			if ( isset( $object->ID ) ) {
				$post_id = $object->ID;
			}
		}
	}

	if ( is_array( $post_id ) ) {
		blocksy_debug_log(
			'blocksy_get_post_options() post_id is array. This is not valid.',
			array(
				'post_id' => $post_id,
				'is_page' => blocksy_is_page(),
				'args'    => $args,
			)
		);

		return array();
	}

	$cache_key = $post_id . ':' . $args['meta_id'];

	if ( isset( $post_opts[ $cache_key ] ) ) {
		return $post_opts[ $cache_key ];
	}

	$values = get_post_meta( $post_id, $args['meta_id'] );

	if ( empty( $values ) ) {
		$values = array( array() );
	}

	if ( ! is_array( $values[0] ) || ! $values[0] ) {
		return array();
	}

	$final_values = $values[0];

	/**
	 * Filters the post options keys kept for the posts page and the shop page.
	 *
	 * Keys correspond to the options in inc/options/meta/blog.php.
	 *
	 * @since 2.0.31
	 *
	 * @param string[] $keys Allowed option keys.
	 */
	$allowed_keys_for_special_posts = apply_filters(
		'blocksy:posts:meta:blog-special-keys', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Blocksy hook naming convention
		array(
			'disable_header',
			'disable_footer',
		)
	);

	if (
		intval( get_option( 'page_for_posts' ) ) === intval( $post_id )
		||
		intval( get_option( 'woocommerce_shop_page_id' ) ) === intval( $post_id )
	) {
		foreach ( $values[0] as $key => $value ) {
			if ( ! in_array( $key, $allowed_keys_for_special_posts, true ) ) {
				unset( $final_values[ $key ] );
			}
		}
	}

	/**
	 * Filters the Blocksy options stored for a post.
	 *
	 * @since 2.0.0
	 *
	 * @param array $final_values Post options.
	 * @param int   $post_id      Post ID.
	 */
	$final_values = apply_filters(
		'blocksy:posts:meta:values', // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Blocksy hook naming convention
		$final_values,
		$post_id
	);

	$post_opts[ $cache_key ] = $final_values;

	return $final_values;
}

function blocksy_get_editor_post_id() {
	if ( ! isset( $_GET['post'] ) ) {
		return null;
	}

	$post_id = absint( $_GET['post'] );

	if (
		! $post_id
		||
		! current_user_can( 'edit_post', $post_id )
	) {
		return null;
	}

	return $post_id;
}

function blocksy_get_taxonomy_options( $term_id = null ) {
	static $taxonomy_opts = array();

	if ( ! $term_id ) {
		$term_id = get_queried_object_id();
	}

	if ( isset( $taxonomy_opts[ $term_id ] ) ) {
		return $taxonomy_opts[ $term_id ];
	}

	$values = get_term_meta(
		$term_id,
		'blocksy_taxonomy_meta_options'
	);

	if (
		empty( $values )
		||
		! is_array( $values[0] )
		||
		! $values[0]
	) {
		$values = array( array() );
	}

	$taxonomy_opts[ $term_id ] = $values[0];

	return $values[0];
}
