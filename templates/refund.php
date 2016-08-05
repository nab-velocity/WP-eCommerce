<div id="wpevelocity-header">
    <div id="wpevelocity-header-info">
        <?php printf('%s %s', __('Module Version', 'wpevelocity'), $this->version); ?> |    
        <strong><?php printf(__('Online Refund Via Velocity Gateway', 'wpevelocity')); ?></strong>
    </div>
</div>
<div class="message-box">
    <?php
    $orderdata = json_decode(base64_decode($_REQUEST['id']));
    if (isset($_POST['refundamount']) && $_POST['refundamount'] != '' && 
        isset($_POST['refund']) && $_POST['refund'] == 'Refund Process') {

        global $wpdb;

        if (isset($orderdata->extrainfo->totalprice) && isset($_POST['refundamount'])) {

            $allowded_refund = $orderdata->extrainfo->totalprice - $orderdata->extrainfo->base_shipping;
            if ($allowded_refund >= $_POST['refundamount']) {

                // include sdk to use retrunbyid method for refund the payment
                require_once(realpath(dirname(dirname(__FILE__))) . '/sdk/Velocity.php');

                $velocity_credential = get_option('wpevelocity_options');

                // check test or live mode.
                if ($velocity_credential['wpevelocity_testmode'] == 'test')
                    $isTestAccount = 'true';
                else
                    $isTestAccount = 'false';

                try {
                    $velocityProcessor = new VelocityProcessor($velocity_credential['wpevelocity_applicationprofileid'], $velocity_credential['wpevelocity_merchantprofileid'], $velocity_credential['wpevelocity_workflowid'], $isTestAccount, $velocity_credential['wpevelocity_identitytoken']);

                    $refundtotal = 0;
                    if (isset($_POST['refundshipping'])) {
                        $refundtotal += $_POST['refundamount'] + $orderdata->extrainfo->base_shipping;
                    } else {
                        $refundtotal += $_POST['refundamount'];
                    }
                    $txnid = $orderdata->extrainfo->transactid;

                    // got ReturnById xml object.  
                    $xml = VelocityXmlCreator::returnByIdXML(number_format($refundtotal, 2, '.', ''), $txnid);
                    $reqobj = $xml->saveXML();
                    
                    // save the refund request xml into log file.
                    WpeVelocity_Helper::islog(serialize($reqobj),'R');
                    
                    // send request for refund the payment.
                    $response = $velocityProcessor->returnById(array(
                        'amount' => $refundtotal,
                        'TransactionId' => $txnid
                    ));
                    
                    // save the refund response array into log file.
                    WpeVelocity_Helper::islog(serialize($response),'R');
                    
                    // check the refund response.
                    if (is_array($response) && !empty($response) && isset($response['Status']) && $response['Status'] == 'Successful') {
                        // Insert payment into velocity table
                        $table_name = $wpdb->prefix . "wpsc_velocity_transactions";
                        $wpdb->insert($table_name, array('transaction_id' => $response['TransactionId'], 'transaction_status' => $response['TransactionState'], 'order_id' => $orderdata->extrainfo->id, 'request_obj' => serialize($reqobj), 'response_obj' => serialize($response)));

                        // get the previous notes for the order.
                        $purchase_log_notes = $wpdb->get_var($wpdb->prepare('SELECT notes FROM ' . WPSC_TABLE_PURCHASE_LOGS . " WHERE id = %d", $orderdata->extrainfo->id));
                        if ($purchase_log_notes != '')
                            $purchase_log_notes .= "\n" . str_repeat('=', 85);
                        $purchase_log_notes .= "\nOrder Payment has been Refunded, Refunded amount is " . wpsc_get_currency_symbol() . $response['Amount'] . ' and with the transactionid : ' . $response['TransactionId'];

                        // update the order status and note for refund.
                        $wpdb->update(
                                WPSC_TABLE_PURCHASE_LOGS, array(
                            'processed' => 7,
                            'notes' => $purchase_log_notes
                                ), array('id' => $orderdata->extrainfo->id), array(
                            '%d',
                            '%s'
                                ), array('%d')
                        );
                        
                        set_transient("{$orderdata->extrainfo->sessionid}_refund_email_sent", true, 60 * 60 * 12);

                        echo '<script>alert("Your payment has been refunded successfully");window.location.href="index.php?page=wpsc-purchase-logs&c=item_details&id=' . $orderdata->extrainfo->id . '";</script>';
                        exit;
                    } else if (is_array($response) && !empty($response)) {
                        printf('%s', $response['StatusMessage']);
                    } else if (is_string($response)) {
                        printf('%s', $response);
                    } else {
                        printf(__('Unknown Error please contact the site admin.', 'wpevelocity'));
                    }
                } catch (Exception $e) {
                    printf('%s', $e->getMessage());
                }
            } else {
                printf('%s %s', __('Max refund amount allowded for this order is', 'wpevelocity'), $allowded_refund);
            }
        } else {
            printf(__('Refund detail are not retrieve for this order.', 'wpevelocity'));
        }
    }
    ?>
</div>
<div id="wpevelocity-refund-container">
    <?php 
    if (is_plugin_active('wp-e-velocity/wp-e-velocity.php')) { ?>
    <form action="" method="post">
        <table>
            <tr>
                <td>
                    Refund Amount
                </td>
                <td>
                    <input type="text" name="refundamount" />
                </td>
            </tr>
            <tr>
                <td>
                    Refund Shipping
                </td>
                <td>
                    <input type="checkbox" name="refundshipping" />
                </td>
            </tr>
            <tr>
                <td>
                    &nbsp;
                </td>
                <td>
                    <input type="submit" name="refund" value="Refund Process" />
                </td>
            </tr>
        </table>
    </form>
    <?php } else { ?>
    <div class="message-box">Please first activate the WP eVelocity plugin then perform payment refund process.</div>
    <?php } ?>
    <a href="index.php?page=wpsc-purchase-logs&c=item_details&id=<?php echo $orderdata->extrainfo->id; ?>">Back to Order</a>
</div>