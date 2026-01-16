<?php if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly ?>

<?php do_action( 'wpo_wcpdf_before_document', $this->get_type(), $this->order ); ?>

<table class="head container">
	<tr>
		<td class="header" colspan="2" style="text-align: center;">
			<?php if ( $this->has_header_logo() ) : ?>
				<?php do_action( 'wpo_wcpdf_before_shop_logo', $this->get_type(), $this->order ); ?>
				<div style="text-align: center; margin-bottom: 20px;">
					<?php 
					$upload_dir = wp_upload_dir();
					$logo_path = $upload_dir['basedir'] . '/2025/05/logo-1.jpg';
					if ( file_exists( $logo_path ) ) {
						echo '<img src="' . $logo_path . '" style="max-width: 200px; height: auto;" />';
					} else {
						$this->header_logo();
					}
					?>
				</div>
				<?php do_action( 'wpo_wcpdf_after_shop_logo', $this->get_type(), $this->order ); ?>
			<?php else : ?>
				<?php $this->title(); ?>
			<?php endif; ?>
		</td>
	</tr>
</table>

<?php do_action( 'wpo_wcpdf_before_document_label', $this->get_type(), $this->order ); ?>

<?php if ( $this->has_header_logo() ) : ?>
	<table style="width: 100%; margin-bottom: 20px;">
		<tr>
			<td style="width: 50%; vertical-align: top;">
				<h1 class="document-type-label" style="margin: 0 0 10px 0;"><?php $this->title(); ?></h1>
				<?php 
				$billing_first_name = $this->order->get_billing_first_name();
				$billing_last_name = $this->order->get_billing_last_name();
				$customer_name = trim( $billing_first_name . ' ' . $billing_last_name );
				if ( ! empty( $customer_name ) ) {
					echo '<div style="font-size: 14pt; font-weight: bold; margin-top: 5px;">' . esc_html( $customer_name ) . '</div>';
				}
				?>
			</td>
			<td style="width: 50%; text-align: right; vertical-align: top;">
				<div style="font-size: 14pt; font-weight: bold; margin-top: 10px;">IIPM CPD</div>
			</td>
		</tr>
	</table>
<?php endif; ?>

<?php do_action( 'wpo_wcpdf_after_document_label', $this->get_type(), $this->order ); ?>

