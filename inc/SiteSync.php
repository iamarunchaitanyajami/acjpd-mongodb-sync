<?php
/**
 * Any Site and Site Meta Sync to Mongodb.
 *
 * @package    acjpd-mongodb-sync
 * @subpackage WordPress
 *
 * @since      1.2.0
 */

namespace Acjpd\Mongodb;

/**
 * Site & Site options Storage class.
 */
class SiteSync extends Connector {

	/**
	 * Blog sync.
	 *
	 * @var array
	 */
	public array $blog = array();

	/**
	 * Delete Blog.
	 *
	 * @var array
	 */
	public array $delete_blog = array();

	/**
	 * Site Meta info.
	 *
	 * @var array
	 */
	public array $site_options = array();

	/**
	 * Site Meta to delete.
	 *
	 * @var array
	 */
	public array $delete_site_options = array();

	/**
	 * Init all actions.
	 *
	 * @return void
	 */
	public function init(): void {

		/**
		 * Blog Actions.
		 */
		add_action( 'wp_initialize_site', array( $this, 'add_site' ), 10 );
		add_action( 'wp_update_site', array( $this, 'add_site' ), 10 );
		add_action( 'wp_delete_site', array( $this, 'delete_site' ), 10 );

		/**
		 * Site meta Actions.
		 */
		add_action( 'add_site_option', array( $this, 'add_site_option' ), 10, 3 );
		add_action( 'update_site_option', array( $this, 'update_site_option' ), 10, 4 );
		add_action( 'delete_site_option', array( $this, 'delete_site_option' ), 10, 2 );

		/**
		 * Shutdown hook.
		 */
		add_action( 'shutdown', array( $this, 'process_sync' ) );
	}

	/**
	 * Add Sync site info.
	 *
	 * @param \WP_Site $new_site New site data.
	 *
	 * @return void
	 */
	public function add_site( \WP_Site $new_site ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$this->blog[] = $new_site;
	}

	/**
	 * Delete site info.
	 *
	 * @param \WP_Site $old_site Old site data.
	 *
	 * @return void
	 */
	public function delete_site( \WP_Site $old_site ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$this->delete_blog[] = $old_site;
	}

	/**
	 * Add site meta.
	 *
	 * @param string $option     Site option.
	 * @param mixed  $value      Value of the site option.
	 * @param int    $network_id Blog ID.
	 *
	 * @return void
	 */
	public function add_site_option( string $option, mixed $value, int $network_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( empty( $option ) || empty( $value ) || empty( $network_id ) ) {
			return;
		}

		$this->site_options[] = array(
			'site_id'    => $network_id,
			'meta_key'   => $option,
			'meta_value' => $value, //phpcs:ignore
		);
	}

	/**
	 * Update site meta.
	 *
	 * @param string $option     Site option.
	 * @param mixed  $value      Value of the site option.
	 * @param mixed  $old_value  Old Value of the site option.
	 * @param int    $network_id Blog ID.
	 *
	 * @return void
	 */
	public function update_site_option( string $option, mixed $value, mixed $old_value, int $network_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( empty( $option ) || empty( $value ) || empty( $network_id ) ) {
			return;
		}

		$this->add_site_option( $option, $value, $network_id );
	}

	/**
	 * Delete site meta.
	 *
	 * @param string $option     Site option.
	 * @param int    $network_id Blog ID.
	 *
	 * @return void
	 */
	public function delete_site_option( string $option, int $network_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$this->delete_site_options[] = array(
			'site_id'  => $network_id,
			'meta_key' => $option,
		);
	}

	/**
	 * Sync process to Mongo db.
	 *
	 * @return void
	 */
	public function process_sync(): void {

		/**
		 * Add blog info.
		 */
		if ( ! empty( $this->blog ) ) {
			$blog_collection = $this->get_blog_collection();
			foreach ( $this->blog as $blog ) {
				$blog_collection->updateOne(
					array(
						'blog_id' => $blog->blog_id,
						'site_id' => $blog->site_id,
					),
					array( '$set' => $blog ),
					array( 'upsert' => true )
				);
			}
		}

		/**
		 * Delete blog.
		 */
		if ( ! empty( $this->delete_blog ) ) {
			$blog_collection = $this->get_blog_collection();
			foreach ( $this->delete_blog as $old_blog ) {
				$blog_collection->deleteOne(
					array(
						'blog_id' => $old_blog->blog_id,
						'site_id' => $old_blog->site_id,
					),
				);
			}
		}

		/**
		 * Process Site meta options.
		 */
		if ( ! empty( $this->site_options ) ) {
			$site_options_collection = $this->get_site_meta_collection();
			foreach ( $this->site_options as $site_meta ) {
				$site_options_collection->updateOne(
					array(
						'site_id'  => $site_meta['site_id'],
						'meta_key' => $site_meta['meta_key'],
					),
					array( '$set' => $site_meta ),
					array( 'upsert' => true )
				);
			}
		}

		/**
		 * Delete Site meta options.
		 */
		if ( ! empty( $this->delete_site_options ) ) {
			$site_options_collection = $this->get_site_meta_collection();
			foreach ( $this->delete_site_options as $options_delete ) {
				$site_options_collection->deleteMany( $options_delete );
			}
		}
	}
}
