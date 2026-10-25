<?php
/** Dedicated server-rendered settings: no framework, requests or admin JS. */
defined( 'ABSPATH' ) || exit;
if ( ! current_user_can( 'manage_options' ) ) { return; }
$currency = get_woocommerce_currency();
$unit = html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' );
$form = $error && is_array( $posted ) ? $posted : $settings;
?>
<div id="nil-bulk-app" class="wrap" dir="rtl" style="display:block;max-width:1180px">
<header class="nil-header"><div class="nil-identity"><span class="nil-mark" aria-hidden="true">N</span><div><span class="nil-kicker">نیل منجر · محصول نیل وب</span><h1>تخفیف روش پرداخت</h1><p>پرداخت نقدی را با تخفیف جدا از درگاه اقساطی تشویق کنید.</p></div></div><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=nil-bulk' ) ); ?>">بازگشت به نیل منجر</a></header>
<?php if ( $notice ) : ?><div class="notice <?php echo $error ? 'notice-error' : 'notice-success'; ?>" role="<?php echo $error ? 'alert' : 'status'; ?>"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
<?php if ( $settings['enabled'] && $currency !== $settings['currency'] ) : ?><div class="notice notice-warning"><p>واحد پول فروشگاه عوض شده است. تخفیف خودکار متوقف است؛ سقف‌ها را با واحد جدید بررسی و ذخیره کنید.</p></div><?php endif; ?>
<section class="nil-card nil-welcome"><div class="nil-section-head"><div><span class="nil-kicker">یک قانون برای هر روش پرداخت</span><h2>درصد دلخواه، با سقف یا بدون سقف</h2></div><span class="nil-unit">واحد سقف: <?php echo esc_html( $unit . ' (' . $currency . ')' ); ?></span></div><div class="nil-operation-grid"><article class="nil-operation"><h3>۱۰٪ بدون سقف</h3><p>سبد ۱۵ میلیون <?php echo esc_html( $unit ); ?> ← ۱٫۵ میلیون <?php echo esc_html( $unit ); ?> تخفیف</p></article><article class="nil-operation"><h3>۱۰٪ تا سقف یک میلیون</h3><p>همان سبد ← یک میلیون <?php echo esc_html( $unit ); ?> تخفیف؛ سقف برای کل سفارش است.</p></article><article class="nil-operation"><h3>اقساطی بدون تخفیف</h3><p>درصد را صفر بگذارید؛ مبلغ سبد با انتخاب آن درگاه دوباره محاسبه می‌شود.</p></article></div></section>
<form method="post" class="nil-card">
<?php wp_nonce_field( 'nil-payment-settings' ); ?>
<input type="hidden" name="currency" value="<?php echo esc_attr( $currency ); ?>">
<input type="hidden" name="revision" value="<?php echo esc_attr( hash( 'sha256', wp_json_encode( $settings ) ) ); ?>">
<div class="nil-section-head"><label><input type="checkbox" name="enabled" value="1" <?php checked( ! empty( $form['enabled'] ) ); ?>> فعال‌بودن تخفیف خودکار روش پرداخت</label><button type="submit" class="button button-primary">ذخیره تنظیمات پرداخت</button></div>
<p class="nil-hint">برای حذف تخفیف یک روش، درصد را صفر کنید. خالی‌بودن سقف یعنی نامحدود؛ صفر، سقف معتبر نیست. اعداد فارسی پذیرفته می‌شوند. روش غیرفعال تا زمان فعال‌شدن در ووکامرس تخفیفی نمی‌دهد.</p>
<?php if ( ! $gateways ) : ?><p>هنوز روش پرداختی ثبت نشده است. ابتدا در ووکامرس ← پیکربندی ← پرداخت‌ها یک روش فعال کنید.</p><?php else : ?>
<div class="nil-table-scroll"><table class="widefat nil-report"><thead><tr><th scope="col">روش پرداخت</th><th scope="col">درصد تخفیف</th><th scope="col">سقف کل سفارش (<?php echo esc_html( $unit ); ?>)</th></tr></thead><tbody>
<?php foreach ( $gateways as $id => $gateway ) :
    $row = isset( $form['rules'][$id] ) && is_array( $form['rules'][$id] ) ? $form['rules'][$id] : array();
    $percent = isset( $row['percent'] ) && is_scalar( $row['percent'] ) ? $row['percent'] : '0';
    $cap = isset( $row['cap'] ) && is_scalar( $row['cap'] ) ? $row['cap'] : '';
    $title = wp_strip_all_tags( $gateway->get_title() ?: $gateway->get_method_title() );
?><tr><th scope="row"><strong><?php echo esc_html( $title ); ?></strong><br><small><?php echo esc_html( $id . ' · ' . ( 'yes' === $gateway->enabled ? 'فعال در ووکامرس' : 'غیرفعال در ووکامرس' ) ); ?></small></th><td><label><span class="screen-reader-text"><?php echo esc_html( 'درصد تخفیف ' . $title ); ?></span><input name="rules[<?php echo esc_attr( $id ); ?>][percent]" inputmode="decimal" value="<?php echo esc_attr( $percent ); ?>" required maxlength="20" aria-label="<?php echo esc_attr( 'درصد تخفیف ' . $title ); ?>"></label></td><td><label><span class="screen-reader-text"><?php echo esc_html( 'سقف تخفیف ' . $title ); ?></span><input name="rules[<?php echo esc_attr( $id ); ?>][cap]" inputmode="decimal" value="<?php echo esc_attr( $cap ); ?>" maxlength="30" placeholder="خالی = بدون سقف" aria-label="<?php echo esc_attr( 'سقف تخفیف ' . $title ); ?>"></label></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php endif; ?>
<details><summary>مبنای محاسبه و نکات مهم</summary><p>مبنای درصد، مجموع قیمت فعلی کالاها و ورییشن‌ها پیش از کوپن‌هاست؛ حراج محصول در همین قیمت لحاظ می‌شود. ارسال و کارمزد مشمول نیستند. مالیات با موتور کوپن ووکامرس محاسبه می‌شود؛ در فروشگاه با قیمت شامل مالیات، سقف نیز شامل مالیات کالاست و در قیمت بدون مالیات، سقف پیش از مالیات است.</p><p>تخفیف با کوپن‌های معمول طبق قوانین ووکامرس جمع می‌شود و از مبلغ باقی‌ماندهٔ کالاها بیشتر نمی‌شود. کوپن «استفاده فردی» بر این تخفیف اولویت دارد. تخفیف ۱۰۰٪ ممکن است سفارش بدون نیاز به پرداخت بسازد. این قابلیت قیمت محصولات و سفارش‌های قبلی را تغییر نمی‌دهد.</p><p>مبلغ نهایی بعد از انتخاب روش پرداخت مشخص می‌شود. سفارش دارای این تخفیف در پرداخت مجدد فقط با همان درگاه پرداخت می‌شود؛ برای تغییر روش، سفارش تازه بسازید. پشتیبانی از تسویه‌حساب کلاسیک و بلوکی است؛ خود درگاه نیز باید از نوع تسویه‌حساب فروشگاه پشتیبانی کند.</p></details>
</form></div>
