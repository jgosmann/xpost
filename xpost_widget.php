<?php
/*
 * Widget for selecting blogs to crosspost to.
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

require_once( 'xpost_config.php' );
require_once( ABSPATH . WPINC .'/class-IXR.php' );

add_action( 'admin_menu', 'xpost_add_widget' );
add_action( 'wp_ajax_xpost_get_categories', 'xpost_get_categories' );

function xpost_add_widget() {
	add_meta_box( 'xpost-widget', 'Xpost', 'xpost_widget', 'post', 'side' );
}

/**
 * Echoes the Xpost widget. Categories of the blogs will be loaded with AJAX.
 */
function xpost_widget() {
	global $wpdb;
	$sql = "SELECT id, name, url, selected, xpost_comments FROM ".XPOSTCS_TABLE_NAME;
	$blogs = $wpdb->get_results( $sql );

	echo '<input type="hidden" name="xpost_nonce" id="xpost_nonce" value="'.wp_create_nonce( 'xpost' ).'" />';
	
	$errors = get_post_meta( $_GET['post'], '_xpost_errors', true );
	if( !empty( $errors ) ) {
		echo '<ul style="color: #f00; font-weight: bold;">';
		echo $errors;
		echo '</ul>';
	}
	
	echo '<p>';
	_e('Crosspost to the following blogs:', 'xpost');
	echo '</p>';

	echo '<div style="border-style:solid; border-width: 1px; height: 400px; overflow: auto; padding: 0.5em 0.9em; border-color:#dfdfdf;"><ul>';
		echo '<li style="display: none;"></li>';
		foreach( $blogs as $blog ) {
			$cbId = 'xpost_blog'.$blog->id;
			$catsListId = 'xpost_blog'.$blog->id.'_cats';
			$cbXpostCommentsId = 'xpost_blog'.$blog->id.'_xpostComments';
			$cbXpostCommentsList = $cbXpostCommentsId.'_list';
			$postId = $_GET['post'] ? $_GET['post'] : -1;
			$selected = $blog->selected;
			$xpost_comments = $blog->xpost_comments;
			if( get_post_meta( $_GET['post'], '_'.$cbId, true ) != '' ) {
				$selected = (get_post_meta( $_GET['post'], '_'.$cbId, true ) == '1') ? true : false;
			} 
			if( get_post_meta( $_GET['post'], '_'.$cbXpostCommentsId, true ) != '' ) {
				$xpost_comments = (get_post_meta( $_GET['post'], '_'.$cbXpostCommentsId, true ) == '1') ? true : false;
			} ?>
			<li style="border-bottom: 1px solid #dfdfdf; margin-bottom: 0.8em; padding-bottom: 0.7em;">
				<label for="<?php echo $cbId; ?>">
					<input id="<?php echo $cbId; ?>" name="<?php echo $cbId; ?>" type="checkbox" <?php echo $selected ? 'checked="checked"' : ''; ?> />
					<strong><?php echo esc_html($blog->name); ?></strong> (<?php echo clean_url($blog->url); ?>)
				</label>
			    <ul id="<?php echo $cbXpostCommentsList; ?>" style="margin-left: 9px; padding-left: 9px; border-left: 1px solid #dfdfdf;"><li>
			    	<label for="<?php echo $cbXpostCommentsId; ?>">
						<input id="<?php echo $cbXpostCommentsId; ?>" name="<?php echo $cbXpostCommentsId; ?>" type="checkbox" <?php echo $xpost_comments ? 'checked="checked"' : ''; ?> />
						<?php _e('Crosspost comments', 'xpost'); ?>
					</label>
				</li></ul>
				<script type='text/javascript'>//<![CDATA[				                         				
					jQuery(document).ready( function($) {
    					$("#<?php echo $catsListId; ?>").load(
							"<?php echo get_bloginfo( 'siteurl' ); ?>/wp-admin/admin-ajax.php",
							{	action: "xpost_get_categories",
								cookie: encodeURIComponent( document.cookie ),
								id    : <?php echo $blog->id; ?>,
								postid: <?php echo $postId; ?> },
							function() {
								if( !$("#<?php echo $cbId; ?>").attr( "checked" ) ) {
									$("#<?php echo $catsListId; ?>").hide();
									$("#<?php echo $cbXpostCommentsList; ?>").hide();
								}	
							} );
						$("#<?php echo $cbId; ?>").bind( "click", function() {
							if( $("#<?php echo $cbId; ?>").attr( "checked" ) ) {
								$("#<?php echo $catsListId; ?>").show();
								$("#<?php echo $cbXpostCommentsList; ?>").show();
							} else {
								$("#<?php echo $catsListId; ?>").hide();
								$("#<?php echo $cbXpostCommentsList; ?>").hide();
							}
						} );
					} );
				//]]></script>
				<ul id="<?php echo $catsListId; ?>" style="margin-left: 9px; padding-left: 9px; border-left: 1px solid #dfdfdf;"><li><?php _e('Loading categories&nbsp;&hellip;', 'xpost'); ?></li></ul>
			</li>
		<?php }
	echo '</ul></div>';
}

