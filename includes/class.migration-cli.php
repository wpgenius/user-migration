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

        /**
         * Export users with metadata, roles, and password hashes to CSV.
         *
         * ## OPTIONS
         *
         * --file=<file>
         * : The output CSV file path.
         *
         * [--roles=<roles>]
         * : Comma-separated roles to export (e.g., "subscriber,customer").
         *
         * ## EXAMPLES
         *
         *     wp user-migration export --file=users.csv
         *     wp user-migration export --file=customers.csv --roles=subscriber,customer
         *
         * @when after_wp_load
         */
        public function export( $args, $assoc_args ) {
            $file = $assoc_args['file'];
            $filter_roles = isset( $assoc_args['roles'] ) ? explode( ',', $assoc_args['roles'] ) : [];

            $args = [
                'orderby' => 'ID',
                'order'   => 'ASC',
                'number'  => -1,
            ];

            if ( $filter_roles ) {
                $args['role__in'] = $filter_roles;
            }

            $users = get_users( $args );

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

            $total = count( $users );
            $counter = 0;

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
                    $val = get_user_meta( $user->ID, $key, true );
                    $row[] = is_array( $val ) ? maybe_serialize( $val ) : $val;
                }

                fputcsv( $fp, $row );
                $counter++;
                echo "\rExporting: $counter of $total";
            }

            fclose( $fp );
            WP_CLI::line(""); // Clear progress line
            WP_CLI::success( "$total users exported to $file" );
        }

        /**
         * Import users from CSV with metadata and roles.
         *
         * ## OPTIONS
         *
         * --file=<file>
         * : The input CSV file path.
         *
         * [--roles=<roles>]
         * : Comma-separated roles to filter and import.
         *
         * [--dry-run]
         * : Perform a dry run without actual import.
         *
         * ## EXAMPLES
         *
         *     wp user-migration import --file=users.csv --dry-run
         *     wp user-migration import --file=users.csv
         *     wp user-migration import --file=users.csv --roles=subscriber,customer --dry-run
         *
         * @when after_wp_load
         */
        public function import( $args, $assoc_args ) {
            $file = $assoc_args['file'];
            $dry_run = isset( $assoc_args['dry-run'] );
            $filter_roles = isset( $assoc_args['roles'] ) ? explode( ',', $assoc_args['roles'] ) : [];

            if ( ! file_exists( $file ) ) {
                WP_CLI::error( "File $file not found." );
            }

            $handle = fopen( $file, 'r' );
            $headers = fgetcsv( $handle );
            $rows = [];

            while ( $row = fgetcsv( $handle ) ) {
                $rows[] = array_combine( $headers, $row );
            }

            fclose( $handle );

            $imported = 0;
            $skipped = 0;
            $conflicts = [];
            $total = count( $rows );
            $counter = 0;

            foreach ( $rows as $data ) {
                $counter++;

                $user_roles = explode( ',', $data['roles'] );
                if ( $filter_roles && ! array_intersect( $filter_roles, $user_roles ) ) {
                    continue;
                }

                $existing_user = get_user_by( 'id', $data['ID'] );
                if ( $existing_user ) {
                    $conflicts[] = $data;
                    continue;
                }

                if ( $dry_run ) {
                    WP_CLI::log( "DRY RUN: Would import user ID {$data['ID']} - {$data['user_login']}" );
                    $imported++;
                    continue;
                }

                $user_id = wp_insert_user( [
                    'ID'            => $data['ID'],
                    'user_login'    => $data['user_login'],
                    'user_email'    => $data['user_email'],
                    'user_pass'     => wp_generate_password( 32 ),
                    'display_name'  => $data['display_name'],
                ] );

                if ( is_wp_error( $user_id ) ) {
                    WP_CLI::warning( "Failed to import {$data['user_login']} - " . $user_id->get_error_message() );
                    $skipped++;
                    continue;
                }

                global $wpdb;
                $wpdb->update( $wpdb->users, [ 'user_pass' => $data['user_pass'] ], [ 'ID' => $user_id ] );

                $user = new WP_User( $user_id );
                foreach ( $user_roles as $role ) {
                    if ( $role ) {
                        $user->add_role( trim( $role ) );
                    }
                }

                if ( isset( $data['capabilities'] ) ) {
                    update_user_meta( $user_id, 'wp_capabilities', maybe_unserialize( $data['capabilities'] ) );
                }

                foreach ( $headers as $key ) {
                    if ( in_array( $key, ['ID', 'user_login','user_email','user_pass','display_name','roles','capabilities'] ) ) {
                        continue;
                    }
                    $val = maybe_unserialize( $data[ $key ] );
                    update_user_meta( $user_id, $key, $val );
                }

                echo "\rImported: $counter of $total";
                $imported++;
            }

            WP_CLI::line(""); // Clean progress line
            WP_CLI::success( "$imported users imported. $skipped skipped. " . count( $conflicts ) . " conflicts." );

            if ( ! empty( $conflicts ) && ! $dry_run ) {
                $conflict_file = dirname( $file ) . '/conflicts.csv';
                $fp = fopen( $conflict_file, 'w' );
                fputcsv( $fp, $headers );
                foreach ( $conflicts as $row ) {
                    fputcsv( $fp, $row );
                }
                fclose( $fp );

                WP_CLI::confirm( "Importing conflicted users from conflicts.csv. Continue?" );
                $assoc_args['file'] = $conflict_file;
                unset( $assoc_args['dry-run'] );
                $this->import( $args, $assoc_args );
            }
        }
    }

    WP_CLI::add_command( 'user-migration', 'User_Migration_CLI' );
}
// END class WPGenius_Migration_CLI