<?php
/*
Plugin Name: WooCommerce CSV Product Import/Export
Description: Import and export WooCommerce products via CSV files.
Version: 1.0
Author: Taha Ahmadpour
*/

// Ensure WooCommerce is active before enabling this plugin
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    exit;
}

// Add a submenu
add_action('admin_menu', 'csv_import_export_menu');
function csv_import_export_menu() {
    add_submenu_page(
        'woocommerce',
        'CSV Import/Export',
        'CSV Import/Export',
        'manage_woocommerce',
        'csv-import-export',
        'csv_import_export_page'
    );
}

// Function to display the page content
function csv_import_export_page() {
    ?>
    <div class="wrap">
        <h1>WooCommerce CSV Product Import/Export</h1>
        <form method="post" enctype="multipart/form-data">
            <h2>Export Products</h2>
            <p><input type="submit" name="export_csv" class="button-primary" value="Export Products to CSV" /></p>

            <h2>Import Products</h2>
            <p><input type="file" name="import_csv" /></p>
            <p><input type="submit" name="import_csv_submit" class="button-primary" value="Import Products from CSV" /></p>
        </form>
    </div>
    <?php
    handle_csv_import_export();
}

// Handle the import/export logic
function handle_csv_import_export() {
    if (isset($_POST['export_csv'])) {
        export_products_to_csv();
    }

    if (isset($_POST['import_csv_submit']) && !empty($_FILES['import_csv']['tmp_name'])) {
        import_products_from_csv($_FILES['import_csv']['tmp_name']);
    }
}

// Function to export products
function export_products_to_csv() {
    // Fetch all products
    $products = wc_get_products(array('limit' => -1)); // No limit to fetch all products

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename=woocommerce-products.csv');

    $output = fopen('php://output', 'w');

    // Output column headings
    fputcsv($output, array('ID', 'Name', 'Price', 'Stock', 'SKU', 'Categories'));

    foreach ($products as $product) {
        $product_id = $product->get_id();
        $product_name = $product->get_name();
        $product_price = $product->get_price();
        $product_stock = $product->get_stock_quantity();
        $product_sku = $product->get_sku();
        $product_categories = implode(', ', wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names')));

        fputcsv($output, array($product_id, $product_name, $product_price, $product_stock, $product_sku, $product_categories));
    }

    fclose($output);
    exit;
}

// Function to import products
function import_products_from_csv($file) {
    $file_info = pathinfo($file);

    // Ensure the file is a CSV
    if ($file_info['extension'] !== 'csv') {
        echo '<div class="notice notice-error"><p>Please upload a valid CSV file.</p></div>';
        return;
    }

    $handle = fopen($file, 'r');
    
    if ($handle !== FALSE) {
        $row = 0;
        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
            if ($row == 0) {
                // Skip the header row
                $row++;
                continue;
            }

            $product_id = intval($data[0]);
            $product_name = sanitize_text_field($data[1]);
            $product_price = floatval($data[2]);
            $product_stock = intval($data[3]);
            $product_sku = sanitize_text_field($data[4]);
            $product_categories = explode(',', sanitize_text_field($data[5]));

            // Check if the product exists, if not create a new product
            $product = wc_get_product($product_id);
            if (!$product) {
                $product = new WC_Product_Simple();
            }

            $product->set_name($product_name);
            $product->set_regular_price($product_price);
            $product->set_stock_quantity($product_stock);
            $product->set_sku($product_sku);

            // Set product categories
            $term_ids = array();
            foreach ($product_categories as $category_name) {
                $term = get_term_by('name', trim($category_name), 'product_cat');
                if ($term) {
                    $term_ids[] = $term->term_id;
                }
            }
            $product->set_category_ids($term_ids);

            $product->save();

            $row++;
        }
        fclose($handle);

        echo '<div class="notice notice-success"><p>Products imported successfully!</p></div>';
    }
}
?>