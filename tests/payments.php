<?php
/** Real WooCommerce cart/order/Store API fixture. Disposable installs ONLY. */
require_once __DIR__.'/fixture.php'; nil_fixture_guard();
$payment_start = $checks;
$payment_options = array();
foreach(array(NIL_Payments::OPTION,'woocommerce_currency','woocommerce_price_num_decimals','woocommerce_calc_taxes','woocommerce_prices_include_tax','woocommerce_default_country','woocommerce_enable_coupons')as$name){$payment_options[$name]=get_option($name,null);}
$payment_user=get_current_user_id();$payment_post=$_POST;$payment_get=$_GET;$payment_method=$_SERVER['REQUEST_METHOD']??'GET';
$payment_old_customer=WC()->customer;$payment_old_cart=WC()->cart;
if(!WC()->session)WC()->initialize_session();
$payment_old_chosen=WC()->session->get('chosen_payment_method');
WC()->customer=new WC_Customer(0,true);WC()->customer->set_billing_country('IR');WC()->customer->set_shipping_country('IR');
WC()->cart=new WC_Cart();$pc=WC()->cart;
update_option('woocommerce_currency','IRT');update_option('woocommerce_price_num_decimals',0);update_option('woocommerce_calc_taxes','no');update_option('woocommerce_prices_include_tax','no');update_option('woocommerce_default_country','IR');update_option('woocommerce_enable_coupons','yes');
$pg=WC()->payment_gateways()->payment_gateways();$payment_gateway_state=array();
foreach(array('bacs','cod','cheque')as$id){$payment_gateway_state[$id]=array($pg[$id]->enabled,$pg[$id]->title);$pg[$id]->enabled='yes';}
$payment_cod_virtual=$pg['cod']->enable_for_virtual;$pg['cod']->enable_for_virtual=true;
$ps=new WC_Product_Simple();$ps->set_name('Payment fixture sale');$ps->set_status('publish');$ps->set_virtual(true);$ps->set_regular_price('4000000');$ps->set_sale_price('3000000');$psid=$ps->save();
$pp=new WC_Product_Variable();$pp->set_name('Payment fixture variable');$pp->set_status('publish');$pa=new WC_Product_Attribute();$pa->set_name('size');$pa->set_options(array('M'));$pa->set_variation(true);$pp->set_attributes(array($pa));$ppid=$pp->save();
$pv=new WC_Product_Variation();$pv->set_parent_id($ppid);$pv->set_attributes(array('size'=>'M'));$pv->set_status('publish');$pv->set_virtual(true);$pv->set_regular_price('2000000');$pvid=$pv->save();WC_Product_Variable::sync($ppid);
$pc->add_to_cart($psid,3);$pc->add_to_cart($ppid,3,$pvid,array('attribute_size'=>'M'));
check(count($pc->get_cart())===2,'payment cart has sale product and real variation');
$payment_config=NIL_Payments::validate(array('enabled'=>true,'currency'=>'IRT','rules'=>array('bacs'=>array('percent'=>'۱۰','cap'=>'۱٬۰۰۰٬۰۰۰'),'cod'=>array('percent'=>'0','cap'=>''),'cheque'=>array('percent'=>'5','cap'=>''))),$pg);
update_option(NIL_Payments::OPTION,$payment_config,false);WC()->session->set('chosen_payment_method','bacs');$pc->calculate_totals();
check((float)$pc->get_subtotal()===15000000.0,'current sale/variation prices, not regular parent prices');
check((float)$pc->get_discount_total()===1000000.0&&(float)$pc->get_total('edit')===14000000.0,'10 percent capped at one million for WHOLE order');
check(count($pc->get_applied_coupons())===1&&count($pc->get_fees())===0,'one virtual coupon and no negative fee');check((new WC_Coupon(NIL_Payments::CODE))->get_id()===0&&(new WC_Coupon(NIL_Payments::CODE))->get_virtual(),'automatic coupon is not a database post');
$pc->calculate_totals();$pc->calculate_totals();check((float)$pc->get_total('edit')===14000000.0&&count($pc->get_applied_coupons())===1,'idempotent recalculation');
WC()->session->set('chosen_payment_method','cod');$pc->calculate_totals();check((float)$pc->get_total('edit')===15000000.0&&!$pc->has_discount(NIL_Payments::CODE),'switch to zero-percent installments removes entire discount');
WC()->session->set('chosen_payment_method','forged');$pc->set_applied_coupons(array(NIL_Payments::CODE));$pc->calculate_totals();check(!$pc->has_discount(NIL_Payments::CODE),'forged/unknown gateway cannot retain discount');
WC()->session->set('chosen_payment_method','cheque');$pc->calculate_totals();check((float)$pc->get_discount_total()===750000.0,'independent rate for every registered payment method');
$pg['cheque']->enabled='no';$pc->calculate_totals();check(!$pc->has_discount(NIL_Payments::CODE),'disabled gateway has no discount');$pg['cheque']->enabled='yes';
WC()->session->set('chosen_payment_method','bacs');$payment_config['rules']['bacs']['cap']='';update_option(NIL_Payments::OPTION,$payment_config,false);$pc->calculate_totals();check((float)$pc->get_discount_total()===1500000.0,'empty cap is unlimited');
$payment_config['rules']['bacs']['cap']='1000000';update_option(NIL_Payments::OPTION,$payment_config,false);
$keys=array_keys($pc->get_cart());$pc->set_quantity($keys[0],1,false);$pc->calculate_totals();check((float)$pc->get_discount_total()===900000.0,'quantity change recalculates below cap');$pc->set_quantity($keys[0],3,false);
$coupon=new WC_Coupon();$coupon->set_code('nil-fixture-payment-other');$coupon->set_discount_type('fixed_cart');$coupon->set_amount(2000000);$coupon->save();$pc->set_applied_coupons(array($coupon->get_code()));$pc->calculate_totals();check((float)$pc->get_discount_total()===3000000.0,'ordinary coupon plus payment discount on pre-coupon current price');
$coupon->set_individual_use(true);$coupon->save();$pc->calculate_totals();check(!$pc->has_discount(NIL_Payments::CODE)&&(float)$pc->get_discount_total()===2000000.0,'individual-use coupon retains priority');$pc->set_applied_coupons(array());
update_option('woocommerce_currency','IRR');$pc->calculate_totals();check(!$pc->has_discount(NIL_Payments::CODE),'currency drift stops rules rather than reinterpreting cap');update_option('woocommerce_currency','IRT');
$pc->calculate_totals();
$payment_data=array('payment_method'=>'bacs','billing_email'=>'nil-payment@example.test','billing_first_name'=>'Nil','billing_last_name'=>'Fixture','billing_country'=>'IR');
$poid=WC()->checkout()->create_order($payment_data);check(!is_wp_error($poid),'real classic checkout order creation: '.(is_wp_error($poid)?$poid->get_error_message():$poid));$po=wc_get_order($poid);
check((float)$po->get_total()===14000000.0&&(float)$po->get_discount_total()===1000000.0,'order totals and discount persist through Woo CRUD');
check(count($po->get_items('fee'))===0&&count($po->get_items('coupon'))===1,'order has a coupon not a negative fee');
$receipt=$po->get_meta('_nil_payment_discount');check($receipt['gateway']==='bacs'&&$receipt['currency']==='IRT'&&(float)$receipt['discount']===1000000.0,'immutable rule/gateway/currency/actual discount receipt');
$line_sum=0;foreach($po->get_items()as$item)$line_sum+=(float)$item->get_total();check($line_sum===14000000.0,'discount allocated to refundable line totals');
$patch_request=new WP_REST_Request('PATCH','/wc/store/v1/checkout');NIL_Payments::capture($po,$patch_request);check($po->get_meta('_nil_payment_discount')['gateway']==='bacs','address-only draft PATCH preserves quote without requiring a posted gateway');
$refund_item=current($po->get_items());$refund_amount=round((float)$refund_item->get_total()/$refund_item->get_quantity(),0);$refund=wc_create_refund(array('order_id'=>$poid,'amount'=>$refund_amount,'reason'=>'Disposable payment fixture','line_items'=>array($refund_item->get_id()=>array('qty'=>1,'refund_total'=>$refund_amount,'refund_tax'=>array())),'refund_payment'=>false,'restock_items'=>false));check(!is_wp_error($refund)&&abs((float)wc_get_order($poid)->get_total_refunded()-$refund_amount)<0.01,'partial refund uses actual discounted line value without external gateway call');
$bad_order=WC()->checkout()->create_order(array_merge($payment_data,array('payment_method'=>'cod')));check(is_wp_error($bad_order),'stale classic gateway/quote rejected before payment');
global $wp,$wp_query;$old_query=$wp->query_vars;$old_order_pay=$wp_query->get('order-pay');$wp->query_vars['order-pay']=$poid;$wp_query->set('order-pay',$poid);$_GET['key']=$po->get_order_key();
$retry=NIL_Payments::pay_gateways($pg);check(array_keys($retry)===array('bacs'),'discounted order retry cannot switch to installment gateway');
$payment_config['enabled']=false;update_option(NIL_Payments::OPTION,$payment_config,false);check(array_keys(NIL_Payments::pay_gateways($pg))===array('bacs'),'historical retry restriction survives rule disable');
$wp->query_vars=$old_query;$wp_query->set('order-pay',$old_order_pay);$_GET=$payment_get;$payment_config['enabled']=true;update_option(NIL_Payments::OPTION,$payment_config,false);
// Real taxes: cap is the gross product discount for tax-inclusive prices.
$tax_id=WC_Tax::_insert_tax_rate(array('tax_rate_country'=>'IR','tax_rate_state'=>'','tax_rate'=>'10.0000','tax_rate_name'=>'Fixture 10%','tax_rate_priority'=>1,'tax_rate_compound'=>0,'tax_rate_shipping'=>1,'tax_rate_order'=>0,'tax_rate_class'=>''));
update_option('woocommerce_calc_taxes','yes');update_option('woocommerce_prices_include_tax','yes');WC_Cache_Helper::invalidate_cache_group('taxes');$pc->calculate_totals();
$gross_discount=(float)$pc->get_discount_total()+(float)$pc->get_discount_tax();
check(abs($gross_discount-1000000)<2&&abs((float)$pc->get_total('edit')-14000000)<2,'tax-inclusive cap and tax allocation through native totals');
WC()->customer->set_is_vat_exempt(true);$pc->calculate_totals();check((float)$pc->get_total_tax()===0.0,'VAT-exempt checkout has no invented tax');WC()->customer->set_is_vat_exempt(false);
WC_Tax::_delete_tax_rate($tax_id);update_option('woocommerce_calc_taxes','no');update_option('woocommerce_prices_include_tax','no');WC_Cache_Helper::invalidate_cache_group('taxes');
// Store API uses native nonce/auth handling; disable nonce ONLY in this disposable PHP fixture.
add_filter('woocommerce_store_api_disable_nonce_check','__return_true');
$pc->calculate_totals();$ext=new WP_REST_Request('POST','/wc/store/v1/cart/extensions');$ext->set_header('Content-Type','application/json');$ext->set_body(wp_json_encode(array('namespace'=>'nil-manager-payment','data'=>array('payment_method'=>'cod','amount'=>999999999))));$er=rest_do_request($ext);
check($er->get_status()===200,'actual Store API extension update: '.wp_json_encode($er->get_data()));check(WC()->session->get('chosen_payment_method')==='cod'&&!$pc->has_discount(NIL_Payments::CODE),'Store API removes stale discount and ignores client amount');
$ext->set_body(wp_json_encode(array('namespace'=>'nil-manager-payment','data'=>array('payment_method'=>'bacs','amount'=>999999999))));$er=rest_do_request($ext);check($er->get_status()===200&&(float)$pc->get_discount_total()===1000000.0,'Store API applies server-owned percentage/cap');
$direct=new WP_REST_Request('POST','/wc/store/v1/checkout');$direct->set_header('Content-Type','application/json');$direct->set_body(wp_json_encode(array('payment_method'=>'cod','billing_address'=>array('first_name'=>'Nil','last_name'=>'Fixture','company'=>'','address_1'=>'Test street','address_2'=>'','city'=>'Karaj','state'=>'ABZ','postcode'=>'3187654321','country'=>'IR','email'=>'nil-payment@example.test','phone'=>'09120000000'),'shipping_address'=>array('first_name'=>'Nil','last_name'=>'Fixture','company'=>'','address_1'=>'Test street','address_2'=>'','city'=>'Karaj','state'=>'ABZ','postcode'=>'3187654321','country'=>'IR'),'payment_data'=>array())));
$dr=rest_do_request($direct);check($dr->get_status()===200,'direct Store API checkout: '.wp_json_encode($dr->get_data()));$direct_order=wc_get_order($dr->get_data()['order_id']);check($direct_order->get_payment_method()==='cod'&&!in_array(NIL_Payments::CODE,$direct_order->get_coupon_codes(),true)&&(float)$direct_order->get_total()===15000000.0,'direct POST gateway cannot reuse cash discount even without JS');
$pc->empty_cart();$pc->add_to_cart($psid,3);$pc->add_to_cart($ppid,3,$pvid,array('attribute_size'=>'M'));$block_body=json_decode($direct->get_body(),true);$block_body['payment_method']='bacs';$direct->set_body(wp_json_encode($block_body));$paid=rest_do_request($direct);check($paid->get_status()===200,'discounted real Store API checkout: '.wp_json_encode($paid->get_data()));$paid_order=wc_get_order($paid->get_data()['order_id']);check((float)$paid_order->get_total()===14000000.0&&$paid_order->get_meta('_nil_payment_discount')['gateway']==='bacs','Block order persists allocated discount through coupon hold and offline payment');
$pc->empty_cart();$pc->add_to_cart($psid,3);$pc->add_to_cart($ppid,3,$pvid,array('attribute_size'=>'M'));$payment_config['rules']['bacs']=array('percent'=>'100.00','cap'=>'');update_option(NIL_Payments::OPTION,$payment_config,false);WC()->session->set('chosen_payment_method','bacs');$pc->calculate_totals();check((float)$pc->get_total('edit')===0.0,'100 percent produces a zero-total cart');$free_data=apply_filters('woocommerce_checkout_posted_data',array('payment_method'=>''));check($free_data['payment_method']==='bacs','classic free checkout retains authorized quote when radios disappear');$block_body['payment_method']='';$direct->set_body(wp_json_encode($block_body));$free_response=rest_do_request($direct);check($free_response->get_status()===200,'free Store API checkout: '.wp_json_encode($free_response->get_data()));$free_order=wc_get_order($free_response->get_data()['order_id']);check((float)$free_order->get_total()===0.0&&$free_order->get_meta('_nil_payment_discount')['gateway']==='bacs','free order still stores the rule receipt');$payment_config['rules']['bacs']=array('percent'=>'10.00','cap'=>'1000000');update_option(NIL_Payments::OPTION,$payment_config,false);
remove_filter('woocommerce_store_api_disable_nonce_check','__return_true');
// Admin settings: permission, nonce, stale forms and atomic validation.
wp_set_current_user(1);$_SERVER['REQUEST_METHOD']='GET';ob_start();NIL_Payments::page();$view=ob_get_clean();file_put_contents(nil_test_dir().'/payment-ui-result.html',$view);check(strpos($view,'rules[bacs][cap]')!==false&&strpos($view,'IRT')!==false,'server-rendered settings show every gateway and explicit currency');
$settings_before=NIL_Payments::settings();$_SERVER['REQUEST_METHOD']='POST';$_POST=array_merge($settings_before,array('_wpnonce'=>'forged','revision'=>hash('sha256',wp_json_encode($settings_before))));ob_start();NIL_Payments::page();$view=ob_get_clean();check(NIL_Payments::settings()===$settings_before&&strpos($view,'role="alert"')!==false,'invalid settings nonce cannot mutate rules');
$_POST['_wpnonce']=wp_create_nonce('nil-payment-settings');$_POST['revision']='stale';ob_start();NIL_Payments::page();ob_end_clean();check(NIL_Payments::settings()===$settings_before,'stale settings form cannot overwrite another save');
$_POST['revision']=hash('sha256',wp_json_encode($settings_before));$_POST['rules']['bacs']['cap']='0';ob_start();NIL_Payments::page();$view=ob_get_clean();check(NIL_Payments::settings()===$settings_before&&strpos($view,'value="0"')!==false,'invalid cap rejects entire save and preserves entered values');
wp_set_current_user(0);ob_start();NIL_Payments::page();$view=ob_get_clean();check($view==='','anonymous cannot access payment rules');
$_POST=$payment_post;$_GET=$payment_get;$_SERVER['REQUEST_METHOD']=$payment_method;wp_set_current_user($payment_user);
foreach($payment_options as$name=>$value){if(null===$value)delete_option($name);else update_option($name,$value,false);}
foreach($payment_gateway_state as$id=>$state){$pg[$id]->enabled=$state[0];$pg[$id]->title=$state[1];}
$pg['cod']->enable_for_virtual=$payment_cod_virtual;WC()->cart=$payment_old_cart;WC()->customer=$payment_old_customer;WC()->session->set('chosen_payment_method',$payment_old_chosen);
echo 'PASS: '.($checks-$payment_start)." real payment cart/order/Store API/security checks; cumulative $checks\n";
file_put_contents(nil_test_dir().'/payments-result.txt',"PASS $checks checks\n");
