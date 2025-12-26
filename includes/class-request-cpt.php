<?php
/**
 * Custom Post Type for Feature Requests
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JE_Request_CPT {

    /**
     * Register Custom Post Type
     */
    public static function register_post_type() {
        $labels = array(
            'name'               => __( 'Feature Requests', 'je-request-block' ),
            'singular_name'      => __( 'Feature Request', 'je-request-block' ),
            'menu_name'          => __( 'Feature Requests', 'je-request-block' ),
            'add_new'            => __( 'Add New', 'je-request-block' ),
            'add_new_item'       => __( 'Add New Request', 'je-request-block' ),
            'edit_item'          => __( 'Edit Request', 'je-request-block' ),
            'new_item'           => __( 'New Request', 'je-request-block' ),
            'view_item'          => __( 'View Request', 'je-request-block' ),
            'search_items'       => __( 'Search Requests', 'je-request-block' ),
            'not_found'          => __( 'No requests found', 'je-request-block' ),
            'not_found_in_trash' => __( 'No requests found in trash', 'je-request-block' ),
        );

        $args = array(
            'labels'              => $labels,
            'public'              => false,
            'publicly_queryable'  => false,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'query_var'           => false,
            'capability_type'     => 'post',
            'has_archive'         => false,
            'hierarchical'        => false,
            'menu_position'       => 25,
            'menu_icon'           => 'dashicons-lightbulb',
            'supports'            => array( 'title', 'editor' ),
            'show_in_rest'        => true,
        );

        register_post_type( 'je_feature_request', $args );

        // Add admin columns
        add_filter( 'manage_je_feature_request_posts_columns', array( __CLASS__, 'add_admin_columns' ) );
        add_action( 'manage_je_feature_request_posts_custom_column', array( __CLASS__, 'render_admin_columns' ), 10, 2 );
        add_filter( 'manage_edit-je_feature_request_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
        
        // Add meta box for status and email
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_je_feature_request', array( __CLASS__, 'save_meta_boxes' ) );
    }

    /**
     * Register Post Meta
     */
    public static function register_meta() {
        // Vote count
        register_post_meta( 'je_feature_request', '_je_request_votes', array(
            'type'              => 'integer',
            'single'            => true,
            'default'           => 0,
            'show_in_rest'      => true,
            'sanitize_callback' => 'absint',
        ) );

        // Status: pending, planned, in_progress, completed, rejected
        register_post_meta( 'je_feature_request', '_je_request_status', array(
            'type'              => 'string',
            'single'            => true,
            'default'           => 'pending',
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        // Voter IPs (to prevent duplicate votes)
        register_post_meta( 'je_feature_request', '_je_request_voters', array(
            'type'              => 'array',
            'single'            => true,
            'default'           => array(),
            'show_in_rest'      => array(
                'schema' => array(
                    'type'  => 'array',
                    'items' => array( 'type' => 'string' ),
                ),
            ),
        ) );

        // Submitter email (optional)
        register_post_meta( 'je_feature_request', '_je_request_email', array(
            'type'              => 'string',
            'single'            => true,
            'default'           => '',
            'show_in_rest'      => true,
            'sanitize_callback' => 'sanitize_email',
        ) );
    }

    /**
     * Add admin columns
     */
    public static function add_admin_columns( $columns ) {
        $new_columns = array();
        
        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;
            
            if ( $key === 'title' ) {
                $new_columns['votes']  = __( 'Votes', 'je-request-block' );
                $new_columns['status'] = __( 'Status', 'je-request-block' );
                $new_columns['email']  = __( 'Email', 'je-request-block' );
            }
        }
        
        return $new_columns;
    }

    /**
     * Render admin columns
     */
    public static function render_admin_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'votes':
                $votes = get_post_meta( $post_id, '_je_request_votes', true );
                echo '<strong>' . intval( $votes ) . '</strong>';
                break;
                
            case 'status':
                $status = get_post_meta( $post_id, '_je_request_status', true ) ?: 'pending';
                $labels = array(
                    'pending'     => __( 'Pending', 'je-request-block' ),
                    'planned'     => __( 'Planned', 'je-request-block' ),
                    'in_progress' => __( 'In Progress', 'je-request-block' ),
                    'completed'   => __( 'Completed', 'je-request-block' ),
                    'rejected'    => __( 'Rejected', 'je-request-block' ),
                );
                $colors = array(
                    'pending'     => '#f59e0b',
                    'planned'     => '#3b82f6',
                    'in_progress' => '#8b5cf6',
                    'completed'   => '#10b981',
                    'rejected'    => '#ef4444',
                );
                $label = isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
                $color = isset( $colors[ $status ] ) ? $colors[ $status ] : '#6b7280';
                echo '<span style="background:' . esc_attr( $color ) . '; color:white; padding:3px 8px; border-radius:3px; font-size:12px;">' . esc_html( $label ) . '</span>';
                break;
                
            case 'email':
                $email = get_post_meta( $post_id, '_je_request_email', true );
                if ( $email ) {
                    echo '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>';
                } else {
                    echo '<span style="color:#9ca3af;">—</span>';
                }
                break;
        }
    }

    /**
     * Sortable columns
     */
    public static function sortable_columns( $columns ) {
        $columns['votes'] = 'votes';
        return $columns;
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'je_request_details',
            __( 'Request Details', 'je-request-block' ),
            array( __CLASS__, 'render_meta_box' ),
            'je_feature_request',
            'side',
            'high'
        );
    }

    /**
     * Render meta box
     */
    public static function render_meta_box( $post ) {
        $status = get_post_meta( $post->ID, '_je_request_status', true ) ?: 'pending';
        $votes  = get_post_meta( $post->ID, '_je_request_votes', true ) ?: 0;
        $email  = get_post_meta( $post->ID, '_je_request_email', true );
        
        wp_nonce_field( 'je_request_meta_box', 'je_request_meta_box_nonce' );
        ?>
        <p>
            <label for="je_request_status"><strong><?php esc_html_e( 'Status:', 'je-request-block' ); ?></strong></label><br>
            <select name="je_request_status" id="je_request_status" style="width:100%; margin-top:5px;">
                <option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'je-request-block' ); ?></option>
                <option value="planned" <?php selected( $status, 'planned' ); ?>><?php esc_html_e( 'Planned', 'je-request-block' ); ?></option>
                <option value="in_progress" <?php selected( $status, 'in_progress' ); ?>><?php esc_html_e( 'In Progress', 'je-request-block' ); ?></option>
                <option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Completed', 'je-request-block' ); ?></option>
                <option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'je-request-block' ); ?></option>
            </select>
        </p>
        <p>
            <label><strong><?php esc_html_e( 'Votes:', 'je-request-block' ); ?></strong></label><br>
            <span style="font-size:24px; color:#4f46e5;"><?php echo intval( $votes ); ?></span>
        </p>
        <?php if ( $email ) : ?>
        <p>
            <label><strong><?php esc_html_e( 'Email:', 'je-request-block' ); ?></strong></label><br>
            <a href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
        </p>
        <?php endif; ?>
        <?php
    }

    /**
     * Save meta boxes
     */
    public static function save_meta_boxes( $post_id ) {
        if ( ! isset( $_POST['je_request_meta_box_nonce'] ) ) {
            return;
        }
        
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['je_request_meta_box_nonce'] ) ), 'je_request_meta_box' ) ) {
            return;
        }
        
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        
        if ( isset( $_POST['je_request_status'] ) ) {
            update_post_meta( $post_id, '_je_request_status', sanitize_text_field( wp_unslash( $_POST['je_request_status'] ) ) );
        }
    }
}