<table class="order-data-addresses">
	<tr>
		<td class="address billing-address">
			<?php do_action( 'wpo_wcpdf_before_billing_address', $this->get_type(), $this->order ); ?>
			<p><?php $this->billing_address(); ?></p>
			<?php do_action( 'wpo_wcpdf_after_billing_address', $this->get_type(), $this->order ); ?>
			<?php if ( isset( $this->settings['display_email'] ) ) : ?>
				<div class="billing-email"><?php $this->billing_email(); ?></div>
			<?php endif; ?>
			<?php if ( isset( $this->settings['display_phone'] ) ) : ?>
				<div class="billing-phone"><?php $this->billing_phone(); ?></div>
			<?php endif; ?>
		</td>
		<td class="address shipping-address">
			<?php if ( $this->show_shipping_address() ) : ?>
				<h3><?php $this->shipping_address_title(); ?></h3>
				<?php do_action( 'wpo_wcpdf_before_shipping_address', $this->get_type(), $this->order ); ?>
				<p><?php $this->shipping_address(); ?></p>
				<?php do_action( 'wpo_wcpdf_after_shipping_address', $this->get_type(), $this->order ); ?>
				<?php if ( isset( $this->settings['display_phone'] ) ) : ?>
					<div class="shipping-phone"><?php $this->shipping_phone(); ?></div>
				<?php endif; ?>
			<?php endif; ?>
		</td>
		<td class="order-data">
			<table>
				<?php do_action( 'wpo_wcpdf_before_order_data', $this->get_type(), $this->order ); ?>
				<?php if ( isset( $this->settings['display_number'] ) ) : ?>
					<tr class="invoice-number">
						<th><?php $this->number_title(); ?></th>
						<td><?php $this->number( $this->get_type() ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( isset( $this->settings['display_date'] ) ) : ?>
					<tr class="invoice-date">
						<th><?php $this->date_title(); ?></th>
						<td><?php $this->date( $this->get_type() ); ?></td>
					</tr>
				<?php endif; ?>
				<?php if ( $this->show_due_date() ) : ?>
					<tr class="due-date">
						<th><?php $this->due_date_title(); ?></th>
						<td><?php $this->due_date(); ?></td>
					</tr>
				<?php endif; ?>
				<tr class="order-number">
					<th><?php $this->order_number_title(); ?></th>
					<td><?php $this->order_number(); ?></td>
				</tr>
				<tr class="order-date">
					<th><?php $this->order_date_title(); ?></th>
					<td><?php $this->order_date(); ?></td>
				</tr>
				<?php if ( $this->get_payment_method() ) : ?>
					<tr class="payment-method">
						<th><?php $this->payment_method_title(); ?></th>
						<td><?php $this->payment_method(); ?></td>
					</tr>
				<?php endif; ?>
				<?php 
				// Get PO Code from order
				global $wpdb;
				$order_id = $this->order->get_id();
				
				// Try to get PO_Code from wp_wc_orders table
				$po_code = $wpdb->get_var($wpdb->prepare(
					"SELECT PO_Code FROM {$wpdb->prefix}wc_orders WHERE id = %d",
					$order_id
				));
				
				// If PO_Code column doesn't exist or is empty, try alternative column name (case-insensitive)
				if ( empty($po_code) ) {
					$po_code = $wpdb->get_var($wpdb->prepare(
						"SELECT po_code FROM {$wpdb->prefix}wc_orders WHERE id = %d",
						$order_id
					));
				}
				
				// Trim and check if PO Code exists
				$po_code = trim($po_code ?? '');
				if ( !empty($po_code) ) : ?>
					<tr class="po-code">
						<th>PO Code:</th>
						<td><?php echo esc_html($po_code); ?></td>
					</tr>
				<?php endif; ?>
				<?php do_action( 'wpo_wcpdf_after_order_data', $this->get_type(), $this->order ); ?>
			</table>
		</td>
	</tr>
</table>

<?php do_action( 'wpo_wcpdf_before_order_details', $this->get_type(), $this->order ); ?>

<table class="order-details">
	<thead>
		<tr>
			<?php foreach ( wpo_wcpdf_get_simple_template_default_table_headers( $this ) as $column_class => $column_title ) : ?>
				<th class="<?php echo esc_attr( $column_class ); ?>"><?php echo esc_html( $column_title ); ?></th>
			<?php endforeach; ?>
		</tr>
	</thead>
	<tbody>
		<?php foreach ( $this->get_order_items() as $item_id => $item ) : ?>
			<tr class="<?php echo esc_html( $item['row_class'] ); ?>">
				<td class="product">
					<p class="item-name"><?php echo esc_html( $item['name'] ); ?></p>
					<?php do_action( 'wpo_wcpdf_before_item_meta', $this->get_type(), $item, $this->order ); ?>
					<div class="item-meta">
						<?php if ( ! empty( $item['sku'] ) ) : ?>
							<p class="sku"><span class="label"><?php $this->sku_title(); ?></span> <?php echo esc_attr( $item['sku'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $item['weight'] ) ) : ?>
							<p class="weight"><span class="label"><?php $this->weight_title(); ?></span> <?php echo esc_attr( $item['weight'] ); ?><?php echo esc_attr( get_option( 'woocommerce_weight_unit' ) ); ?></p>
						<?php endif; ?>
						<!-- ul.wc-item-meta -->
						<?php if ( ! empty( $item['meta'] ) ) : ?>
							<?php echo wp_kses_post( $item['meta'] ); ?>
						<?php endif; ?>
						<!-- / ul.wc-item-meta -->
					</div>
					<?php do_action( 'wpo_wcpdf_after_item_meta', $this->get_type(), $item, $this->order ); ?>
				</td>
				<td class="quantity"><?php echo esc_html( $item['quantity'] ); ?></td>
				<td class="price"><?php echo esc_html( $item['order_price'] ); ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<table class="notes-totals">
	<tbody>
		<tr class="no-borders">
			<td class="no-borders notes-cell">
				<?php do_action( 'wpo_wcpdf_before_document_notes', $this->get_type(), $this->order ); ?>
				<?php if ( $this->get_document_notes() ) : ?>
					<div class="document-notes">
						<h3><?php $this->notes_title(); ?></h3>
						<?php $this->document_notes(); ?>
					</div>
				<?php endif; ?>
				<?php do_action( 'wpo_wcpdf_after_document_notes', $this->get_type(), $this->order ); ?>
				<?php do_action( 'wpo_wcpdf_before_customer_notes', $this->get_type(), $this->order ); ?>
				<?php if ( $this->get_shipping_notes() ) : ?>
					<div class="customer-notes">
						<h3><?php $this->customer_notes_title(); ?></h3>
						<?php $this->shipping_notes(); ?>
					</div>
				<?php endif; ?>
				<?php do_action( 'wpo_wcpdf_after_customer_notes', $this->get_type(), $this->order ); ?>
			</td>
			<td class="no-borders totals-cell">
				<table class="totals">
					<tfoot>
						<?php foreach ( $this->get_woocommerce_totals() as $key => $total ) : ?>
							<tr class="<?php echo esc_attr( $key ); ?>">
								<th class="description"><?php echo esc_html( $total['label'] ); ?></th>
								<td class="price"><span class="totals-price"><?php echo esc_html( $total['value'] ); ?></span></td>
							</tr>
						<?php endforeach; ?>
					</tfoot>
				</table>
			</td>
		</tr>
	</tbody>
</table>

<?php do_action( 'wpo_wcpdf_after_order_details', $this->get_type(), $this->order ); ?>

<div style="margin-top: 30px; padding: 20px; border: 2px solid #000; background-color: #f9f9f9;">
	<h2 style="margin: 0 0 15px 0; font-size: 16pt; font-weight: bold; text-transform: uppercase; border-bottom: 2px solid #000; padding-bottom: 10px;">PAYMENT METHODS</h2>
	
	<?php 
	// Get the Stripe Checkout URL (direct Stripe payment page)
	$stripe_checkout_url = $this->order->get_meta('_stripe_checkout_url');
	// Fallback to WooCommerce checkout payment URL if Stripe URL not available
	$payment_url = !empty($stripe_checkout_url) ? $stripe_checkout_url : $this->order->get_checkout_payment_url(false);
	?>
	
	<div style="margin-bottom: 15px;">
		<strong>Preferred Method - Pay Online (Stripe):</strong>
	</div>
	
	<div style="margin-bottom: 15px;">
		Click <a href="<?php echo esc_url($payment_url); ?>" style="color: #0066cc; text-decoration: underline;">here</a> to pay with Stripe.
	</div>
	
	<div style="text-align: center; margin: 15px 0; font-weight: bold;">
		- OR -
	</div>
	
	<div style="margin-bottom: 10px;">
		<strong>Pay by EFT - please quote Invoice Number</strong>
	</div>
	
	<div style="margin-bottom: 8px;">
		&gt; Bank Transfer to: <strong>IIPM, PERMANENT TSB P.L.C</strong>
	</div>
	
	<div style="margin-bottom: 8px;">
		&gt; IBAN: <strong>IE90IPBS99066732865036</strong>, BIC: <strong>IPBSIE2D</strong>
	</div>
	
	<div style="margin-bottom: 15px;">
		&gt; Sort Code: <strong>99-06-67</strong>, A/c Number: <strong>32865036</strong>
	</div>
	
	<div style="margin-top: 20px; font-weight: bold;">
		Payment is due within 30 days of receipt. Please DO NOT send cheques.
	</div>
</div>

<div class="bottom-spacer"></div>

<?php if ( $this->get_footer() ) : ?>
	<htmlpagefooter name="docFooter"><!-- required for mPDF engine -->
		<div id="footer">
			<!-- hook available: wpo_wcpdf_before_footer -->
			<?php $this->footer(); ?>
			<!-- hook available: wpo_wcpdf_after_footer -->
		</div>
	</htmlpagefooter><!-- required for mPDF engine -->
<?php endif; ?>

<?php do_action( 'wpo_wcpdf_after_document', $this->get_type(), $this->order ); ?>