<?php
/**
 * Plugin Name: App Link
 * Plugin URI: mailto: imback.sufi@gmail.com
 * Description: Plugin to connect with our app
 * Version: 1.0
 * Author: Sufi Shaikh
 * Author URI: mailto: imback.sufi@gmail.com
 **/
 
 
 
add_action( 'rest_api_init', 'rest_api_media_url' );

function rest_api_media_url() {
	register_rest_field( 'post', 'media_url',
		array(
			'get_callback'    => 'audio_video_url_rest',
			'update_callback' => null,
			'schema'          => null,
		)
	);
}

function audio_video_url_rest($post, $field_name, $request){
	if(get_post_meta($post['id'],'video_url',true)){
		return get_post_meta($post['id'],'video_url',true);  
	}else if(get_post_meta($post['id'],'audio_url',true)){
		return get_post_meta($post['id'],'audio_url',true);
	}else if(get_post_format($post['id']) == 'audio'){
		$enclosure = get_post_meta( get_the_ID(), 'enclosure',true );
		
		if(strpos($enclosure, '.mp3') !== false ){
			$enclosure = explode('.mp3',$enclosure);
			$audio_url = $enclosure[0].'.mp3';	
		}
		return $audio_url;
	}else{
		return false;
	}
}

// Endpoint in REST API for Menus

add_action( 'rest_api_init', function () {
	register_rest_route( 'wp/v2', '/menus', array(
        'methods' => 'GET',
        'callback' => 'get_registered_menu',
    ) );
} );

function get_registered_menu() {
	$menus = array();
	
	foreach(get_terms('nav_menu') as $menu_data){
		$menu = array();
		$menu['id'] = $menu_data->term_id;
		$menu['slug'] = $menu_data->slug;
		$menu['name'] = $menu_data->name;
		$menu['items'] = array();
		
		foreach(wp_get_nav_menu_items($menu_data->slug) as $menu_item){
			
			$object_id = $menu_item->type == 'custom' ? 0 : (int)$menu_item->object_id;
			
			array_push($menu['items'],
				array(
					$menu_item->ID,
					$menu_item->title,
					$menu_item->url,
					$menu_item->type,
					$object_id,
				)
			);
		}
		
		$menus[$menu['slug']] = $menu;
	}
	
    return $menus;
}

// Endpoint for video URL

add_action( 'rest_api_init', 'reg_video_URL_endpoint' );

function reg_video_URL_endpoint() {
    register_rest_route( 'wp/v2', '/video_url/(?P<id>[0-9]+)', array(
        'methods' => 'GET',
        'callback' => 'retrieve_post_video_url',
    ) );
}

function retrieve_post_video_url($data){
	$post_id = (int)$data['id'];
	return get_post_meta($post_id,'__cbc_video_url',true);
}

// Endpoint for Album Track

add_action( 'rest_api_init', 'reg_album_art' );

function reg_album_art() {
    register_rest_route( 'wp/v2', '/album_art', array(
        'methods' => 'GET',
        'callback' => 'retrieve_album_art',
    ) );
}

function retrieve_album_art(){
	
	$file = file_get_contents('https://radiofreeorg.radioca.st/');
	$xml_obj= json_decode(json_encode(simplexml_load_string($file)),true);
	$divs = $xml_obj["body"]["div"];
	$author_title = '';
	
	foreach($divs as $div){
		if($div['@attributes']['class'] == 'roundbox'){
			foreach($div["div"][1]["table"]["tbody"]["tr"] as $child){
				if(strtolower($child['td']['0']) == 'currently playing:')
					$author_title = $child['td']['1'];
			}
		}
	}
	
	$info = explode(' - ',$author_title);
	$artist = urlencode(trim($info[0]));
	$track = trim($info[1]);
	
	$url = "https://itunes.apple.com/search?term=$artist&limit=200";
	
	$itunes_info = json_decode(file_get_contents($url),true);
	
	foreach($itunes_info['results'] as $result){
		if($result["trackName"] == $track)
			$artwork = $result['artworkUrl100'];
		
		if(!$artwork){
			if($result["trackName"] == $track.' (Live)')
				$artwork = $result['artworkUrl100'];
		}
	}
	
	$artwork = str_replace('100x100','500x500',$artwork);
	
	$return = array();
	$return['url'] = thb_get_option('shoutcast_stream');
	$return['album'] = trim($info[0]);
	$return['track'] = $track;
	$return['artwork'] = $artwork;
	
	return $return;
}


//Endpoint for post author
	
add_action( 'rest_api_init', 'reg_author_endpoint' );

function reg_author_endpoint() {
    register_rest_route( 'wp/v2', '/postauthor/(?P<id>[0-9]+)', array(
        'methods' => 'GET',
        'callback' => 'retrieve_post_author',
    ) );
}

function retrieve_post_author($data){
	$post_id = (int)$data['id'];
	$author_id = get_post($post_id)->post_author;
	$user_info = get_userdata($author_id);
	return $user_info->display_name;
}


// Add post author field in post endpoint

add_action( 'rest_api_init', 'rest_api_post_author' );

function rest_api_post_author() {
	register_rest_field( 'post', 'postauthor',
		array(
			'get_callback'    => 'post_author_add',
			'update_callback' => null,
			'schema'          => null,
		)
	);
}

function post_author_add($post, $field_name, $request){
	$post_id = (int)$post['id'];
	$author_id = get_post($post_id)->post_author;
	$user_info = get_userdata($author_id);
	return $user_info->display_name;
}

// Add featured Image field in post endpoint

add_action( 'rest_api_init', 'rest_api_featured_image_url' );

function rest_api_featured_image_url() {
	register_rest_field( 'post', 'featured_image_url',
		array(
			'get_callback'    => 'get_featured_image_url',
			'update_callback' => null,
			'schema'          => null,
		)
	);
}

function get_featured_image_url($post, $field_name, $request){
	$post_id = (int)$post['id'];
	if( has_post_thumbnail($post_id) )
		return get_the_post_thumbnail_url($post_id,'full');
	else
		return '';
}

/* Get Image Height */

add_action( 'rest_api_init', 'rest_api_featured_image_height' );

function rest_api_featured_image_height() {
	register_rest_field( 'post', 'ftiheight',
		array(
			'get_callback'    => 'featured_image_height',
			'update_callback' => null,
			'schema'          => null,
		)
	);
}

function featured_image_height($post, $field_name, $request){
	$post_id = (int)$post['id'];
	if( has_post_thumbnail($post_id) ){
		list($width, $height) = getimagesize(get_the_post_thumbnail_url($post_id,'full'));
		return $height;
	}else
		return '';
}

/* Get Image Width */

add_action( 'rest_api_init', 'rest_api_featured_image_width' );

function rest_api_featured_image_width() {
	register_rest_field( 'post', 'ftiwidth',
		array(
			'get_callback'    => 'featured_image_width',
			'update_callback' => null,
			'schema'          => null,
		)
	);
}

function featured_image_width($post, $field_name, $request){
	$post_id = (int)$post['id'];
	if( has_post_thumbnail($post_id) ){
		list($width, $height) = getimagesize(get_the_post_thumbnail_url($post_id,'full'));
		return $width;
	}else
		return '';
}