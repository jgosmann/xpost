<?php
/*
 * XML-RPC additions for crossposting of comments.
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

include_once(ABSPATH . WPINC . '/comment.php');
include_once(ABSPATH . WPINC . '/class-IXR.php');

add_filter( 'xmlrpc_methods', 'xpost_add_xmlrpc_methods' );

function xpost_add_xmlrpc_methods( $methods ) {
	$methods['xpost.broadcastComment'] = 'xpost_broadcastComment';
	$methods['xpost.newComment'] = 'xpost_newComment';
	return $methods;
}

function xpost_broadcastComment( $args ) {
	global $xpost_xmlrpc;
	$xpost_xmlrpc->broadcastComment( $args );
}

function xpost_newComment( $args ) {
	global $xpost_xmlrpc;
	$xpost_xmlrpc->newComment( $args );
}

class xpost_xmlrpc {
	/**
	 * Broadcasts a comment via XML-RPC. The first argument has to be the
	 * commentToken which is saved in the meta data of the post comment is posted
	 * to. The second argument is the post id the comment is posted to.
	 * The third argument is the ID of the blog the comment should not be posted to,
	 * because it is broadcasted from this blog. The fourth argument is the
	 * comment data like in wp.newComment.
	 */
	function broadcastComment( $args ) {
		global $wpdb;
	
		$this->escape($args);
		
		$commentToken = (int) $args[0];
		$post = (int) $args[1];
		$excludeId = (int) $args[2];
		$content_struct = $args[3];

		if ( is_numeric($post) )
			$post_id = absint($post);
		else
			$post_id = url_to_postid($post);
	
		if ( ! $post_id )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );
	
		if ( ! get_post($post_id) )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );
		
		if ( $commentToken != get_post_meta( $post_id, '_xpost_comment_token', true ) ) {
			return new IXR_Error( 403, __( 'Invalid comment token.', 'xpost' ) );
		}

		if( get_post_meta( $post_id, '_xpost_blog'.$excludeId, true ) != '1'
				|| get_post_meta( $post_id, '_xpost_blog'.$excludeId.'_xpostComments', true ) != '1' ) {
			return;
		}

		$commentId = $this->addComment( $post_id, $content_struct );
		
		$sql = "SELECT id, remote_postid FROM ".XPOST_POSTS_TABLE_NAME." WHERE local_postid = $post_id AND id <> $excludeId";
		$blogs = $wpdb->get_results( $sql );
		foreach( $blogs as $blog ) {
			if( get_post_meta( $post_id, '_xpost_blog'.$blog->id, true ) == '1'
					&& get_post_meta( $post_id, '_xpost_blog'.$blog->id.'_xpostComments', true ) == '1' ) {
				$sql = "SELECT blogid, xmlrpc, user, password FROM ".XPOST_TABLE_NAME." WHERE id = $blog->id";
				$blogUserData = $wpdb->get_row( $sql );
			
				$client = new IXR_Client( $blogUserData->xmlrpc );
				$client->query( 'xpost.newComment', $blogUserData->blogid, $blogUserData->user, $blogUserData->password, $blog->remote_postid, $commentData );
			}
		}
	}
	
	/**
	 * Adds a new comment. It is mostly the same as wp.newComment and also heavily
	 * based on the original code of that function, but this function
	 * does use the transmitted author data instead of the login data and does not
	 * allow anonymous comments (even with plugin). Also the comment will be
	 * posted as an approved comment.
	 */
	function newComment( $args ) {	
		$this->escape($args);
	
		$blog_id	= (int) $args[0];
		$username	= $args[1];
		$password	= $args[2];
		$post		= $args[3];
		$content_struct = $args[4];
	
		$user = $this->login($username, $password);
	
		if ( !$user ) {
			return $this->error;
		} else {
			$logged_in = true;
		}
	
		if ( is_numeric($post) )
			$post_id = absint($post);
		else
			$post_id = url_to_postid($post);
	
		if ( ! $post_id )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );
	
		if ( ! get_post($post_id) )
			return new IXR_Error( 404, __( 'Invalid post ID.' ) );
			
		return $this->addComment( $post_id, $content_struct );
	}
	
	/**
	 * Adds a new comment and uses the author data from $content_struct. Does
	 * not check whether this comment is posted by a user logged in and sets
	 * the comment status directly to approved.
	 * 
	 * Code of this function is heavily based on the wp.newComment code of
	 * the Wordpress xmlrpc.php file.
	 * @param $post_id Id of post the comment is posted to.
	 * @param $content_struct comment data.
	 * @return The comment id.
	 */
	function addComment( $post_id, $content_struct ) {
		$comment['comment_post_ID'] = $post_id;
	
		$comment['comment_author'] = '';
		if ( isset($content_struct['author']) )
			$comment['comment_author'] = $content_struct['author'];
	
		$comment['comment_author_email'] = '';
		if ( isset($content_struct['author_email']) )
			$comment['comment_author_email'] = $content_struct['author_email'];
	
		$comment['comment_author_url'] = '';
		if ( isset($content_struct['author_url']) )
			$comment['comment_author_url'] = $content_struct['author_url'];
	
		$comment['user_ID'] = 0;
	
		if ( get_option('require_name_email') ) {
			if ( 6 > strlen($comment['comment_author_email']) || '' == $comment['comment_author'] )
				return new IXR_Error( 403, __( 'Comment author name and email are required' ) );
			elseif ( !is_email($comment['comment_author_email']) )
				return new IXR_Error( 403, __( 'A valid email address is required' ) );
		}
	
		$comment['comment_parent'] = isset($content_struct['comment_parent']) ? absint($content_struct['comment_parent']) : 0;
	
		$comment['comment_content'] = $content_struct['content'];
	
		do_action('xmlrpc_call', 'wp.newComment');
	
		$commentId = wp_new_comment($comment);
		wp_set_comment_status( $commentId, 'approve' );
		return $commentId;
	}
	
	/* The following functions have been taken slightly modified from the orginal
	 * wordpress xmlrpc.php.
	 */ 
	
	/**
	 * Sanitize string or array of strings for database.
	 *
	 * @since 1.5.2
	 *
	 * @param string|array $array Sanitize single string or array of strings.
	 * @return string|array Type matches $array and sanitized for the database.
	 */
	function escape(&$array) {
		global $wpdb;
	
		if(!is_array($array)) {
			return($wpdb->escape($array));
		}
		else {
			foreach ( (array) $array as $k => $v ) {
				if (is_array($v)) {
					$this->escape($array[$k]);
				} else if (is_object($v)) {
					//skip
				} else {
					$array[$k] = $wpdb->escape($v);
				}
			}
		}
	}
	
	/**
	 * Log user in.
	 *
	 * @since 2.8
	 *
	 * @param string $username User's username.
	 * @param string $password User's password.
	 * @return mixed WP_User object if authentication passed, false otherwise
	 */
	function login($username, $password) {
		if ( !get_option( 'enable_xmlrpc' ) ) {
			$this->error = new IXR_Error( 405, sprintf( __( 'XML-RPC services are disabled on this blog.  An admin user can enable them at %s'),  admin_url('options-writing.php') ) );
			return false;
		}
	
		$user = wp_authenticate($username, $password);
	
		if (is_wp_error($user)) {
			$this->error = new IXR_Error(403, __('Bad login/pass combination.'));
			return false;
		}
	
		set_current_user( $user->ID );
		return $user;
	}
}

$xpost_xmlrpc = new xpost_xmlrpc();
	
?>