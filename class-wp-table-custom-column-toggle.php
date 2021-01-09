<?php
/**
 * Add a toggle column to WP_Table or its siblings
 *
 * @author: Per Søderlind / DSS
 * @since: 15/08/2018
 */

define( 'CUSTOM_COLUMN_TOGGLE_VERSION', '0.1.0' );

if ( class_exists( 'WP_Table_Custom_Column_Toggle' ) ) {
	return;
}


class WP_Table_Custom_Column_Toggle {

	/**
	 * Default properties are:
	 *    [
	 *        'meta_key'        => 'column_meta_key',  // "toggle" for a single site is saved using post meta
	 *        'column_id'       => 'column_id',
	 *        'column_name'     => 'Column Toggle',
	 *        'column_hooks'    => [
	 *            'header'  => 'manage_page_posts_columns',
	 *            'content' => 'manage_page_posts_custom_column',
	 *        ],
	 *        'use_siteoptions' => false,
	 *    ]
	 *
	 * @var object
	 */
	private $properties;

	/**
	 * Undocumented function
	 *
	 * @param Array $properties
	 */
	private function __construct( $properties ) {
		$this->properties = (object) wp_parse_args(
			$properties, [
				'meta_key'        => 'column_meta_key',
				'column_id'       => 'column_id',
				'column_name'     => 'Column Toggle',
				'column_hooks'    => [
					'header'  => 'manage_page_posts_columns',
					'content' => 'manage_page_posts_custom_column',
				],
				'use_siteoptions' => false,
			]
		);

		$this->init();
	}

	/**
	 *  * static factory
	 *
	 * @link https://carlalexander.ca/static-factory-method-pattern-wordpress/
	 *
	 * @param Array $properties
	 * @return void
	 */
	public static function create( Array $properties ) {
		return new self( $properties );
	}

