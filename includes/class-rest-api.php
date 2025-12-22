<?php
/**
 * REST API for Feature Requests
 * 
 * Hybrid Voting System (IP + Cookie UUID) - Paired Format
 * 
 * Voters array format: ["192.168.1.1|abc-123", "10.0.0.5|def-456"]
 * Each entry = 1 voter (IP|UUID pair)
 * 
 * Vote check: Match if IP matches OR UUID matches
 * Unvote: Remove the matching entry
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JE_Request_REST_API {

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        $namespace = 'je-request/v1';

        // Get all requests
        register_rest_route( $namespace, '/requests', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_requests' ),
            'permission_callback' => '__return_true',
        ) );

        // Submit new request
        register_rest_route( $namespace, '/requests', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'submit_request' ),
            'permission_callback' => '__return_true',
        ) );

        // Vote on request
        register_rest_route( $namespace, '/requests/(?P<id>\d+)/vote', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'vote_request' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Get all requests
     */
    public static function get_requests( $request ) {
        $args = array(
            'post_type'      => 'je_feature_request',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_je_request_votes',
            'order'          => 'DESC',
        );

        // Filter by status
        $status = $request->get_param( 'status' );
        if ( $status && $status !== 'all' ) {
            $args['meta_query'] = array(
                array(
                    'key'   => '_je_request_status',
                    'value' => sanitize_text_field( $status ),
                ),
            );
        }

        // Sort option
        $sort = $request->get_param( 'sort' );
        if ( $sort === 'date' ) {
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
            unset( $args['meta_key'] );
        }

        // Get voter_uuid from header for has_voted check
        $voter_uuid = $request->get_header( 'X-Voter-UUID' );
        $voter_uuid = $voter_uuid ? sanitize_text_field( $voter_uuid ) : '';

        $posts = get_posts( $args );
        $data  = array();

        foreach ( $posts as $post ) {
            $data[] = self::format_request( $post, $voter_uuid );
        }

        return rest_ensure_response( $data );
    }

    /**
     * Submit new request
     */
    public static function submit_request( $request ) {
        $title       = sanitize_text_field( $request->get_param( 'title' ) );
        $description = sanitize_textarea_field( $request->get_param( 'description' ) );
        $email       = sanitize_email( $request->get_param( 'email' ) );
        $voter_uuid  = sanitize_text_field( $request->get_param( 'voter_uuid' ) );

        if ( empty( $title ) ) {
            return new WP_Error( 'missing_title', __( 'Title is required.', 'je-request-block' ), array( 'status' => 400 ) );
        }

        if ( strlen( $title ) < 5 ) {
            return new WP_Error( 'title_too_short', __( 'Title must be at least 5 characters.', 'je-request-block' ), array( 'status' => 400 ) );
        }

        // Rate limiting: Check if same IP submitted recently
        $ip = self::get_client_ip();
        $recent = get_posts( array(
            'post_type'      => 'je_feature_request',
            'post_status'    => 'any',
            'posts_per_page' => 1,
            'date_query'     => array(
                'after' => '5 minutes ago',
            ),
            'meta_query'     => array(
                array(
                    'key'   => '_je_request_submitter_ip',
                    'value' => $ip,
                ),
            ),
        ) );

        if ( ! empty( $recent ) ) {
            return new WP_Error( 'rate_limited', __( 'Please wait a few minutes before submitting another request.', 'je-request-block' ), array( 'status' => 429 ) );
        }

        // Create post
        $post_id = wp_insert_post( array(
            'post_type'    => 'je_feature_request',
            'post_title'   => $title,
            'post_content' => $description,
            'post_status'  => 'publish',
        ) );

        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        // Auto-vote: store "IP|UUID" paired format
        $voters = array();
        $voters[] = $ip . '|' . $voter_uuid;

        update_post_meta( $post_id, '_je_request_votes', 1 );
        update_post_meta( $post_id, '_je_request_status', 'pending' );
        update_post_meta( $post_id, '_je_request_voters', $voters );
        update_post_meta( $post_id, '_je_request_submitter_ip', $ip );

        if ( $email ) {
            update_post_meta( $post_id, '_je_request_email', $email );
        }

        $post = get_post( $post_id );

        return rest_ensure_response( array(
            'success' => true,
            'request' => self::format_request( $post, $voter_uuid ),
        ) );
    }

    /**
     * Vote on request (toggle vote/unvote)
     */
    public static function vote_request( $request ) {
        $post_id    = absint( $request->get_param( 'id' ) );
        $voter_uuid = sanitize_text_field( $request->get_param( 'voter_uuid' ) );
        $post       = get_post( $post_id );

        if ( ! $post || $post->post_type !== 'je_feature_request' ) {
            return new WP_Error( 'not_found', __( 'Request not found.', 'je-request-block' ), array( 'status' => 404 ) );
        }

        $ip     = self::get_client_ip();
        $voters = get_post_meta( $post_id, '_je_request_voters', true );

        if ( ! is_array( $voters ) ) {
            $voters = array();
        }

        // Find matching entry (IP or UUID match)
        $matched_index = self::find_voter_index( $voters, $ip, $voter_uuid );
        $has_voted = $matched_index !== false;

        if ( $has_voted ) {
            // Unvote: remove the matched entry
            array_splice( $voters, $matched_index, 1 );

            $votes = count( $voters );

            update_post_meta( $post_id, '_je_request_voters', $voters );
            update_post_meta( $post_id, '_je_request_votes', $votes );

            return rest_ensure_response( array(
                'success'  => true,
                'action'   => 'unvoted',
                'votes'    => $votes,
                'hasVoted' => false,
            ) );
        }

        // Vote: add new entry "IP|UUID"
        $voters[] = $ip . '|' . $voter_uuid;

        $votes = count( $voters );

        update_post_meta( $post_id, '_je_request_voters', $voters );
        update_post_meta( $post_id, '_je_request_votes', $votes );

        return rest_ensure_response( array(
            'success'  => true,
            'action'   => 'voted',
            'votes'    => $votes,
            'hasVoted' => true,
        ) );
    }

    /**
     * Find voter index by IP or UUID match
     * 
     * @param array  $voters Array of "IP|UUID" entries
     * @param string $ip     Current user IP
     * @param string $uuid   Current user UUID (may be empty)
     * @return int|false     Index if found, false otherwise
     */
    private static function find_voter_index( $voters, $ip, $uuid ) {
        foreach ( $voters as $index => $voter ) {
            $parts = explode( '|', $voter, 2 );
            $stored_ip = isset( $parts[0] ) ? $parts[0] : '';
            $stored_uuid = isset( $parts[1] ) ? $parts[1] : '';

            // Match by IP
            if ( ! empty( $ip ) && $stored_ip === $ip ) {
                return $index;
            }

            // Match by UUID (only if both have UUID)
            if ( ! empty( $uuid ) && ! empty( $stored_uuid ) && $stored_uuid === $uuid ) {
                return $index;
            }
        }

        return false;
    }

    /**
     * Format request for response
     */
    private static function format_request( $post, $voter_uuid = '' ) {
        return array(
            'id'          => $post->ID,
            'title'       => $post->post_title,
            'description' => $post->post_content,
            'votes'       => (int) get_post_meta( $post->ID, '_je_request_votes', true ),
            'status'      => get_post_meta( $post->ID, '_je_request_status', true ) ?: 'pending',
            'date'        => $post->post_date,
            'hasVoted'    => self::has_voted( $post->ID, $voter_uuid ),
        );
    }

    /**
     * Check if current user has voted (Hybrid: IP or UUID match)
     * 
     * @param int    $post_id    Post ID
     * @param string $voter_uuid UUID from cookie (may be empty)
     * @return bool
     */
    private static function has_voted( $post_id, $voter_uuid = '' ) {
        $ip     = self::get_client_ip();
        $voters = get_post_meta( $post_id, '_je_request_voters', true );

        if ( ! is_array( $voters ) ) {
            return false;
        }

        return self::find_voter_index( $voters, $ip, $voter_uuid ) !== false;
    }

    /**
     * Get client IP address
     * 
     * @return string
     */
    private static function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            // Get first IP if multiple
            $ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
            $ip = trim( $ips[0] );
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field( $ip );
    }
}