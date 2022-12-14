<?php
/**
 * Gutenberg class
 *
 * @author: themeum
 * @author_uri: https://themeum.com
 * @package Tutor
 * @since v.1.0.0
 */


namespace TUTOR;


if ( ! defined( 'ABSPATH' ) )
	exit;

class Gutenberg {

	public function __construct() {
		if ( ! function_exists('register_block_type')){
			return;
		}

		add_action( 'init', array($this, 'register_blocks') );
		add_filter('block_categories_all', array($this, 'registering_new_block_category'), 10, 2);
		add_action('wp_ajax_render_block_tutor', array($this, 'render_block_tutor'));
		add_filter( 'rest_user_query', array($this, 'author_list_dropdown'), 10, 2 );
	}
	
	/**
	 * Bind author list on gutenberg editor mode
	 *
	 * @param array $prepared_args
	 * @param WP_REST_Request $request
	 * 
	 * @return void
	 * 
	 * @since 2.1.0
	 */
	public function author_list_dropdown( $prepared_args, $request ) {
		$url	= $request->get_header('referer');
		$parts	= parse_url( $url );
		parse_str( $parts['query'], $query );
		$post_id = (int) $query['post'] ?? 0;

		if ( ! $post_id ) {
			return $prepared_args;
		}
		
		$post = get_post( $post_id );
		
		if ( $post->post_type === tutor()->course_post_type && tutor_utils()->get_option( 'enable_gutenberg_course_edit' ) === true ){
			// Modify the wp/v2/users endpoint request from gutenberg editor
			unset( $prepared_args['who'] );
			$prepared_args['role__in'] = array( 'administrator', 'tutor_instructor' );
		}

		return $prepared_args;
	}

	function register_blocks() {
		global $pagenow;
		if ( 'widgets.php' !== $pagenow ) {
			wp_register_script(
				'tutor-student-registration-block', tutor()->url . 'assets/js/lib/gutenberg_blocks.js', array( 'wp-blocks', 'wp-i18n', 'wp-element', 'wp-components', 'wp-editor' )
			);
		}

		register_block_type( 'tutor-gutenberg/student-registration', array(
			'editor_script'     => 'tutor-student-registration-block',
			'render_callback'   => array($this, 'render_block_student_registration'),
		) );
		/*
		register_block_type( 'tutor-gutenberg/student-dashboard', array(
			'editor_script' => 'tutor-student-registration-block',
			'render_callback'   => array($this, 'render_block_tutor_dashboard'),
		) );*/
		register_block_type( 'tutor-gutenberg/instructor-registration', array(
			'editor_script' => 'tutor-student-registration-block',
			'render_callback'   => array($this, 'render_block_tutor_instructor_registration_form'),
		) );

		// Check if WP version is equal to or greater than 5.9.
		global $wp_version;
		if ( version_compare( $wp_version, '5.9', '>=' ) && function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			wp_localize_script( 'tutor-student-registration-block', '_tutor_gutenberg_block_data', array(
                'is_wp_version_5_9' => 'true',
            ) );
		} else {
			wp_localize_script( 'tutor-student-registration-block', '_tutor_gutenberg_block_data', array(
                'is_wp_version_5_9' => 'false',
            ) );
		}
	}

	public function registering_new_block_category($categories, $post ){
		return array_merge(
			array(
				array(
					'slug' => 'tutor',
					'title' => __( 'Tutor LMS', 'tutor' ),
				),
			),
			$categories
		);
	}

	public function render_block_student_registration($args){
		return do_shortcode("[tutor_student_registration_form]");
	}
	public function render_block_tutor_dashboard($args){
		return do_shortcode("[tutor_dashboard]");
	}
	public function render_block_tutor_instructor_registration_form($args){
		return do_shortcode("[tutor_instructor_registration_form]");
	}

	//For editor
	public function render_block_tutor(){
		tutor_utils()->checking_nonce();

		$shortcode = sanitize_text_field($_POST['shortcode']);
		
		$allowed_shortcode = array(
			'tutor_instructor_registration_form',
			'tutor_student_registration_form'
		);
		
		if(!in_array($shortcode, $allowed_shortcode)) {
			wp_send_json_error( );
		}

		wp_send_json_success(do_shortcode("[{$shortcode}]"));
	}

}