/**
 * Echoes the categories of the blog with the ID $_POST['id'] for post $_POST['postid'].
 */
function xpost_get_categories() {
	global $wpdb;
	$id = intval( $_POST['id'] );
	$postId = intval( $_POST['postid'] );
	
	$sql = "SELECT blogid, xmlrpc, user,xpost_community_server, password FROM ".XPOSTCS_TABLE_NAME." WHERE id = $id";
	$blog = $wpdb->get_row( $sql );
	
	if( empty( $blog )==true ) {
		echo '<li>';
		_e('Could not load blog data.', 'xpost');
		echo '</li>';
		exit( -1 );
	}

	if ($blog->xpost_community_server){
		return xpost_get_categories_cs($blog);
	}else{
		return  xpost_get_categories_wp($blog);
	}
	
}

function xpost_get_categories_cs($blog)
{
	global $wpdb;
	$id = intval( $_POST['id'] );
	

	/* Fetch categories */
	$client = @new IXR_Client( $blog->xmlrpc );
	$success = $client->query( 'metaWeblog.getCategories',  $blog->user, $blog->user, $blog->password );
	$response = $client->getResponse();	;
	
	if ( !$success || isset( $response['faultString'] ) ) {		
		_e('XML-RPC connection to the blog failed', 'xpost');
		if( !$success ) {
			echo ': ' . $client->getErrorMessage();
		} else if( isset( $response['faultString'] ) ){
			echo ': ' . $response['faultString'];
		} else {
			echo '.';
		}
		exit( -1 );
	}
	
	/* Fetch used categories */
	$usedCats = array();	
	$sql = "SELECT remote_postid FROM ".XPOSTCS_POSTS_TABLE_NAME." WHERE id = $id AND local_postid = $postId";
	$rpost = $wpdb->get_row( $sql );
	if( isset( $rpost ) ) {
		$success = $client->query( 'metaWeblog.getPost', $rpost->remote_postid, $blog->user, $blog->password );
		$usedResponse = $client->getResponse();
		if ( $success && isset( $usedResponse['categories'] ) ) {	
			foreach( $usedResponse['categories'] as $category ) {
				$usedCats[$category] = true;
			}
		}
	}
	
	/* Build category tree */
	$indexedCats = array();
	$rootCats = array();
	$minId = PHP_INT_MAX;
	$maxId = 0;
	foreach( $response as $category ) {
		$catId = intval( $category['categoryid'] );
		$parentId = intval( $category['parentId'] );
		$name = esc_html( $category['title'] );
		
		$indexedCats[$catId]['name'] = $name;
		$indexedCats[$catId]['selected'] = isset( $usedCats[$name] );
		$indexedCats[$catId]['parentId'] = $parentId;
		if( $parentId == 0 ) {
			$rootCats[] = $catId;
		} else {
			$indexedCats[$parentId]['subCats'][] = $catId;
		}
		
		if( $catId > $maxId ) {
			$maxId = $catId;
		}
		if( $catId < $minId ) {
			$minId = $catId;
		}
	}
	
	echo '<li style="display: none;">';
	echo '<input type="hidden" id="xpost_blog'.$id.'_min" name="xpost_blog'.$id.'_min" value="'.$minId.'" />';
	echo '<input type="hidden" id="xpost_blog'.$id.'_max" name="xpost_blog'.$id.'_max" value="'.$maxId.'" />';
	echo '</li>';
	
	echo_category_tree( $indexedCats, $rootCats, $id );
	
	exit( 0 ); // This call prevents a 0 from being appended.
}

