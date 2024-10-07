<?php

namespace BasgateSDK;

class BasgateConstants
{
    const TRANSACTION_STATUS_URL_PRODUCTION = "https://api.basgate.com:4950/api/v1/merchant/secure/transaction/status";
    const TRANSACTION_STATUS_URL_STAGING = "https://api-tst.basgate.com:4951/api/v1/merchant/secure/transaction/status";

    const PRODUCTION_HOST = "https://api.basgate.com:4950/";
    const STAGING_HOST = "https://api-tst.basgate.com:4951/";

    // CONST ORDER_PROCESS_URL= "api/v1/merchant/secure/transaction/initiate";
    const ORDER_STATUS_URL = "api/v1/merchant/secure/transaction/status";
    const INITIATE_TRANSACTION_URL = "api/v1/merchant/secure/transaction/initiate";
    // const BASGATE_SDK_URL_PRODUCTION = "https://pub-8bba29ca4a7a4024b100dca57bc15664.r2.dev/sdk/prod/";
    // const BASGATE_SDK_URL_STAGING = "https://pub-8bba29ca4a7a4024b100dca57bc15664.r2.dev/sdk/stage/";
    const CHECKOUT_JS_URL = "https://pub-8bba29ca4a7a4024b100dca57bc15664.r2.dev/sdk/merchant/v1/public.js";


    const SAVE_BASGATE_RESPONSE = true;
    const CHANNEL_ID = "WEB";
    const APPEND_TIMESTAMP = true;
    const ORDER_PREFIX = "";
    const X_REQUEST_ID = "PLUGIN_WOOCOMMERCE_";
    const PLUGIN_DOC_URL = "https://basgate.github.io/pages.html";

    const MAX_RETRY_COUNT = 3;
    const CONNECT_TIMEOUT = 10;
    const TIMEOUT = 10;

    const LAST_UPDATED = "20241003";
    const PLUGIN_VERSION = "0.1.5";
    const PLUGIN_VERSION_FOLDER = "015";

    const CUSTOM_CALLBACK_URL = "";


    const ID = "basgate";
    const METHOD_TITLE = "Basgate Payments";
    const METHOD_DESCRIPTION = "The best payment gateway provider in Yemen for e-payment through most of wallets and banks";

    const TITLE = "Pay via Basgate Payment";
    const DESCRIPTION = "The best payment gateway provider in Yemen for e-payment through most of wallets and banks";

    const FRONT_MESSAGE = "Thank you for your order, please click the button below to pay with basgate.";
    const NOT_FOUND_TXN_URL = "Something went wrong. Kindly contact with us.";
    const BASGATE_PAY_BUTTON = "Pay via Basgate";
    const CANCEL_ORDER_BUTTON = "Cancel order & Restore cart";
    const POPUP_LOADER_TEXT = "Thank you for your order. We are now redirecting you to basgate to make payment.";

    const TRANSACTION_ID = "<b>Transaction ID:</b> %s";
    const BASGATE_ORDER_ID = "<b>Basgate Order ID:</b> %s";

    const REASON = " Reason: %s";
    const FETCH_BUTTON = "Fetch Status";

    //Success
    const SUCCESS_ORDER_MESSAGE = "Thank you for your order. Your payment has been successfully received.";
    const RESPONSE_SUCCESS = "Updated <b>STATUS</b> has been fetched";
    const RESPONSE_STATUS_SUCCESS = " and Transaction Status has been updated <b>PENDING</b> to <b>%s</b>";
    const RESPONSE_ERROR = "Something went wrong. Please again'";

    //Error
    const PENDING_ORDER_MESSAGE = "Your payment has been pending!";
    const ERROR_ORDER_MESSAGE = "Your payment has been failed!";
    const ERROR_SERVER_COMMUNICATION = "It seems some issue in server to server communication. Kindly connect with us.";
    const ERROR_CHECKSUM_MISMATCH = "Security Error. Checksum Mismatched!";
    const ERROR_AMOUNT_MISMATCH = "Security Error. Amount Mismatched!";
    const ERROR_INVALID_ORDER = "No order found to process. Kindly contact with us.";
    const ERROR_CURL_DISABLED = "cURL is not enabled properly. Please verify.";
    const ERROR_CURL_WARNING = "Your server is unable to connect with us. Please contact to Basgate Support.";

    const WEBHOOK_STAGING_URL = "https://api-tst.basgate.com:4951/";
    const WEBHOOK_PRODUCTION_URL = "https://api.basgate.com:4950/";
}
