<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<tr valign="top">
    <th scope="row" class="titledesc"><?php _e('Disable external coupons', 'advanced-dynamic-pricing-for-woocommerce') ?></th>
    <td class="forminp">
        <label><input type="radio" name="disable_external_coupons" value="dont_disable" <?php checked( $options['disable_external_coupons'], 'dont_disable' ); ?>>
        <?php _e('Don\'t disable', 'advanced-dynamic-pricing-for-woocommerce') ?></label>

        <label><input type="radio" name="disable_external_coupons" value="if_any_rule_applied" <?php checked( $options['disable_external_coupons'], 'if_any_rule_applied' ); ?>>
        <?php _e('If any rule applied', 'advanced-dynamic-pricing-for-woocommerce') ?></label>

        <label><input type="radio" name="disable_external_coupons" value="if_any_of_cart_items_updated" <?php checked( $options['disable_external_coupons'], 'if_any_of_cart_items_updated' ); ?>>
        <?php _e('If any of cart items updated', 'advanced-dynamic-pricing-for-woocommerce') ?></label>
    </td>
</tr>
