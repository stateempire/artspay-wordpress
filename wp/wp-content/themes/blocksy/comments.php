<?php
/**
 * The template for displaying comments
 *
 * This is the template that displays the area of the page that contains both the current comments
 * and the comment form.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package Blocksy
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- template scope, included by comments_template()

if ( post_password_required() ) {
	return;
}

$prefix = blocksy_manager()->screen->get_prefix();

$comments_position = blocksy_get_theme_mod( $prefix . '_comments_position', 'below' );

/**
 * Fires before the comments area is rendered.
 *
 * @since 1.7.52
 */
do_action( 'blocksy:comments:before' );

$html_atts = array(
	'class' => 'ct-comments',
	'id'    => 'comments',
);

if ( blocksy_get_theme_mod(
	$prefix . '_comments_containment',
	'separated'
) === 'contained'
) {
	$html_atts['class'] .= ' is-width-constrained';
}

$deep_link_args = array(
	'prefix' => $prefix,
	'suffix' => $prefix . '_has_comments',
	'return' => 'array',
);

$html_atts = array_merge(
	$html_atts,
	blocksy_generic_get_deep_link(
		$deep_link_args
	)
);

?>


<div <?php echo blocksy_attr_to_html( $html_atts ); ?>>
	<?php
	/**
	 * Fires at the top of the comments area.
	 *
	 * @since 1.7.52
	 */
	do_action( 'blocksy:comments:top' );
	?>

	<?php
	if ( have_comments() ) :
		$label_tag = blocksy_validate_html_tag(
			array(
				'tag'     => blocksy_get_theme_mod( $prefix . '_comments_heading_tag' ),
				'allowed' => 'headings',
				'default' => 'h3',
			)
		);
		?>
		<<?php echo esc_html( $label_tag ); ?> class="ct-comments-title">
			<?php comments_number( esc_html__( 'No comments yet', 'blocksy' ), __( 'One comment', 'blocksy' ), __( '% Comments', 'blocksy' ) ); ?>
		</<?php echo esc_html( $label_tag ); ?>>
	<?php endif; // have_comments() ?>

	<?php
	if ( 'above' === $comments_position ) {
		comment_form();
	}
	?>

	<?php if ( have_comments() ) : ?>
		<ol class="ct-comment-list">
			<?php
				wp_list_comments(
					array(
						'short_ping'   => true,
						'avatar_size'  => 100,
						'callback'     => 'blocksy_custom_comment_template',
						'end-callback' => function () {
							echo '</li>';
						},
					)
				);
			?>
		</ol>

		<?php
		// Are there comments to navigate through?
		if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) :
			?>
			<nav class="ct-comment-navigation-container">
				<h4 class="screen-reader-text section-heading">
				<?php esc_html_e( 'Comment navigation', 'blocksy' ); ?>
				</h4>

				<div class="ct-comments-navigation">
					<span class="prev">
					<?php previous_comments_link( __( '&larr; Older Comments', 'blocksy' ) ); ?>
					</span>

					<span class="next">
					<?php next_comments_link( __( 'Newer Comments &rarr;', 'blocksy' ) ); ?>
					</span>
				</div>
			</nav>
		<?php endif; // Check for comment navigation ?>

		<?php if ( ! comments_open() && get_comments_number() ) : ?>
			<p class="no-comments">
				<?php esc_html_e( 'Comments are closed.', 'blocksy' ); ?>
			</p>
		<?php endif; ?>

	<?php endif; // have_comments() ?>

	<?php
	if ( 'below' === $comments_position ) {
		comment_form();
	}
	?>

	<?php
	/**
	 * Fires at the bottom of the comments area.
	 *
	 * @since 1.7.52
	 */
	do_action( 'blocksy:comments:bottom' );
	?>

</div>

<?php
/**
 * Fires after the comments area is rendered.
 *
 * @since 1.7.52
 */
do_action( 'blocksy:comments:after' );
?>
