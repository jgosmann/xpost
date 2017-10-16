<?php
/*
 * Crossposting and broadcasting of comments
 */

/*  Copyright 2009-2010 Jan Gosmann  (email: jan@hyper-world.de)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/
include 'wordpress/wp-load.php';

require_once( 'xpost_config.php' );
require_once( ABSPATH . WPINC . '/class-IXR.php' );
require_once ( ABSPATH . WPINC . '/class-wp-http-ixr-client.php');

add_action( 'wp_set_comment_status', 'crosspost_comment', 10, 2 );
add_action( 'comment_post', 'crosspost_fresh_comment', 10, 2 );

/**
 * Wrapper for crossposting a fresh comment which needs no approval.
 */
function crosspost_fresh_comment( $id, $approved ) {
	if( $approved == '1' ) {
		crosspost_comment( $id, 'approve' );
	}
}

/**
 * Crossposts and broadcasts an approved comment.
 * @param $id commend ID
 * @param $approved Should be "approved" when the comment was approved.
 *     Otherwise one of "delete", "spam" or "hold".
 */
function crosspost_comment( $id, $approved ) {
	global $wpdb;

	if( $approved != 'approve' ) {
		return;
	}

	$comment = get_comment( $id, OBJECT );
	$commentData = array();
	$commentData['comment_parent'] = 0;
	$commentData['content'] = $comment->comment_content;
	$commentData['author'] = $comment->comment_author;
	$commentData['author_url'] = $comment->comment_author_url;
	$commentData['author_email'] = $comment->comment_author_email;
	
	$broadcastUrl = get_post_meta( $comment->comment_post_ID, '_xpost_commet_broadcast_url', true );
	if( !empty( $broadcastUrl ) ) {
		$commentToken = get_post_meta( $comment->comment_post_ID, '_xpost_comment_token', true );
		$excludeId = get_post_meta( $comment->comment_post_ID, '_xpost_comment_exclude_id', true );
		$originalPost = get_post_meta( $comment->comment_post_ID, '_xpost_original_postid', true );
		$client = new WP_HTTP_IXR_CLIENT( $broadcastUrl );
		$client->query( 'xpost.broadcastComment', $commentToken, $originalPost, $excludeId, $commentData );
	}
	
	$sql = "SELECT id, remote_postid FROM ".XPOST_POSTS_TABLE_NAME." WHERE local_postid = $comment->comment_post_ID";
	$blogs = $wpdb->get_results( $sql );
	
	foreach( $blogs as $blog ) {
		if( get_post_meta( $comment->comment_post_ID, '_xpost_blog'.$blog->id, true ) != '1' ) {
			continue;
		} 
		if( get_post_meta( $comment->comment_post_ID, '_xpost_blog'.$blog->id.'_xpostComments', true ) != '1' ) {
			continue;
		}

		$sql = "SELECT blogid, xmlrpc, user, password FROM ".XPOST_TABLE_NAME." WHERE id = $blog->id";
		$blogUserData = $wpdb->get_row( $sql );
		
		$client = new WP_HTTP_IXR_CLIENT( $blogUserData->xmlrpc );
		$client->query( 'xpost.newComment', $blogUserData->blogid, $blogUserData->user, $blogUserData->password, $blog->remote_postid, $commentData );
	}
}

?>