	public function init() {
		add_filter( $this->properties->column_hooks['header'], [ $this, 'filter_posts_columns' ] );
		add_action( $this->properties->column_hooks['content'], [ $this, 'page_column' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		if ( is_admin() ) {
			add_action( 'wp_ajax_' . $this->properties->column_id . '_update_option', [ $this, 'on_ajax_update_option' ] );
		}
	}


	/**
	 * Add column header to Pages
	 *
	 * @param array $columns
	 * @return void
	 */
	public function filter_posts_columns( $columns ) {
		$columns[ $this->properties->column_id ] = sprintf( '%1$s', $this->properties->column_name );
		// $columns[ $this->properties->column_id ] = sprintf( '<span class="Xdashicons Xdashicons-search" Xtitle="%1$s">%1$s</span>', $this->properties->column_name );
		return $columns;
	}

	/**
	 * Add checkbox to Index Me row
	 *
	 * @param string $column column id
	 * @param int $post_id
	 * @return void
	 */
	public function page_column( $column, $post_id ) {
		if ( $this->properties->column_id === $column ) {

			$toggle        = $this->get_value( $post_id, '0' );
			$input_checked = ( '1' == $toggle ) ? 'checked' : '';
			$togle_to      = ( '1' == $toggle ) ? '0' : '1';
			printf(
				'<input id="%1$s_%2$s" class="tgl tgl-flat" type="checkbox" %3$s data-changeto="%4$s" data-dataid="%2$s" /><label class="tgl-btn" for="%1$s_%2$s"></label>',
				$this->properties->column_id,
				$post_id,
				$input_checked,
				$togle_to
			);
		}
	}

	/**
	 * Load scripts & styles
	 *
	 * @return void
	 */
	public function admin_scripts() {

		$http_scheme = ( is_ssl() ) ? 'https' : 'http';
		// multisite fix, use home_url() if domain mapped to avoid cross-domain issues
		if ( is_multisite() && home_url() != site_url() ) {
			$ajaxurl = home_url( '/wp-admin/admin-ajax.php', $http_scheme );
		} else {
			$ajaxurl = site_url( '/wp-admin/admin-ajax.php', $http_scheme );
		}
		// $url = plugins_url( '', __FILE__ );
		// $url = get_template_directory_uri();

		$file_path = __DIR__;
		$url  = str_replace( $_SERVER['DOCUMENT_ROOT'], '', $file_path );

		wp_enqueue_script( 'custom-column-toggle', $url . '/js/custom-column-toggle.js', [ 'jquery', 'jquery-effects-core' ], CUSTOM_COLUMN_TOGGLE_VERSION );
		wp_enqueue_style( 'custom-column-toggle', $url . '/css/custom-column-toggle.css', [], CUSTOM_COLUMN_TOGGLE_VERSION );
		wp_localize_script(
			'custom-column-toggle', 'customColumnToggle', [
				'nonce'     => wp_create_nonce( $this->properties->column_id ),
				'column_id' => $this->properties->column_id,
				'ajaxurl'   => $ajaxurl,
			]
		);
		$column_id = $this->properties->column_id;
		$style     = <<< EOS
		.column-$column_id {
			width: 5%;
		}
		td.column-$column_id {
			text-align: center;
		}
EOS;
		wp_add_inline_style( 'custom-column-toggle', $style );
	}

	/**
	 * Handle AJAX call from js/index-me.js
	 *
	 * @return void
	 */
	public function on_ajax_update_option() {
		header( 'Content-type: application/json' );
		if ( check_ajax_referer( $this->properties->column_id, 'security', false ) ) {
			$data_id   = filter_var( $_POST['data_id'], FILTER_VALIDATE_INT, [ 'default' => 0 ] );
			$change_to = filter_var( $_POST['change_to'], FILTER_VALIDATE_INT, [ 'default' => 1 ] );
			if ( ! $data_id ) {
				$response['data'] = 'something went wrong ...';
				echo json_encode( $response );
				die();
			}
			if ( isset( $change_to ) ) {

				if ( ! $this->properties->use_siteoptions ) {
					update_post_meta( $data_id, $this->properties->meta_key, $change_to );
				}
				$this->change_value( $data_id, $change_to );
				if ( '1' == $change_to ) {
					$response['change_to'] = '0';
				} else {
					$response['change_to'] = '1';
				}
				$response['response'] = 'success';
			} else {
				$response['response'] = 'failed';
				$response['data']     = 'something went wrong ...';
			}
		} else {
			$response['response'] = 'failed';
			$response['message']  = 'invalid nonse' . $this->properties->column_id;
		}

		echo json_encode( $response );
		die();
	}


	public function get_values() {
		$values = $this->get( $this->properties->column_id );

		if ( false !== $values ) {
			return array_keys( array_intersect( $values, [ 1 ] ) );
		} else {
			return [];
		}
	}

	/**
	 * Change the portfolio status (vissible / hidden), also reset the single site post query transient
	 *
	 * @param int     $site_id
	 * @param string  $status  'vissible' or 'hidden'
	 * @return [type]          [description]
	 */
	private function change_value( $id, $value ) {
		$record = $this->get( $this->properties->column_id );
		if ( false !== $record ) {
			$record[ $id ] = $value;
			$this->update( $this->properties->column_id, $record );
		} else {
			$this->update( $this->properties->column_id, [ $id => $value ] );
		}
	}

	public function get_value( $id, $default = false ) {
		$record = $this->get( $this->properties->column_id );
		if ( false !==  $record && isset( $record[ $id ] ) ) {
			$get_value = $record[ $id ];
		} else {
			$get_value = $default;
		}
		return $get_value;
	}

	private function get( $id ) {
		if ( $this->properties->use_siteoptions ) {
			$get = get_site_option( $id, false );
		} else {
			$get = get_option( $id, false );
		}
		return $get;
	}

	private function update( $id, $value ) {
		if ( $this->properties->use_siteoptions ) {
			$update = update_site_option( $id, $value );
		} else {
			$update = update_option( $id, $value );
		}
		return $update;
	}
}
