<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<tr valign="top">
    <th scope="row" class="titledesc">
        <label for="discount_for_onsale"><?php _e('Apply discount on "Onsale" products', 'advanced-dynamic-pricing-for-woocommerce') ?></label>
    </th>
    <td class="forminp">
        <select name="discount_for_onsale">
            <option <?php selected( $options['discount_for_onsale'], 'sale_price' ); ?> value="sale_price"><?php _e('Use sale price', 'advanced-dynamic-pricing-for-woocommerce') ?></option>
            <option <?php selected( $options['discount_for_onsale'], 'discount_regular' ); ?> value="discount_regular"><?php _e('Discount regular price', 'advanced-dynamic-pricing-for-woocommerce')?></option>
            <option <?php selected( $options['discount_for_onsale'], 'discount_sale' ); ?> value="discount_sale"><?php _e('Discount sale price', 'advanced-dynamic-pricing-for-woocommerce')?></option>
            <option <?php selected( $options['discount_for_onsale'], 'compare_discounted_and_sale' ); ?> value="compare_discounted_and_sale"><?php _e('Best between discounted and sale', 'advanced-dynamic-pricing-for-woocommerce')?></option>
        </select>
    </td>
</tr>