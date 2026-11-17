<?php

function blocksy_get_taxonomies_for_cpt( $post_type, $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'return_empty' => false,
		)
	);

	if ( 'product' === $post_type ) {
		return array(
			'product_cat' => __( 'Category', 'blocksy' ),
			'product_tag' => __( 'Tag', 'blocksy' ),
		);
	}

	$result = array();

	$taxonomies = array_values(
		array_diff(
			get_object_taxonomies( $post_type ),
			array( 'post_format' )
		)
	);

	if ( count( $taxonomies ) === 0 ) {
		if ( $args['return_empty'] ) {
			return array();
		}

		return array(
			'default' => __( 'Default', 'blocksy' ),
		);
	}

	foreach ( $taxonomies as $single_taxonomy ) {
		$taxonomy_object            = get_taxonomy( $single_taxonomy );
		$result[ $single_taxonomy ] = $taxonomy_object->label;
	}

	return $result;
}

function blocksy_is_post_viewable( $args = array() ) {
	$args = wp_parse_args(
		$args,
		array(
			'post'      => null,
			'post_type' => null,
		)
	);

	$post = get_post( $args['post'] );

	if ( ! $post ) {
		return false;
	}

	if (
		$args['post_type']
		&&
		! in_array( $post->post_type, (array) $args['post_type'], true )
	) {
		return false;
	}

	if (
		'product_variation' === $post->post_type
		&&
		! blocksy_is_post_viewable(
			array(
				'post'      => $post->post_parent,
				'post_type' => 'product',
			)
		)
	) {
		return false;
	}

	if ( post_password_required( $post ) ) {
		return false;
	}

	if ( 'publish' === $post->post_status ) {
		return true;
	}

	return (
		'private' === $post->post_status
		&&
		current_user_can( 'read_post', $post->ID )
	);
}

if ( ! function_exists( 'blocksy_get_screen_prefix' ) ) {
	function blocksy_get_screen_prefix() {
		return blocksy_manager()->screen->get_prefix();
	}
}
