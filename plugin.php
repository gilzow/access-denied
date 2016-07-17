<?php
/*
Plugin Name: Anti-Recon
Description: Attempts to prevent recon data gathering
Author: WpCampus
Version: 0.0.1
*/
/**
 * Code to be used in the plugin
 * User: gilzowp
 * Date: 6/20/16
 * Time: 12:03 PM
 */

/**********************************
 * username anti-enumeration stuff
 *********************************/
/**
 * Blocks remote attackers from enumerating user names
 * @param $strRedirectionURL
 * @param $strRequestedURL
 * @return mixed
 * @see https://developer.wordpress.org/reference/hooks/redirect_canonical/
 */
add_filter('redirect_canonical',function($strRedirectionURL, $strRequestedURL){
    if (1 === preg_match('/\?author=([\d]*)/', $strRequestedURL)) {
        $strRedirectionURL = false;
    }

    return $strRedirectionURL;
}, 10,2);

/**
 * Force wordpress to use /?author=id for author permalink
 */
add_action('init',function(){
    global $wp_rewrite;
    $wp_rewrite->author_structure = '';

});

/**
 * Removes username from the body class list.  Why does wordpress include the user name in the body class?  So you can
 * add per-user custom classes, but that seems like a very fringe case vs giving hackers all of your user names.
 *
 * @param $aryClasses array of classes to include in the body element
 * @return array filtered list of classes
 */
add_filter('body_class',function($aryClasses){
    if(is_author() && in_array('author',$aryClasses)){
        /**
         * match all classes of 'author-<username>' but not 'author-id'
         *
         * match: author-admin
         * match: author-gilzowp
         * NO match: author-5
         *
         */
        $aryUserNames = preg_grep('/^author-(?!\d+$).+$/',$aryClasses);
        if(count($aryUserNames) > 0){
            $aryClasses = array_diff($aryClasses,$aryUserNames);
        }
    }
    return $aryClasses;
},100,1);


/**
 * Removes the error message indicating an invalid user, or incorrect password for a specific user
 * @param $objUser WP_User|WP_Error
 * @return WP_Error|WP_User|null
 */
add_filter('authenticate',function($objUser){
    if(is_wp_error($objUser)){
        if(
                isset($objUser->errors['incorrect_password']) 
            ||  isset($objUser->errors['invalid_username'])
            ||  isset($objUser->errors['invalid_email'])
        ){
            $objUser = null;;
        }
    }

    return $objUser;
},99,1); 

   
/**
 * Alternate way to remove error message indicating an invalid user, or incorrect password
 */
add_filter('login_errors',function($error){
    if(false !== stripos($error,'password you entered') || false !== stripos($error,'invalid username')){
        $error = "Invalid credentials";
    }
    return $error;
},99,1);


/**********************************
 * Change wp-content location, can even put plugins/themes in completely different locations
 * @see https://codex.wordpress.org/Editing_wp-config.php
 *********************************/
define('WP_CONTENT_FOLDERNAME', 'resources');
define('WP_CONTENT_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . WP_CONTENT_FOLDERNAME );
// note: using SERVER_NAME or HTTP_HOST is *not* safe
define('WP_SITEURL', 'https://vagrantpress.dev/');
define('WP_CONTENT_URL', WP_SITEURL . WP_CONTENT_FOLDERNAME );

/**********************************
 * Remove Wordpress version stuff
 *********************************/
/**
 * remove generator meta
 */
add_filter('the_generator',function(){ return ''; });
remove_action( 'wp_head', 'wp_generator' );

/**
 * Removes version number from stylesheets and scripts and replaces with a hash
 * @param $strSrc string
 * @return string
 */
function arRemoveVersion($strSrc){
    global $wp_version;

    if(false!== strpos($strSrc,'ver=')){
        parse_str(parse_url($strSrc,PHP_URL_QUERY),$aryURLQuery);

        if(isset($aryURLQuery['ver']) && $wp_version == $aryURLQuery['ver']){
            $strSrc = esc_url(add_query_arg('ver',hash('crc32', 'ar'.$aryURLQuery['ver']), $strSrc));
        }
    }

    return $strSrc;
}

add_filter('style_loader_src','arRemoveVersion',999);
add_filter('script_loader_src','arRemoveVersion',999);

/**********************************
 * XMLRPC stuff
 *********************************/
// disable XML-RPC methods that require authentication
add_filter('xmlrpc_enabled','__return false');
//remove pingback support
add_filter('xmlrpc_methods', function( $aryMethods ) {
    unset( $aryMethods['pingback.ping'] );
    return $aryMethods;
});


//disable file editing in wp-config
define('DISALLOW_FILE_EDIT', true);
//or in our plugin
//disable the internal editor
if(!defined('DISALLOW_FILE_EDIT')){
    define('DISALLOW_FILE_EDIT', TRUE);
}