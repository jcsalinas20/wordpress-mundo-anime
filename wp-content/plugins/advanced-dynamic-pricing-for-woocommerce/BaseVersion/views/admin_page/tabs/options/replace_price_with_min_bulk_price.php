<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<tr valign="top">
    <th scope="row" class="titledesc">
    </th>
    <td class="forminp forminp-checkbox">
				<a href="https://algolplus.freshdesk.com/en/support/solutions/articles/25000022380-tags-supported-at-tab-product-price-" target="_blank">
					<?php _e( 'Guide for supported tags', 'advanced-dynamic-pricing-for-woocommerce' ) ?>
				</a>
    </td>
<tr>


<tr valign="top">
    <th scope="row" class="titledesc">
        <div><?php _e( 'Replace price with lowest bulk price', 'advanced-dynamic-pricing-for-woocommerce' ) ?></div>
		<div style="font-style: italic; font-weight: normal; margin: 10px 0;">
            <label><?php _e( 'Applying at category/tag pages', 'advanced-dynamic-pricing-for-woocommerce' ) ?></label>
        </div>
    </th>
    <td class="forminp forminp-checkbox">
        <fieldset>
            <div>
                <label for="replace_price_with_min_bulk_price">
                    <input <?php checked( $options['replace_price_with_min_bulk_price'] ) ?> name="replace_price_with_min_bulk_price" id="replace_price_with_min_bulk_price" type="checkbox">
					<?php _e( 'Enable', 'advanced-dynamic-pricing-for-woocommerce' ) ?>
                </label>
            </div>
            <div>
                <label for="replace_price_with_min_bulk_price_template">
	                <?php _e( 'Output template', 'advanced-dynamic-pricing-for-woocommerce' ) ?>
                    <input value="<?php echo $options['replace_price_with_min_bulk_price_template'] ?>" name="replace_price_with_min_bulk_price_template" id="replace_price_with_min_bulk_price_template" type="text">
                </label>
                <br>
                <?php _e( 'Available tags', 'advanced-dynamic-pricing-for-woocommerce' ) ?> : <?php _e( '{{price}}, {{price_suffix}}, {{price_striked}}, {{initial_price}}', 'advanced-dynamic-pricing-for-woocommerce' ) ?>
            </div>
        </fieldset>
    </td>
</tr>
