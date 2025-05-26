<?php
/**
 * Duplicate an order for Magento 2.4.5-p10.
 *
 * Usage: php duplicate_order.php <original_increment_id>
 *
 * @author: Sean Breeden
 * @email: seanbreeden@gmail.com
 *
 */
use Magento\Framework\App\Bootstrap;

require __DIR__ . '/app/bootstrap.php';

$bootstrap    = Bootstrap::create(BP, $_SERVER);
$obj          = $bootstrap->getObjectManager();
$resource     = $obj->get('Magento\Framework\App\ResourceConnection');
$connection   = $resource->getConnection();

/**
 * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
 * @param string $originalIncrementId
 */
function duplicateOrder($connection, $originalIncrementId) {
    try {
        $originalOrderId = $connection->fetchOne(
            "SELECT entity_id FROM sales_order WHERE increment_id = :increment_id",
            ['increment_id' => $originalIncrementId]
        );
        if (!$originalOrderId) {
            die("Error: Original order with increment_id {$originalIncrementId} not found.\n");
        }
        $orderInsertQuery = "
            INSERT INTO sales_order
            (state, status, store_id, customer_id, customer_email, base_discount_amount, base_grand_total,
             base_shipping_amount, base_subtotal, base_tax_amount, discount_amount, grand_total,
             shipping_amount, subtotal, tax_amount, total_qty_ordered, created_at, updated_at)
            SELECT state, status, store_id, customer_id, customer_email, base_discount_amount, base_grand_total,
                   base_shipping_amount, base_subtotal, base_tax_amount, discount_amount, grand_total,
                   shipping_amount, subtotal, tax_amount, total_qty_ordered, NOW(), NOW()
            FROM sales_order
            WHERE entity_id = :original_order_id
        ";
        $connection->query($orderInsertQuery, ['original_order_id' => $originalOrderId]);
        $newOrderId = $connection->lastInsertId();
        $maxIncrementId = $connection->fetchOne("SELECT MAX(increment_id) FROM sales_order");
        if (preg_match('/^(\D*)(\d+)$/', $maxIncrementId, $matches)) {
            $prefix         = $matches[1];
            $number         = (int)$matches[2];
            $newIncrementId = $prefix . str_pad($number + 1, strlen($matches[2]), '0', STR_PAD_LEFT);
        } else {
            $newIncrementId = ((int)$maxIncrementId) + 1;
        }
        $connection->query(
            "UPDATE sales_order SET increment_id = :new_increment_id WHERE entity_id = :new_order_id",
            ['new_increment_id' => $newIncrementId, 'new_order_id' => $newOrderId]
        );
        $connection->query(
            "UPDATE sales_order SET status = 'processing', state = 'processing' WHERE entity_id = :new_order_id",
            ['new_order_id' => $newOrderId]
        );
        $storeId = $connection->fetchOne(
            "SELECT store_id FROM sales_order WHERE entity_id = :new_order_id",
            ['new_order_id' => $newOrderId]
        );
        if ($storeId) {
            $sequenceTable = "sequence_order_" . $storeId;
            if (preg_match('/^(\D*)(\d+)$/', $newIncrementId, $matches)) {
                $newNumeric = (int)$matches[2];
            } else {
                $newNumeric = (int)$newIncrementId;
            }
            $nextValue = $newNumeric + 1;
            $connection->query("ALTER TABLE {$sequenceTable} AUTO_INCREMENT = {$nextValue}");
        }
        function buildItemData($item, $newOrderId, $newParentId = null) {
            return [
                'order_id'               => $newOrderId,
                'parent_item_id'         => $newParentId,
                'store_id'               => $item['store_id'],
                'product_id'             => $item['product_id'],
                'product_type'           => $item['product_type'],
                'sku'                    => $item['sku'],
                'name'                   => $item['name'],
                'weight'                 => $item['weight'],
                'price'                  => $item['price'],
                'original_price'         => $item['original_price'],
                'tax_amount'             => $item['tax_amount'],
                'tax_percent'            => $item['tax_percent'],
                'discount_amount'        => $item['discount_amount'],
                'row_total'              => $item['row_total'],
                'base_price'             => $item['base_price'],
                'base_row_total'         => $item['base_row_total'],
                'base_tax_amount'        => $item['base_tax_amount'],
                'base_discount_amount'   => $item['base_discount_amount'],
                'price_incl_tax'         => $item['price_incl_tax']          ?? 0,
                'base_price_incl_tax'    => $item['base_price_incl_tax']     ?? 0,
                'row_total_incl_tax'     => $item['row_total_incl_tax']      ?? 0,
                'base_row_total_incl_tax'=> $item['base_row_total_incl_tax'] ?? 0,
                'additional_data'        => $item['additional_data'],
                'product_options'        => $item['product_options'],
                'qty_ordered'            => $item['qty_ordered'],
                'created_at'             => date('Y-m-d H:i:s'),
                'updated_at'             => date('Y-m-d H:i:s'),
            ];
        }
        $originalParentItems = $connection->fetchAll(
            "SELECT * FROM sales_order_item
             WHERE order_id = :original_order_id
               AND (parent_item_id IS NULL OR parent_item_id = 0)",
            ['original_order_id' => $originalOrderId]
        );
        $newItemMapping = [];
        foreach ($originalParentItems as $item) {
            $data = buildItemData($item, $newOrderId, null);
            $cols = array_keys($data);
            $vals = array_map(fn($col) => ':' . $col, $cols);
            $insertQuery = "INSERT INTO sales_order_item (" . implode(', ', $cols) . ")
                            VALUES (" . implode(', ', $vals) . ")";
            $connection->query($insertQuery, $data);
            $newParentItemId = $connection->lastInsertId();
            $newItemMapping[$item['item_id']] = $newParentItemId;
        }
        $originalChildItems = $connection->fetchAll(
            "SELECT * FROM sales_order_item
             WHERE order_id = :original_order_id
               AND parent_item_id IS NOT NULL
               AND parent_item_id <> 0",
            ['original_order_id' => $originalOrderId]
        );
        foreach ($originalChildItems as $item) {
            $data = buildItemData($item, $newOrderId, null);
            $cols = array_keys($data);
            $vals = array_map(fn($col) => ':' . $col, $cols);
            $insertQuery = "INSERT INTO sales_order_item (" . implode(', ', $cols) . ")
                            VALUES (" . implode(', ', $vals) . ")";
            $connection->query($insertQuery, $data);
            $newChildItemId = $connection->lastInsertId();
            $newItemMapping[$item['item_id']] = $newChildItemId;
            $oldParentId = $item['parent_item_id'];
            if (isset($newItemMapping[$oldParentId])) {
                $newParentId = $newItemMapping[$oldParentId];
                $connection->query(
                    "UPDATE sales_order_item SET parent_item_id = :new_parent_id WHERE item_id = :new_item_id",
                    [
                        'new_parent_id' => $newParentId,
                        'new_item_id'   => $newChildItemId
                    ]
                );
            }
        }
        $orderAddressInsertQuery = "
    INSERT INTO sales_order_address
    (parent_id, address_type, customer_id, email, firstname, lastname, street, city, region, region_id, postcode, country_id, telephone)
    SELECT :new_order_id, address_type, customer_id, email, firstname, lastname, street, city, region, region_id, postcode, country_id, telephone
    FROM sales_order_address
    WHERE parent_id = :original_order_id
";
        $connection->query($orderAddressInsertQuery, [
            'new_order_id'      => $newOrderId,
            'original_order_id' => $originalOrderId
        ]);
        $billingAddress = $connection->fetchRow(
            "SELECT * FROM sales_order_address WHERE parent_id = :new_order_id AND address_type = 'billing'",
            ['new_order_id' => $newOrderId]
        );
        if (!$billingAddress) {
            $shippingAddress = $connection->fetchRow(
                "SELECT * FROM sales_order_address WHERE parent_id = :original_order_id AND address_type = 'shipping'",
                ['original_order_id' => $originalOrderId]
            );
            if ($shippingAddress) {
                $insertBillingQuery = "
    INSERT INTO sales_order_address
    (parent_id, address_type, customer_id, email, firstname, lastname, street, city, region, region_id, postcode, country_id, telephone)
    VALUES (:new_order_id, 'billing', :customer_id, :email, :firstname, :lastname, :street, :city, :region, :region_id, :postcode, :country_id, :telephone)
";
                $connection->query($insertBillingQuery, [
                    'new_order_id' => $newOrderId,
                    'customer_id'  => $shippingAddress['customer_id'],
                    'email'        => $shippingAddress['email'],
                    'firstname'    => $shippingAddress['firstname'],
                    'lastname'     => $shippingAddress['lastname'],
                    'street'       => $shippingAddress['street'],
                    'city'         => $shippingAddress['city'],
                    'region'       => $shippingAddress['region'],
                    'region_id'    => $shippingAddress['region_id'],
                    'postcode'     => $shippingAddress['postcode'],
                    'country_id'   => $shippingAddress['country_id'],
                    'telephone'    => $shippingAddress['telephone'],
                ]);

            }
        }
        $orderPaymentInsertQuery = "
            INSERT INTO sales_order_payment
            (parent_id, method, additional_information)
            SELECT :new_order_id, method, additional_information
            FROM sales_order_payment
            WHERE parent_id = :original_order_id
        ";
        $connection->query($orderPaymentInsertQuery, [
            'new_order_id'      => $newOrderId,
            'original_order_id' => $originalOrderId
        ]);
        $orderStatusHistoryInsertQuery = "
            INSERT INTO sales_order_status_history
            (parent_id, status, comment, created_at)
            SELECT :new_order_id, status, comment, NOW()
            FROM sales_order_status_history
            WHERE parent_id = :original_order_id
        ";
        $connection->query($orderStatusHistoryInsertQuery, [
            'new_order_id'      => $newOrderId,
            'original_order_id' => $originalOrderId
        ]);
        $gridInsertQuery = "
            INSERT INTO sales_order_grid
                (entity_id, status, store_id, store_name, customer_id, base_grand_total, base_total_paid,
                 grand_total, total_paid, increment_id, base_currency_code, order_currency_code,
                 shipping_name, billing_name, created_at, updated_at, customer_email, customer_group,
                 subtotal, shipping_and_handling, customer_name, payment_method, total_refunded,
                 refunded_to_store_credit, signifyd_guarantee_status, pickup_location_code,
                 stripe_radar_risk_score, stripe_radar_risk_level, stripe_payment_method_type,
                 initial_fee_tax, base_initial_fee_tax)
            SELECT
                :new_order_id, status, store_id, store_name, customer_id, base_grand_total, base_total_paid,
                grand_total, total_paid, :new_increment_id, base_currency_code, order_currency_code,
                shipping_name, billing_name, created_at, updated_at, customer_email, customer_group,
                subtotal, shipping_and_handling, customer_name, payment_method, total_refunded,
                refunded_to_store_credit, signifyd_guarantee_status, pickup_location_code,
                stripe_radar_risk_score, stripe_radar_risk_level, stripe_payment_method_type,
                initial_fee_tax, base_initial_fee_tax
            FROM sales_order_grid
            WHERE entity_id = :original_order_id
        ";
        $connection->query($gridInsertQuery, [
            'new_order_id'      => $newOrderId,
            'new_increment_id'  => $newIncrementId,
            'original_order_id' => $originalOrderId
        ]);
        $updateBillingQuery = "
            UPDATE sales_order_grid s
            JOIN sales_order_address a ON a.parent_id = s.entity_id AND a.address_type = 'billing'
            SET s.billing_name = CONCAT(a.firstname, ' ', a.lastname)
            WHERE s.entity_id = :new_order_id
        ";
        $connection->query($updateBillingQuery, ['new_order_id' => $newOrderId]);
        $updateStripeQuery = "
            UPDATE sales_order_grid s
            JOIN sales_order_payment p ON p.parent_id = s.entity_id
            SET s.stripe_payment_method_type = p.method
            WHERE s.entity_id = :new_order_id AND p.method = 'stripe'
        ";
        $connection->query($updateStripeQuery, ['new_order_id' => $newOrderId]);
        $connection->query(
            "UPDATE sales_order_grid SET status = 'processing' WHERE entity_id = :new_order_id",
            ['new_order_id' => $newOrderId]
        );
        echo "Order duplicated successfully! New Order Increment ID: {$newIncrementId}\n";
        echo "Reindexing sales index...\n";
        shell_exec('bin/magento indexer:reindex sales');
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
if (!isset($argv[1])) {
    echo "Usage: php duplicate_order.php <original_increment_id>\n";
    exit(1);
}
$originalIncrementId = $argv[1];
duplicateOrder($connection, $originalIncrementId);

 
