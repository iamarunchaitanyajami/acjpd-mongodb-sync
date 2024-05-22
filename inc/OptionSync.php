<?php
/**
 * All options Sync to Mongodb.
 *
 * @package acj-mongodb-clone
 * @subpackage WordPress
 */

namespace Acj\Mongodb;

/**
 * Options Page fix.
 */
class OptionSync extends Connector {

	/**
	 * Options create/update.
	 *
	 * @var array
	 */
	public array $options = array();

	/**
	 * Options delete.
	 *
	 * @var array
	 */
	public array $options_delete = array();

	/**
	 * Class inti.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'added_option', array( $this, 'sync_added_option' ), 10, 2 );
		add_action( 'updated_option', array( $this, 'sync_updated_option' ), 10, 3 );
		add_action( 'deleted_option', array( $this, 'sync_delete_option' ), 10, 1 );

		add_action( 'shutdown', array( $this, 'process_sync' ) );
	}

	/**
	 * Add options.
	 *
	 * @param string $option Name of the option to add.
	 * @param mixed  $value Optional. Option value. Must be serializable if non-scalar.
	 *
	 * @return void
	 */
	public function sync_added_option( string $option, mixed $value ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$site_id                     = get_current_blog_id();
		$this->options[ $site_id ][] = array(
			'key'   => $option,
			'value' => $value, // phpcs:ignore
		);
	}

	/**
	 * Update options.
	 *
	 * @param string $option Name of the option to add.
	 * @param mixed  $old_value Old value.
	 * @param mixed  $value Optional. Option value. Must be serializable if non-scalar.
	 *
	 * @return void
	 */
	public function sync_updated_option( string $option, mixed $old_value, mixed $value ): void {
		$this->sync_added_option( $option, $value );
	}

	/**
	 * Delete options.
	 *
	 * @param string $option Name of the option to add.
	 *
	 * @return void
	 */
	public function sync_delete_option( string $option ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$site_id                            = get_current_blog_id();
		$this->options_delete[ $site_id ][] = array(
			'key'   => $option,
			'value' => '', // phpcs:ignore
		);
	}

	/**
	 * Sync process to Mongo db.
	 *
	 * @return void
	 */
	public function process_sync(): void {
		/**
		 * Process options queue.
		 */
		if ( ! empty( $this->options ) ) {
			$options_collection = $this->get_option_collection();
			foreach ( $this->options as $site_id => $options ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $options as $option ) {
					$options_collection->updateOne(
						array(
							'option_name' => $option['key'],
						),
						array( '$set' => $option ),
						array( 'upsert' => true )
					);
				}

				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}

		/**
		 * Delete option.
		 */
		if ( ! empty( $this->options_delete ) ) {
			$options_collection = $this->get_option_collection();
			foreach ( $this->options_delete as $site_id => $options_delete ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $options_delete as $option_delete ) {
					$options_collection->deleteMany( $option_delete );
				}

				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}
	}
}
