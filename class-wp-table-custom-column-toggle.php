<?php
/**
 * Add a toggle column to WP_Table or its siblings.
 *
 * @package WP_Table_Custom_Column_Toggle
 * @author: Per Søderlind
 * @since: 15/08/2018
 */

define( 'WP_TABLE_CUSTOM_COLUMN_TOGGLE_VERSION', '0.1.0' );

if ( class_exists( 'WP_Table_Custom_Column_Toggle' ) ) {
	return;
}

/**
 * Class that adds a toggle column to WP_Table or its siblings.
 */
class WP_Table_Custom_Column_Toggle {

	/**
	 * Porperties.
	 *
	 * @var array
	 */
	private $properties;

	/**
	 * Constructor
	 *
	 * @param array $properties Array with properties.
	 */
	private function __construct( array $properties ) {
		$this->properties = (object) wp_parse_args(
			$properties,
			[
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
	 * Static factory.
	 *
	 * @link https://carlalexander.ca/static-factory-method-pattern-wordpress/
	 *
	 * @param array $properties Array with properties.
	 * @return object
	 */
	public static function create( array $properties ) {
		return new self( $properties );
	}

	/**
	 * Add hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( $this->properties->column_hooks['header'], [ $this, 'filter_posts_columns' ] );
		add_action( $this->properties->column_hooks['content'], [ $this, 'page_column' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'admin_scripts' ] );
		if ( is_admin() ) {
			add_action( 'wp_ajax_' . $this->properties->column_id . '_update_option', [ $this, 'on_ajax_update_option' ] );
		}
	}


	/**
	 * Add column header to table
	 *
	 * @param array $columns Array with WP_Table coloumns.
	 * @return array
	 */
	public function filter_posts_columns( array $columns ) {
		$columns[ $this->properties->column_id ] = sprintf( '<span>%1$s</span>', $this->properties->column_name );
		return $columns;
	}

	/**
	 * Add a checkbox to custom column row.
	 *
	 * @param string $column column id.
	 * @param int    $post_id Podt ID.
	 * @return void
	 */
	public function page_column( string $column, int $post_id ) {
		if ( $this->properties->column_id === $column ) {

			$toggle        = $this->get_value( $post_id, '0' );
			$input_checked = ( '1' === $toggle ) ? 'checked' : '';
			$togle_to      = ( '1' === $toggle ) ? '0' : '1';
			printf(
				'<input id="%1$s_%2$s" class="custom-tgl tgl-flat" type="checkbox" %3$s data-handle="%1$s" data-changeto="%4$s" data-dataid="%2$s" /><label class="tgl-btn" for="%1$s_%2$s"></label>',
				esc_attr( $this->properties->column_id ),
				esc_attr( $post_id ),
				esc_attr( $input_checked ),
				esc_attr( $togle_to )
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
		// multisite fix, use home_url() if domain mapped to avoid cross-domain issues.
		if ( is_multisite() && home_url() !== site_url() ) {
			$ajaxurl = home_url( '/wp-admin/admin-ajax.php', $http_scheme );
		} else {
			$ajaxurl = site_url( '/wp-admin/admin-ajax.php', $http_scheme );
		}

		$url = str_replace( esc_url( $_SERVER['DOCUMENT_ROOT'] ), '', __DIR__ );

		wp_enqueue_script( 'wp-table-custom-column-toggle', $url . '/js/custom-column-toggle.js', [], WP_TABLE_CUSTOM_COLUMN_TOGGLE_VERSION, true );
		wp_enqueue_style( 'wp-table-custom-column-toggle', $url . '/css/custom-column-toggle.css', [], WP_TABLE_CUSTOM_COLUMN_TOGGLE_VERSION );
		wp_localize_script(
			'wp-table-custom-column-toggle',
			$this->properties->column_id,
			[
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
				echo wp_json_encode( $response );
				die();
			}
			if ( isset( $change_to ) ) {

				if ( ! $this->properties->use_siteoptions ) {
					update_post_meta( $data_id, $this->properties->meta_key, $change_to );
				}
				$this->change_value( $data_id, $change_to );
				if ( '1' === $change_to ) {
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

		echo wp_json_encode( $response );
		die();
	}

	/**
	 * Get array with option values.
	 *
	 * @return array
	 */
	public function get_values() {
		$values = $this->get( $this->properties->column_id );

		if ( false !== $values ) {
			return array_keys( array_intersect( $values, [ 1 ] ) );
		} else {
			return [];
		}
	}

	/**
	 * Change the toggle status (on / off).
	 *
	 * @param int    $id Site ID.
	 * @param string $status  'vissible' or 'hidden'.
	 * @return void
	 */
	private function change_value( int $id, string $value ) {
		$record = $this->get( $this->properties->column_id );
		if ( false !== $record ) {
			$record[ $id ] = $value;
			$this->update( $this->properties->column_id, $record );
		} else {
			$this->update( $this->properties->column_id, [ $id => $value ] );
		}
	}

	/**
	 * Get option value.
	 *
	 * @param string $id Name of option.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	public function get_value( $id, $default = false ) {
		$record = $this->get( $this->properties->column_id );
		if ( false !== $record && isset( $record[ $id ] ) ) {
			$get_value = $record[ $id ];
		} else {
			$get_value = $default;
		}
		return $get_value;
	}

	/**
	 * Get site option or option.
	 *
	 * @param string $id Name of option.
	 *
	 * @return mixed
	 */
	private function get( string $id ) {
		if ( $this->properties->use_siteoptions ) {
			$get = get_site_option( $id, false );
		} else {
			$get = get_option( $id, false );
		}
		return $get;
	}

	/**
	 * Update site option or option
	 *
	 * @param string $id Name of option.
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	private function update( string $id, $value ) {
		if ( $this->properties->use_siteoptions ) {
			$update = update_site_option( $id, $value );
		} else {
			$update = update_option( $id, $value );
		}
		return $update;
	}
}
