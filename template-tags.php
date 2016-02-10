<?php
function get_sol_post_format() {
	global $post;

	if ( !$post || !$post->ID )
		return false;

	$terms = get_the_terms( $post->ID, 'sol_post_format' );

	if ( $terms )
		foreach ( $terms as $term )
			return $term->slug;

	return false;

}
