<?php

namespace fmb_engine\Facebook;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$facebook = Facebook::instance();

?>

<div class="fmb-engine-field-row">
	<div class="fmb-engine-field-label">Immediately Send Purchase Event
		<p class="ads-settings-description">If enabled, purchase events will be sent immediately when order is placed (if courier ratio is above threshold). If disabled, purchase events will be saved and sent when order status changes to "ads-purchase".</p>
	</div>
	<div class="fmb-engine-field-input">
		<label class="fmb-engine-toggle-switch">
			<input type="hidden" name="orderflow[orderflow_facebook][woo_purchase_immediately_send]" value="0">
			<input type="checkbox" name="orderflow[orderflow_facebook][woo_purchase_immediately_send]" value="1" id="orderflow_orderflow_facebook_woo_purchase_immediately_send" <?php checked( $facebook->getOption( 'woo_purchase_immediately_send' ), true ); ?>>
			<span class="fmb-engine-slider"></span>
		</label>
	</div>
</div>

<div class="fmb-engine-field-row" id="orderflow_orderflow_facebook_woo_purchase_courier_ratio_threshold_panel" style="<?php echo $facebook->getOption( 'woo_purchase_immediately_send' ) ? '' : 'display: none;'; ?>">
	<div class="fmb-engine-field-label">Courier Ratio Threshold (%)
		<p class="ads-settings-description">If courier ratio is above this threshold, purchase event will be sent immediately. If below threshold, event will be saved and sent when order status changes to "ads-purchase".</p>
	</div>
	<div class="fmb-engine-field-input">
		<?php $facebook->render_number_input( 'woo_purchase_courier_ratio_threshold', 'Enter threshold percentage', false, 100, 0, 0.1 ); ?>
	</div>
</div>

<script>
jQuery( document ).ready( function ( $ ) {
	$( '#orderflow_orderflow_facebook_woo_purchase_immediately_send' ).on( 'change', function () {
		if ( $( this ).is( ':checked' ) ) {
			$( '#orderflow_orderflow_facebook_woo_purchase_courier_ratio_threshold_panel' ).show();
		} else {
			$( '#orderflow_orderflow_facebook_woo_purchase_courier_ratio_threshold_panel' ).hide();
		}
	} );
} );
</script>