function xpost_get_categories_wp($blog)
{
	global $wpdb;
	$id = intval( $_POST['id'] );
	$postId = intval( $_POST['postid'] );
	
	/* Fetch categories */
	$client = @new IXR_Client( $blog->xmlrpc );
	//echo json_encode($blog);
	$success = $client->query( 'wp.getCategories', $blog->blogid, $blog->user, $blog->password );
	$response = $client->getResponse();	;
	
	if ( !$success || isset( $response['faultString'] ) ) {		
		_e('XML-RPC connection to the blog failed', 'xpost');
		if( !$success ) {
			echo ': ' . $client->getErrorMessage();
		} else if( isset( $response['faultString'] ) ){
			echo ': ' . $response['faultString'];
		} else {
			echo '.';
		}
		exit( -1 );
	}
	
	/* Fetch used categories */
	$usedCats = array();	
	$sql = "SELECT remote_postid FROM ".XPOSTCS_POSTS_TABLE_NAME." WHERE id = $id AND local_postid = $postId";
	$rpost = $wpdb->get_row( $sql );
	if( isset( $rpost ) ) {
		$success = $client->query( 'metaWeblog.getPost', $rpost->remote_postid, $blog->user, $blog->password );
		$usedResponse = $client->getResponse();
		if ( $success && isset( $usedResponse['categories'] ) ) {	
			foreach( $usedResponse['categories'] as $category ) {
				$usedCats[$category] = true;
			}
		}
	}
	
	/* Build category tree */
	$indexedCats = array();
	$rootCats = array();
	$minId = PHP_INT_MAX;
	$maxId = 0;
	foreach( $response as $category ) {
		$catId = intval( $category['categoryId'] );
		$parentId = intval( $category['parentId'] );
		if ($blog->xpost_community_server){
			$name = esc_html( $category['title'] );
		}else{
			$name = esc_html( $category['categoryName'] );
		}
		$indexedCats[$catId]['name'] = $name;
		$indexedCats[$catId]['selected'] = isset( $usedCats[$name] );
		$indexedCats[$catId]['parentId'] = $parentId;
		if( $parentId == 0 ) {
			$rootCats[] = $catId;
		} else {
			$indexedCats[$parentId]['subCats'][] = $catId;
		}
		
		if( $catId > $maxId ) {
			$maxId = $catId;
		}
		if( $catId < $minId ) {
			$minId = $catId;
		}
	}
	
	echo '<li style="display: none;">';
	echo '<input type="hidden" id="xpost_blog'.$id.'_min" name="xpost_blog'.$id.'_min" value="'.$minId.'" />';
	echo '<input type="hidden" id="xpost_blog'.$id.'_max" name="xpost_blog'.$id.'_max" value="'.$maxId.'" />';
	echo '</li>';
	
	echo_category_tree( $indexedCats, $rootCats, $id );
	
	exit( 0 ); // This call prevents a 0 from being appended.
}

/**
 * Echoes recursivly the list of categories.
 * @param array $indexed Array with all categories, indexed by the category IDs.
 * @param array $root    Array with all IDs of top level categories.
 * @param int   $blogid  ID of the blog for which the categories will be echoed.
 */
function echo_category_tree( $indexed, $root, $blogid ) {
	foreach( $root as $categoryId) {
		$category = $indexed[$categoryId];
		$idString = 'xpost_blog'.$blogid.'_cat'.$categoryId; ?>
		<li><label for="<?php echo $idString; ?>">
			<input id="<?php echo $idString; ?>" name="<?php echo $idString; ?>" type="checkbox" value="<?php echo $category['name']; ?>" <?php echo $category['selected'] ? 'checked="checked"' : ''; ?> />
			<?php
			echo $category['name'];
			if( count( $category['subCats'] ) > 0 ) {
				echo '<ul class="children" style="margin-left: 18px">';
				echo_category_tree( $indexed, $category['subCats'], $blogid );
				echo '</ul>';
			} ?>
		</label></li>
	<?php }
}

?>