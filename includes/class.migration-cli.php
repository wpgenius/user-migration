<?php
/**
 *
 * @class       WPGenius_Migration_CLI
 * @author      Team WPGenius (Makarand Mane)
 * @category    Admin
 * @package     user-migration/includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    class User_Migration_CLI {

        public function export( $args, $assoc_args ) {
            $file = $assoc_args['file'];
            $users = get_users();
            $headers = ['ID', 'user_login', 'user_email', 'user_pass', 'display_name', 'roles', 'capabilities'];
            $meta_keys = [];

            foreach ( $users as $user ) {
                $meta = get_user_meta( $user->ID );
                $meta_keys = array_merge( $meta_keys, array_keys( $meta ) );
            }

            $meta_keys = array_unique( $meta_keys );
            $headers = array_merge( $headers, $meta_keys );

            $fp = fopen( $file, 'w' );
            fputcsv( $fp, $headers );

            foreach ( $users as $user ) {
                $row = [
                    $user->ID,
                    $user->user_login,
                    $user->user_email,
                    $user->user_pass,
                    $user->display_name,
                    implode( ',', $user->roles ),
                    maybe_serialize( get_user_meta( $user->ID, 'wp_capabilities', true ) ),
                ];

                foreach ( $meta_keys as $key ) {
                    $meta_val = get_user_meta( $user->ID, $key, true );
                    $row[] = is_array( $meta_val ) ? maybe_serialize( $meta_val ) : $meta_val;
                }

                fputcsv( $fp, $row );
            }

            fclose( $fp );
            WP_CLI::success( count( $users ) . " users exported to $file" );
        }

        public function import( $args, $assoc_args ) {
            $file = $assoc_args['file'];
            $dry_run = isset( $assoc_args['dry-run'] );
            $conflict_file = dirname( $file ) . '/conflicts.csv';

            if ( ! file_exists( $file ) ) {
                WP_CLI::error( "File $file not found." );
            }

            $handle = fopen( $file, 'r' );
            $headers = fgetcsv( $handle );

            $conflict_fp = fopen( $conflict_file, 'w' );
            fputcsv( $conflict_fp, $headers );

            $imported = 0;
            $conflicted = 0;

            global $wpdb;

            while ( $row = fgetcsv( $handle ) ) {
                $data = array_combine( $headers, $row );
                $user_id = (int) $data['ID'];

                $user_exists = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID = %d", $user_id ) );

                if ( $user_exists ) {
                    fputcsv( $conflict_fp, $row );
                    WP_CLI::log( "Skipped (ID exists): {$data['user_login']} (ID: $user_id)" );
                    $conflicted++;
                    continue;
                }

                if ( $dry_run ) {
                    WP_CLI::log( "DRY RUN: Would import user {$data['user_login']} with ID {$user_id}" );
                    $imported++;
                    continue;
                }

                // Manually insert into wp_users with ID
                $result = $wpdb->insert(
                    $wpdb->users,
                    [
                        'ID'           => $user_id,
                        'user_login'   => $data['user_login'],
                        'user_pass'    => $data['user_pass'],
                        'user_email'   => $data['user_email'],
                        'display_name' => $data['display_name'],
                        'user_registered' => current_time( 'mysql' ),
                    ]
                );

                if ( $result === false ) {
                    WP_CLI::warning( "Failed to insert user {$data['user_login']}" );
                    continue;
                }

                // Set roles
                $roles = explode( ',', $data['roles'] );
                $user = new WP_User( $user_id );
                foreach ( $roles as $role ) {
                    if ( $role ) {
                        $user->add_role( trim( $role ) );
                    }
                }

                // Set capabilities
                if ( isset( $data['capabilities'] ) ) {
                    update_user_meta( $user_id, 'wp_capabilities', maybe_unserialize( $data['capabilities'] ) );
                }

                // Add user meta
                foreach ( $headers as $key ) {
                    if ( in_array( $key, ['ID','user_login','user_email','user_pass','display_name','roles','capabilities'] ) ) continue;
                    $val = maybe_unserialize( $data[ $key ] );
                    update_user_meta( $user_id, $key, $val );
                }

                WP_CLI::log( "Imported: {$data['user_login']} (ID: $user_id)" );
                $imported++;
            }

            fclose( $handle );
            fclose( $conflict_fp );

            WP_CLI::success( "$imported users imported. $conflicted skipped due to ID conflict (see $conflict_file)." );
        }
    }

    WP_CLI::add_command( 'user-migration', 'User_Migration_CLI' );
}
// END class WPGenius_Migration_CLI