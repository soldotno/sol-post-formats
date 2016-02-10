<?php
function get_sol_post_format() {
	global $post;

	if ( !$post || !$post->ID )
		return false;

	foreach ( get_the_terms( $post->ID, 'sol_post_format' ) as $term )
		return $term->slug;

	return false;

}
