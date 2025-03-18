<?php
/*
Plugin Name: WooCommerce Duplicate Product Finder
Description: Identifies and manages duplicate products in WooCommerce based on product titles
Version: 1.2
Author: Joseah B. https://github.com/johbcodes/wc-find-products.git
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
        } elseif (isset($_POST['delete_duplicates'])) {
            wdpf_delete_duplicates();
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
    if (!class_exists('WooCommerce')) {
        echo '<div class="error"><p>WooCommerce is not active.</p></div>';
        return;
    }

    $items_per_page = 50;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;

    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );

    $products = new WP_Query($args);
    $product_titles = array();
    $duplicates = array();

    // Collect all product data
    if ($products->have_posts()) {
        while ($products->have_posts()) {
            $products->the_post();
            $id = get_the_ID();
            $title = get_the_title();
            $product = wc_get_product($id);

            $product_data = array(
                'id' => $id,
                'price' => floatval($product->get_price()),
                'categories' => wp_get_post_terms($id, 'product_cat', array('fields' => 'names')),
                'date' => get_the_date('Y-m-d H:i:s')
            );

            if (isset($product_titles[$title])) {
                if (!isset($duplicates[$title])) {
                    $duplicates[$title] = array($product_titles[$title]);
                }
                $duplicates[$title][] = $product_data;
            } else {
                $product_titles[$title] = $product_data;
            }
        }
        wp_reset_postdata();
    }

    // Paginate duplicates
    $duplicate_groups = array_filter($duplicates, function ($group) {
        return count($group) > 1;
    });
    $total_groups = count($duplicate_groups);
    $total_pages = ceil($total_groups / $items_per_page);
    $offset = ($paged - 1) * $items_per_page;
    $paged_duplicates = array_slice($duplicate_groups, $offset, $items_per_page, true);

    // Display results
    if (!empty($paged_duplicates)) {
        echo '<h2>Found Potential Duplicates</h2>';
        echo '<form method="post" id="duplicate-form">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>Product Title</th>';
        echo '<th>Product ID</th>';
        echo '<th>Price</th>';
        echo '<th>Categories</th>';
        echo '<th>Date</th>';
        echo '<th><input type="checkbox" id="select-all"> Select All</th>';
        echo '</tr></thead>';
        echo '<tbody>';

        foreach ($paged_duplicates as $title => $products) {
            foreach ($products as $product) {
                echo '<tr>';
                echo '<td>' . esc_html($title) . '</td>';
                echo '<td><a href="' . get_edit_post_link($product['id']) . '" target="_blank">#' . $product['id'] . '</a></td>';
                echo '<td>' . wc_price($product['price']) . '</td>';
                echo '<td>' . implode(', ', $product['categories']) . '</td>';
                echo '<td>' . $product['date'] . '</td>';
                echo '<td><input type="checkbox" name="delete_ids[]" value="' . $product['id'] . '" class="delete-checkbox"></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '<p><input type="submit" name="delete_duplicates" class="button button-danger" value="Delete Selected (Keep Lowest Price)" onclick="return confirm(\'Are you sure? This will delete selected duplicates, keeping the lowest priced product in each group.\');"></p>';
        echo '</form>';

        // Pagination
        $pagination_args = array(
            'base' => add_query_arg('paged', '%#%'),
            'format' => '',
            'total' => $total_pages,
            'current' => $paged,
            'prev_text' => '« Previous',
            'next_text' => 'Next »',
        );
        echo '<div class="tablenav"><div class="tablenav-pages">';
        echo paginate_links($pagination_args);
        echo '</div></div>';
        echo '<p>Total duplicate groups found: ' . $total_groups . '</p>';
    } else {
        echo '<div class="updated"><p>No duplicate products found.</p></div>';
    }
}

// Function to delete duplicates, keeping lowest price
function wdpf_delete_duplicates()
{
    if (!isset($_POST['delete_ids']) || !is_array($_POST['delete_ids'])) {
        return;
    }

    $delete_ids = array_map('intval', $_POST['delete_ids']);
    $args = array(
        'post_type' => 'product',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    );

    $products = new WP_Query($args);
    $title_groups = array();

    // Group products by title
    if ($products->have_posts()) {
        while ($products->have_posts()) {
            $products->the_post();
            $id = get_the_ID();
            $title = get_the_title();
            $product = wc_get_product($id);

            $title_groups[$title][] = array(
                'id' => $id,
                'price' => floatval($product->get_price())
            );
        }
        wp_reset_postdata();
    }

    // Process deletions
    foreach ($title_groups as $title => $group) {
        if (count($group) > 1) {
            // Sort by price
            usort($group, function ($a, $b) {
                return $a['price'] - $b['price'];
            });

            // Keep lowest priced product (first in sorted array)
            $keep_id = $group[0]['id'];

            foreach ($group as $product) {
                if (in_array($product['id'], $delete_ids) && $product['id'] !== $keep_id) {
                    wp_delete_post($product['id'], true);
                }
            }
        }
    }

    echo '<div class="updated"><p>Selected duplicates deleted, lowest priced products retained.</p></div>';
}

// Add styling and JavaScript
function wdpf_admin_styles()
{
    $screen = get_current_screen();
    if ($screen->id === 'woocommerce_page_duplicate-product-finder') {
    ?>
        <style>
            .wp-list-table {
                margin-top: 20px;
            }

            .wp-list-table td {
                vertical-align: middle;
            }

            .button-danger {
                background: #dc3232;
                border-color: #dc3232;
                color: white;
            }

            .button-danger:hover {
                background: #c32d2d;
                border-color: #c32d2d;
            }

            .tablenav {
                margin: 20px 0;
            }
        </style>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const selectAll = document.getElementById('select-all');
                const checkboxes = document.querySelectorAll('.delete-checkbox');

                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(checkbox => {
                        checkbox.checked = selectAll.checked;
                    });
                });
            });
        </script>
<?php
    }
}
add_action('admin_head', 'wdpf_admin_styles');
