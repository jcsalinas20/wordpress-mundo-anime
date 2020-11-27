<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<tr valign="top">
    <th scope="row" class="titledesc">
        <?php _e( 'Apply pricing rules to backend orders', 'advanced-dynamic-pricing-for-woocommerce' ) ?>
        <div style="font-weight: normal; font-style: italic; margin: 10px 0">
            <label><?php echo sprintf(__( 'Use plugin %s to add backend orders','advanced-dynamic-pricing-for-woocommerce'), '<a href="https://wordpress.org/plugins/phone-orders-for-woocommerce/" target="_blank">Phone Orders</a>') ?></label>
        </div>
        <div style="font-weight: normal; font-style: italic; margin: 10px 0">
            <label><?php _e( 'You should activate this option if the theme uses AJAX requests to show prices (for example, to support quickview popups)', 'advanced-dynamic-pricing-for-woocommerce' ); ?> </label>
        </div>
    </th>
    <td class="forminp forminp-checkbox">
        <fieldset>
            <legend class="screen-reader-text">
                <span><?php _e( 'Apply pricing rules to backend orders', 'advanced-dynamic-pricing-for-woocommerce' ) ?></span></legend>
            <label for="load_in_backend">
                <input <?php checked( $options['load_in_backend'] ) ?>
                        name="load_in_backend" id="load_in_backend" type="checkbox">
            </label>
        </fieldset>
    </td>
</tr>