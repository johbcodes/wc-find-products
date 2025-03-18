<?php
/*
Plugin Name: WooCommerce Duplicate Product Finder
Description: Identifies potential duplicate products in WooCommerce based on product titles
Version: 1.0
Author: @johbcodes
*/

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add menu item to WooCommerce admin menu
function wdpf_add_admin_menu()
{
    add_submenu_page(
        'woocommerce',
        'Duplicate Product Finder',
        'Duplicate Finder',
        'manage_woocommerce',
        'duplicate-product-finder',
        'wdpf_admin_page'
    );
}
add_action('admin_menu', 'wdpf_add_admin_menu');

// Admin page content
function wdpf_admin_page()
{
?>
    <div class="wrap">
        <h1>Duplicate Product Finder</h1>

        <?php
        if (isset($_POST['scan_duplicates'])) {
            wdpf_find_duplicates();
        }
        ?>

        <form method="post">
            <p><input type="submit" name="scan_duplicates" class="button button-primary" value="Scan for Duplicates"></p>
        </form>
    </div>
<?php
}

// Function to find and display duplicate products
function wdpf_find_duplicates()
{
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        echo '<div class="error"><p>WooCommerce is not active. This plugin requires WooCommerce to function.</p></div>';
        return;
    }

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );

    $products = new WP_Query($args);
    $product_titles = array();
    $duplicates = array();

    // Collect all product titles
    if ($products->have_posts()) {
        while ($products->have_posts()) {
            $products->the_post();
            $title = get_the_title();
            $id = get_the_ID();

            if (isset($product_titles[$title])) {
                // If title exists, add to duplicates
                if (!isset($duplicates[$title])) {
                    $duplicates[$title] = array($product_titles[$title]);
                }
                $duplicates[$title][] = $id;
            } else {
                $product_titles[$title] = $id;
            }
        }
        wp_reset_postdata();
    }

    // Display results
    if (!empty($duplicates)) {
        echo '<h2>Found Potential Duplicates</h2>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Product Title</th><th>Product IDs</th><th>Actions</th></tr></thead>';
        echo '<tbody>';

        foreach ($duplicates as $title => $ids) {
            echo '<tr>';
            echo '<td>' . esc_html($title) . '</td>';
            echo '<td>';
            foreach ($ids as $id) {
                echo '<a href="' . get_edit_post_link($id) . '" target="_blank">#' . $id . '</a> ';
            }
            echo '</td>';
            echo '<td><a href="' . admin_url('edit.php?post_type=product&s=' . urlencode($title)) . '" class="button">View All</a></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '<p>Total duplicate groups found: ' . count($duplicates) . '</p>';
    } else {
        echo '<div class="updated"><p>No duplicate products found.</p></div>';
    }
}

// Add basic styling
function wdpf_admin_styles()
{
    $screen = get_current_screen();
    if ($screen->id === 'woocommerce_page_duplicate-product-finder') {
        echo '<style>
            .wp-list-table {
                margin-top: 20px;
            }
            .wp-list-table td {
                vertical-align: middle;
            }
        </style>';
    }
}
add_action('admin_head', 'wdpf_admin_styles');
