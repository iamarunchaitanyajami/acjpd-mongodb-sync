<?php
/**
 * All Users Sync to Mongodb.
 *
 * @package    acjpd-mongodb-sync
 * @subpackage WordPress
 *
 * @since      1.0.6
 */

namespace Acjpd\Mongodb;

/**
 * User Sync class.
 */
class UserSync extends Connector {

	/**
	 * User info.
	 *
	 * @var array
	 */
	public array $users = array();

	/**
	 * User meta info.
	 *
	 * @var array
	 */
	public array $users_meta = array();

	/**
	 * User delete.
	 *
	 * @var array
	 */
	public array $users_delete = array();

	/**
	 * User meta info delete.
	 *
	 * @var array
	 */
	public array $users_meta_delete = array();

	/**
	 * User meta to create/update.
	 *
	 * @var array
	 */
	public array $user_meta = array();

	/**
	 * Class inti.
	 *
	 * @return void
	 */
	public function init(): void {

		/**
		 * User register.
		 */
		add_action( 'user_register', array( $this, 'sync_user' ), 10, 2 );

		/**
		 * Update profile.
		 */
		add_action( 'profile_update', array( $this, 'sync_updated_user' ), 10, 3 );

		/**
		 * Delete User.
		 */
		add_action( 'deleted_user', array( $this, 'sync_delete_user' ) );

		/**
		 * User meta info.
		 */
		add_action( 'updated_user_meta', array( $this, 'sync_user_meta' ), 10, 4 );
		add_action( 'added_user_meta', array( $this, 'sync_user_meta' ), 10, 4 );
		add_action( 'deleted_user_meta', array( $this, 'sync_delete_user_meta' ), 10, 4 );

		/**
		 * Process sync.
		 */
		add_action( 'shutdown', array( $this, 'process_sync' ) );
	}

	/**
	 * Sync user data.
	 *
	 * @param int   $user_id  User Id.
	 * @param array $userdata User information.
	 *
	 * @return void
	 */
	public function sync_user( int $user_id, array $userdata ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( ! empty( $userdata['ID'] ) ) {
			$site_id                   = get_current_blog_id();
			$this->users[ $site_id ][] = $userdata;
		}
	}

	/**
	 * Update user.
	 *
	 * @param int      $user_id       User Id.
	 * @param \WP_User $old_user_data Old user info.
	 * @param array    $userdata      Updated user info.
	 *
	 * @return void
	 */
	public function sync_updated_user( int $user_id, \WP_User $old_user_data, array $userdata ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$this->sync_user( $user_id, $userdata );
	}

	/**
	 * Remove user info.
	 *
	 * @param int $user_id User id.
	 *
	 * @return void
	 */
	public function sync_delete_user( int $user_id ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$site_id                          = get_current_blog_id();
		$this->users_delete[ $site_id ][] = $user_id;
	}

	/**
	 * Syc user meta.
	 *
	 * @param int    $meta_id     Meta Id.
	 * @param int    $object_id   User id.
	 * @param string $meta_key    Meta Key.
	 * @param mixed  $_meta_value Meta Value.
	 *
	 * @return void
	 */
	public function sync_user_meta( int $meta_id, int $object_id, string $meta_key, mixed $_meta_value ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$site_id                        = get_current_blog_id();
		$this->users_meta[ $site_id ][] = array( //phpcs:ignore
			'umeta_id'   => $meta_id,
			'user_id'    => $object_id,
			'meta_key'   => $meta_key,
			'meta_value' => $_meta_value, //phpcs:ignore
		);
	}

	/**
	 * Remove User meta.
	 *
	 * @param mixed  $meta_ids    Meta I`ds.
	 * @param int    $object_id   User I`ds.
	 * @param string $meta_key    Meta Key.
	 * @param mixed  $_meta_value Meta Value.
	 *
	 * @return void
	 */
	public function sync_delete_user_meta( mixed $meta_ids, int $object_id, string $meta_key, mixed $_meta_value ): void {
		if ( in_array( $meta_key, $this->exclude_meta_keys, true ) ) {
			return;
		}

		$site_id                               = get_current_blog_id();
		$this->users_meta_delete[ $site_id ][] = array(
			'user_id'  => $object_id,
			'meta_key' => $meta_key,
		);
	}

	/**
	 * Sync process to Mongo db.
	 *
	 * @return void
	 */
	public function process_sync(): void {
		/**
		 * Process user queue.
		 */
		if ( ! empty( $this->users ) ) {
			$user_collection = $this->get_user_collection();
			foreach ( $this->users as $site_id => $user_info ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $user_info as $mdb_user ) {
					if ( empty( $mdb_user['ID'] ) ) {
						continue;
					}

					$update = $user_collection->updateOne(
						array( 'ID' => $mdb_user['ID'] ),
						array( '$set' => $mdb_user ),
						array( 'upsert' => true )
					);

					$upserted_id = $update->getUpsertedId();
					if ( ! empty( $upserted_id ) ) {
						update_user_meta( $mdb_user->ID, 'acjpd_mongodb_sync_inserted_id', $upserted_id );
					}
				}

				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}

		/**
		 * Process delete queue.
		 */
		if ( ! empty( $this->users_delete ) ) {
			$post_delete_collection = $this->get_user_collection();
			foreach ( $this->users_delete as $site_id => $delete_users ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $delete_users as $delete_user ) {
					$post_delete_collection->deleteOne( array( 'ID' => $delete_user ) );
				}

				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}

		/**
		 * Update/Add Meta.
		 */
		if ( ! empty( $this->users_meta ) ) {
			$user_meta_collection = $this->get_user_meta_collection();
			foreach ( $this->users_meta as $site_id => $users_meta ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $users_meta as $user_meta ) {
					$user_meta_collection->updateOne(
						array( 'umeta_id' => $user_meta['umeta_id'] ),
						array( '$set' => $user_meta ),
						array( 'upsert' => true )
					);
				}

				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}

		/**
		 * Delete User meta.
		 */
		if ( ! empty( $this->users_meta_delete ) ) {
			$delete_user_meta_collection = $this->get_user_meta_collection();
			foreach ( $this->users_meta_delete as $site_id => $delete_users_meta ) {
				if ( $this->is_multi_blog ) {
					\switch_to_blog( $site_id );
				}

				foreach ( $delete_users_meta as $delete_user_meta ) {
					$delete_user_meta_collection->deleteMany( $delete_user_meta );
				}

				if ( $this->is_multi_blog ) {
					\restore_current_blog();
				}
			}
		}
	}
}
