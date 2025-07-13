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
    class User_Migration_CLI{

        /**
         * Export users with metadata, roles, and password hashes to CSV.
         *
         * ## OPTIONS
         *
         * --file=<file>
         * : The output CSV file path.
         */
        public function export( $args, $assoc_args ) {
            $file = $assoc_args['file'];
            $users = get_users();
            $headers = ['user_login', 'user_email', 'user_pass', 'display_name', 'roles', 'capabilities'];

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

        /**
         * Import users from CSV.
         *
         * ## OPTIONS
         *
         * --file=<file>
         * : The input CSV file path.
         *
         * [--dry-run]
         * : Show what would happen, without making changes.
         */
        public function import( $args, $assoc_args ) {
            $file = $assoc_args['file'];
            $dry_run = isset( $assoc_args['dry-run'] );

            if ( ! file_exists( $file ) ) {
                WP_CLI::error( "File $file not found." );
            }

            $handle = fopen( $file, 'r' );
            $headers = fgetcsv( $handle );

            $imported = 0;
            $skipped = 0;

            while ( $row = fgetcsv( $handle ) ) {
                $data = array_combine( $headers, $row );

                if ( username_exists( $data['user_login'] ) || email_exists( $data['user_email'] ) ) {
                    WP_CLI::log( "🔁 Skipped (exists): {$data['user_login']}" );
                    $skipped++;
                    continue;
                }

                if ( $dry_run ) {
                    WP_CLI::log( "✅ DRY RUN: Would import user: {$data['user_login']}" );
                    $imported++;
                    continue;
                }

                // Create user with hashed password
                $user_id = wp_insert_user( [
                    'user_login'    => $data['user_login'],
                    'user_email'    => $data['user_email'],
                    'user_pass'     => wp_generate_password( 32 ), // Temporary
                    'display_name'  => $data['display_name'],
                ] );

                if ( is_wp_error( $user_id ) ) {
                    WP_CLI::warning( "❌ Failed to import {$data['user_login']} - " . $user_id->get_error_message() );
                    continue;
                }

                // Overwrite hashed password directly
                global $wpdb;
                $wpdb->update( $wpdb->users, [ 'user_pass' => $data['user_pass'] ], [ 'ID' => $user_id ] );

                // Set role
                $roles = explode( ',', $data['roles'] );
                $user = new WP_User( $user_id );
                foreach ( $roles as $role ) {
                    if ( $role ) {
                        $user->add_role( trim( $role ) );
                    }
                }

                // Set capabilities if needed
                if ( isset( $data['capabilities'] ) ) {
                    update_user_meta( $user_id, 'wp_capabilities', maybe_unserialize( $data['capabilities'] ) );
                }

                // Add user meta
                foreach ( $headers as $key ) {
                    if ( in_array( $key, ['user_login','user_email','user_pass','display_name','roles','capabilities'] ) ) continue;
                    $val = maybe_unserialize( $data[ $key ] );
                    update_user_meta( $user_id, $key, $val );
                }

                WP_CLI::log( "✅ Imported: {$data['user_login']}" );
                $imported++;
            }

            fclose( $handle );
            WP_CLI::success( "$imported users imported. $skipped skipped." );
        }
    }

    WP_CLI::add_command( 'user-migration', 'User_Migration_CLI' );
}// END class WPGenius_Migration_CLI