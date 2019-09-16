<?php
/*
Plugin Name: WpMsObjectDuplicator
Version: 1.0
Description: WordPress Multisite Object Duplicator allows to duplicate post to another site in Multisite
Author: Polumisnyy
*/

class WpMsObjectDuplicator {



    private static $_instance;



    static function getInstance() {
        if ( ! ( self::$_instance instanceof self ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }



    /*
     * Constructor
     */
    public function __construct() {
        if( !is_multisite() )
            return;

        add_action( 'admin_init', array( $this, 'add_option_to_general_settings' ) );
        add_filter( 'post_row_actions', array( $this, 'add_action_to_duplicate_objects' ), 10, 2 );
        add_action( 'admin_action_duplicate_object_to_anther_site_as_draft', array( $this, 'do_duplicate_object_to_anther_site_as_draft' ) );
    }



    public function add_option_to_general_settings(){
        register_setting('general', 'wpmsod_site_id', 'esc_attr');
        add_settings_field('wpmsod_site_id', '<label for="wpmsod_site_id">' .
            __('Dublicate post to site' , 'wp_ms_object_duplicator' ) . '</label>' , array( $this, 'display_wpmsod_site_id' ), 'general');
    }



    public function display_wpmsod_site_id(){
        $value  = get_option( 'wpmsod_site_id', '' );
        $sites  = get_sites( array(
            'site__not_in' => array( get_current_blog_id() )
        ) );
        $html = '<select name="wpmsod_site_id" id="wpmsod_site_id">';

        if( $sites ) :
            foreach ( $sites as $site ):
                $blog_details = get_blog_details( $site->blog_id );
                $selected = ( $value && $value == $site->blog_id ) ? "selected" : "";
                $html .= '<option value="' . $site->blog_id . '" ' . $selected . ' >' . $blog_details->blogname . '</option>';
            endforeach;
        endif;

        $html .= '</select>';
        echo $html;
    }



    public function add_action_to_duplicate_objects( $actions, $post ) {
        if( get_option( 'wpmsod_site_id' ) && $post->post_type !== 'attachment' ) {
            $actions['duplicate_object_to_anther_site'] = '<a href="' . self::get_post_dublicate_link($post->ID) . '">' .
                __('Clone to another site', 'wp_ms_object_duplicator') . '</a>';
        }
        return $actions;
    }



    public static function  get_post_dublicate_link( $id = 0 ) {
        
        if( !$to_site_id = get_option( 'wpmsod_site_id' ) )
            return;

        if ( !$post = get_post( $id ) )
            return;

        $action_name = "duplicate_object_to_anther_site_as_draft";
        $action = '?action=' . $action_name . '&post=' . $post->ID . '&to_site=' . $to_site_id;

        $post_type_object = get_post_type_object( $post->post_type );

        if ( !$post_type_object )
        return;

        return wp_nonce_url( admin_url( "admin.php". $action ), 'duplicate_object_' . $post->ID . '_to_site_' . $to_site_id );
    }



    public function do_duplicate_object_to_anther_site_as_draft( $status = '' ){

        if (! ( isset( $_GET['post']) || isset( $_POST['post']) || isset( $_GET['to_site']) || isset( $_POST['to_site'])  || ( isset($_REQUEST['action']) && 'duplicate_object_to_anther_site_as_draft' == $_REQUEST['action'] ) ) ) {
            wp_die(esc_html__('No post to duplicate has been supplied!', 'wp_ms_object_duplicator'));
        }

        // post id
        $id = (isset($_GET['post']) ? $_GET['post'] : $_POST['post']);
        // get site id
        $to_site_id = (isset($_GET['to_site']) ? $_GET['to_site'] : $_POST['to_site']);

        check_admin_referer('duplicate_object_' . $id . '_to_site_'. $to_site_id );

        $post = get_post($id);

        if (isset($post) && $post!=null) {
            $new_post_id = self::do_dublicate_to_another_site( $post, $to_site_id , true );
            wp_redirect( admin_url( '/edit.php?post_type=' . $post->post_type ), 301 );
            exit;
        }else{
            wp_die(esc_html__('Copy creation failed, could not find post', 'wp_ms_object_duplicator'));
        }
    }



    public static function do_dublicate_to_another_site( $post, $to_site, $with_meta = fasle, $with_img = true ){
        $new_post = array(
            'comment_status' => $post->comment_status,
            'ping_status' => $post->ping_status,
            'post_content' => $post->post_content ,
            'post_content_filtered' => $post->post_content_filtered ,
            'post_excerpt' => $post->post_excerpt,
            'post_mime_type' => $post->post_mime_type,
            'post_parent' => $post->post_parent,
            'post_password' => $post->post_password,
            'post_status' => 'draft',
            'post_title' => $post->post_title,
            'post_type' => $post->post_type,
            'post_name' => $post->post_name
        );

        if( $with_meta )
            $meta = get_post_meta( $post->ID );

        if( $with_img )
            $img_url = get_the_post_thumbnail_url( $post->ID, 'full' );


        switch_to_blog( $to_site );
        $new_post_id = wp_insert_post(wp_slash($new_post));
        if( $new_post_id && $with_meta ) {
            foreach ( $meta as $meta_key => $meta_value ) {
                update_post_meta( $new_post_id, $meta_key, $meta_value[0] );
            }
        }
        if( $img_url )
            self::addThumb( $new_post_id, $img_url );

        restore_current_blog();

        return $new_post_id;
    }



    public static function addThumb( $post_id, $coin ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $img_tag = media_sideload_image( $coin, $post_id, '', 'id' );
        if( $img_tag ) {
            delete_post_thumbnail( $post_id );
            set_post_thumbnail( $post_id, $img_tag );
        }
    }
}

WpMsObjectDuplicator::getInstance();