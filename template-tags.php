<?php
function get_sol_post_format( $post_id=0 ) {
	global $post;

	if ( !$post_id && ( !$post || !$post->ID ) )
		return false;

	$post_id = $post_id ? $post_id : $post->ID;

	$terms = get_the_terms( $post_id, 'sol_post_format' );

	if ( $terms )
		foreach ( $terms as $term )
			return $term->slug;

	return false;
}
