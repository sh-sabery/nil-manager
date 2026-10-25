/* global NIL_PAYMENT, jQuery */
(() => {
    'use strict';
    if (!NIL_PAYMENT.blocks) {
        jQuery(document.body).on('change.nilPayment', 'input[name="payment_method"]', () => jQuery(document.body).trigger('update_checkout'));
        return;
    }
    const {select, dispatch, subscribe} = window.wp.data;
    const {paymentStore, validationStore} = window.wc.wcBlocksData;
    const {extensionCartUpdate, registerCheckoutFilters} = window.wc.blocksCheckout;
    const key = 'nil-payment-sync', code = 'nil-manager-payment-discount';
    const validation = dispatch(validationStore);
    let desired, sent, running = false;
    registerCheckoutFilters('nil-manager-payment', {
        coupons: coupons => coupons.map(coupon => coupon.code === code ? {...coupon, label:'تخفیف روش پرداخت'} : coupon)
    });
    async function drain() {
        if (running || desired === sent) return;
        running = true;
        let target;
        validation.setValidationErrors({[key]:{message:'در حال محاسبهٔ تخفیف روش پرداخت…',hidden:true}});
        try {
            while (desired !== sent) {
                target = desired;
                await extensionCartUpdate({namespace:'nil-manager-payment',data:{payment_method:target}});
                sent = target;
            }
            validation.clearValidationError(key);
        } catch (_) {
            validation.setValidationErrors({[key]:{message:'محاسبهٔ تخفیف کامل نشد؛ روش پرداخت دیگری انتخاب کنید یا صفحه را تازه کنید.',hidden:false}});
        } finally { running = false; if (desired !== target) drain(); }
    }
    function changed() {
        const store = select(paymentStore);
        if (!store.paymentMethodsInitialized()) return;
        const method = store.getActivePaymentMethod() || '';
        if (method === desired) return;
        desired = method;
        drain();
    }
    subscribe(changed);
    changed();
})();
