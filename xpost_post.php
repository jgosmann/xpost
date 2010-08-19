<?php
/*
 * The actual crossposting logic.
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

require_once( ABSPATH . '/wp-includes/post.php' );

add_action( 'save_post', 'xpost_crosspost' );

/**
 * Does the actual crossposting.
 */
function xpost_crosspost( $localPostId ) {
	global $wpdb;
	
	if( !wp_verify_nonce( $_POST['xpost_nonce'], 'xpost' ) ) {
		return;
	}

	if( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) {
		return;
	}

	if( get_post_type( $localPostId ) != 'post' ) {
		return;
	}

	if( !current_user_can( 'edit_post', $localPostId ) ) {
		return;
	}
	
	$commentToken = get_post_meta( $localPostId, '_xpost_comment_token', true );
	if( empty( $commentToken ) ) {
		$commentToken = md5( uniqid( mt_rand(), true ) );
		update_post_meta( $localPostId, '_xpost_comment_token', $commentToken );
	}

	$sql = "SELECT id, blogid, xmlrpc, user, xpost_summary_only, password FROM ".XPOST_TABLE_NAME;
	$blogs = $wpdb->get_results( $sql );

	$errors = '';

	foreach( $blogs as $blog ) {
		if( !isset( $_POST['xpost_blog'.$blog->id] ) ) {
			update_post_meta( $localPostId, '_xpost_blog'.$blog->id, '0' );
		} else {
			update_post_meta( $localPostId, '_xpost_blog'.$blog->id, '1' );
			
			$xpost_comments = isset( $_POST['xpost_blog'.$blog->id.'_xpostComments'] );
			update_post_meta( $localPostId, '_xpost_blog'.$blog->id.'_xpostComments', $xpost_comments ? '1' : '0' );
			
			if( $_POST['visibility'] == 'private' ) {
				// We wait with this check until now, because we don't know
				// wheter a blog has been selected for crossposting before this
				// point.
				$errors .= '<li>';
				$errors .= __('Crossposting is not possible for private posts.', 'xpost');
				$errors .= '</li>';
				update_post_meta( $localPostId, '_xpost_errors', $errors );
				return;
			}
				
			$sql = "SELECT remote_postid FROM ".XPOST_POSTS_TABLE_NAME." WHERE id = $blog->id AND local_postid = $localPostId";
			$post = $wpdb->get_row( $sql );
			$createNew = empty( $post );
				
			/* Create post data struct to send */
			$permalink = get_permalink($localPostId);
			$postData = array();
			$postData['title'] = stripslashes( $_POST['post_title'] );
			
			if( !$blog->xpost_summary_only ) {
				$postData['description'] = $_POST['content'];
			} else {
				$postData['description'] = strip_tags( $_POST['content'] );
				$postData['description'] = preg_replace( '/\[(.*?)\]/', '', $postData['description'] );
				$postData['description'] = explode( ' ', $postData['description'] );
				$postData['description'] = array_slice( $postData['description'], 0, 80 );
				$postData['description'] = implode( ' ', $postData['description'] );
				$postData['description'] = preg_replace( '/\n/', '<br/>', $postData['description'] );
				$postData['description'] .= "...<br/>";
				$postData['description'] .= '<a href="'.$permalink.'"><b>'.__('Read More', 'xpost').'</b></a>'; 
			}
			$postData['description'] = stripslashes( $postData['description'] );
			$postData['link'] = $permalink;
			$postData['permalink'] = $permalink;
			$postData['mt_excerpt'] = stripslashes( $_POST['excerpt'] );
			// Next line is not used, because I think it might be dangerous,
			// because we don't know which slugs already exist in blog we post
			// to.
			//$postData['wp_slug'] = stripslashes( $_POST['name'] );
			$postData['mt_keywords'] = stripslashes( $_POST['tax_input']['post_tag'] );
			$postData['mt_allow_comments'] = ($_POST['comment_status'] == 'open') ? 1 : 0;
			$postData['mt_allow_pings'] = ($_POST['ping_status'] == 'open') ? 1 : 0;
			if( $_POST['visibility'] == 'password' ) {
				$postData['wp_password'] = stripslashes( $_POST['post_password'] );
			}

			$date = new DateTime();
			$date->setTimezone( new DateTimeZone('GMT') );
			$date->setDate( intval( $_POST['aa'] ), intval( $_POST['mm'] ), intval( $_POST['jj'] ) );
			$date->setTime( intval( $_POST['hh'] - get_option('gmt_offset') ), intval( $_POST['mn'] ), intval( $_POST['ss'] ) );
			// In hope to workaround a bug that post appear as scheduled, but do not publish
			// (instead showing "missed schedule") we publish all posts scheduled for the next
			// three minutes instantenous.
			if( abs( DateTime_getTimestamp( $date ) - DateTime_getTimestamp( new DateTime() ) ) > 3 * 60 ) {
				$postData['date_created_gmt'] = new IXR_Date( DateTime_getTimestamp( $date ) );
			}

			/* Collect categories */
			$min = intval( $_POST['xpost_blog'.$blog->id.'_min'] );
			$max = intval( $_POST['xpost_blog'.$blog->id.'_max'] );
			for( $i = $min; $i <= $max; ++$i ) {
				if( isset( $_POST['xpost_blog'.$blog->id.'_cat'.$i] ) ) {
					$postData['categories'][] = $_POST['xpost_blog'.$blog->id.'_cat'.$i];
				}
			}
			
			/* Set comments crossposting option */
			$postData['custom_fields'][0]['key'] = '_xpost_commet_broadcast_url';
			$postData['custom_fields'][0]['value'] = get_bloginfo( 'pingback_url' );
			$postData['custom_fields'][1]['key'] = '_xpost_comment_exclude_id';
			$postData['custom_fields'][1]['value'] = $blog->id;
			$postData['custom_fields'][2]['key'] = '_xpost_original_postid';
			$postData['custom_fields'][2]['value'] = $localPostId;
			$postData['custom_fields'][3]['key'] = '_xpost_comment_token';
			$postData['custom_fields'][3]['value'] = $commentToken;
							
			$publish = ($_POST['post_status'] == 'publish');
			
			$updateDb = false;
			$client = new IXR_Client( $blog->xmlrpc );
			if( !$createNew ) {
				$client->query( 'metaWeblog.editPost', $post->remote_postid, $blog->user, $blog->password, $postData, $publish );
				$response = $client->getResponse();
				if( is_array( $response ) && isset( $response['faultCode'] ) && $response['faultCode'] == 404 ) {
					$updateDb = true;
					$createNew = true;
				}
			}
			if( $createNew ) {
				$blogId = $blog->xpost_community_server ? $blog->user :$blog->blogid;
				$client->query( 'metaWeblog.newPost', $blogId, $blog->user, $blog->password, $postData, $publish );
			}

			$response = $client->getResponse();
			if ( empty( $response ) || (is_array( $response ) && isset( $response['faultString'] )) ) {
				$error = __('XML-RPC connection to the blog failed', 'xpost');
				if( !empty( $response['faultString'] ) ){
					$error .= ': ' . $response['faultString'];
				} else {
					$error .= '.';
				}
				$errors .= '<li>'.$error.' --- '.$postData['title'].'</li>';
			} else {
				$rowsAffected = 0;
				if( $updateDb ) {
					$rowsAffected = $wpdb->update( XPOST_POSTS_TABLE_NAME,
					array( 'remote_postid' => intval( $response ) ),
					array(
								'id' => $blog->id,
								'local_postid' => $localPostId ),
					array( '%d' ), array( '%d', '%d' ) );
				} else if( $createNew ) {
					$rowsAffected = $wpdb->insert( XPOST_POSTS_TABLE_NAME,
					array(
								'id' => $blog->id,
								'local_postid' => $localPostId,
								'remote_postid' => intval( $response ) ),
					array( '%d', '%d', '%d' ) );
						
				}
				if( ($updateDb || $createNew) && $rowsAffected < 1 ) {
					$errors .= '<li>';
					$errors .= __('Local database error. (Crosspost was created anyway.)', 'xpost');
					$errors .= '</li>';
				}
			}
		}
	}

	update_post_meta( $localPostId, '_xpost_errors', $errors );
}

/**
 * Convertes the DateTime $dt to a Unix timestamp. This works also with PHP
 * versions older than 5.3.0 in opposite to the DateTime->getTimestamp()
 * function.
 */
function DateTime_getTimestamp(&$dt) {
	$dtz_original = $dt->getTimezone();
	$dtz_utc = new DateTimeZone( "UTC" );
	$dt->setTimezone( $dtz_utc );
	$year = intval( $dt->format( "Y" ) );
	$month = intval( $dt->format( "n" ) );
	$day = intval( $dt->format( "j" ) );
	$hour = intval( $dt->format( "G" ) );
	$minute = intval( $dt->format( "i" ) );
	$second = intval( $dt->format( "s" ) );
	$dt -> setTimezone($dtz_original );
	return mktime( $hour, $minute, $second, $month, $day, $year );
}

?>
