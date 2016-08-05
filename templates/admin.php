<div id="wpevelocity-header">
    <div id="wpevelocity-header-info">
        <?php printf('%s %s', __('Module Version', 'wpevelocity'), $this->version); ?> |
        <a href="http://nabvelocity.com/"  target="_BLANK">
            <?php printf(__('Visit the nabVelocity website', 'wpevelocity')); ?>
        </a>                    
    </div>
</div>
    
<div id="wpevelocity-container">    
    <table class="wpevelocity-settings">

        <form action="options.php" method="post">
            <?php settings_fields('wpevelocity_options'); ?>
            <?php do_settings_sections('wpevelocity_config'); ?>

            <input name="Submit" type="submit" value="<?php esc_attr_e(__('Save Changes')); ?>" />
        </form>

        <?php if ((!empty($this->settings['wpevelocity_identitytoken']) && (!empty($this->settings['wpevelocity_workflowid'])) 
                 && (!empty($this->settings['wpevelocity_applicationprofileid'])) && 
                 (!empty($this->settings['wpevelocity_merchantprofileid'])))) { ?>
            <h3 id='paymentMethodsHeader'>Paymentmethods</h3>       

            <ul id='wpevelocity-paymentmethod-list'> 
                <?php
                if (!empty($this->paymentMethods)) {
                    foreach (unserialize($this->paymentMethods) as $paymentMethod) {
                        ?>
                        <li><a href='<?php echo get_option('siteurl') . "/wp-admin/options-general.php?page=wpsc-settings&tab=gateway&payment_gateway_id=wpevelocity_{$paymentMethod['PaymentMethodCode']}"; ?>'><?php echo $paymentMethod['Description']; ?></a></li>
                    <?php } ?>
                </ul>
                <?php
            }
        }
        ?>
        <noscript>
        <div class='error v_getpaymentmethods_error'>Javascript must be enabled in your browser in order to fetch paymentmethods.</div>
        </noscript>
    </table>
</div>