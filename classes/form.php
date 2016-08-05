<?php

/**
 *  WpeVelocity_Form class
 *  This class use to add and validate the diffrent field type in
 *  admin configuration form.
 *  @category payment
 *  @package wp-e-velocity
 *  @author velocity
 *  @since 1.0.0
 */
class WpeVelocity_Form {
    /*
     * This function validate the velocity credential in admin panel configuration.
     * 
     * @param array field detail
     * return array field detail
     */

    public function validateSettings($input) {
        $options = get_option('wpevelocity_options');

        if (empty($input['wpevelocity_workflowid'])) {
            add_settings_error('WpeVelocity', 'WpeVelocity_error', __('Velocity Work Flow ID:', 'wpevelocity') . " '" . $input['wpevelocity_workflowid'] . "' " . __('is invalid', 'wpevelocity'));
            $input['wpevelocity_workflowid'] = $options['wpevelocity_workflowid'];
        }

        if (empty($input['wpevelocity_applicationprofileid'])) {
            add_settings_error('WpeVelocity', 'WpeVelocity_error', __('Application Profile Id:', 'wpevelocity') . " '" . $input['wpevelocity_applicationprofileid'] . "' " . __('is invalid', 'wpevelocity'));
            $input['wpevelocity_applicationprofileid'] = $options['wpevelocity_applicationprofileid'];
        }

        if (empty($input['wpevelocity_merchantprofileid'])) {
            add_settings_error('WpeVelocity', 'WpeVelocity_error', __('Merchant Profile Id:', 'wpevelocity') . " '" . $input['wpevelocity_merchantprofileid'] . "' " . __('is invalid', 'wpevelocity'));
            $input['wpevelocity_merchantprofileid'] = $options['wpevelocity_merchantprofileid'];
        }

        return $input;
    }

    /*
     * This function create dropdown field in admin config form.
     * 
     * @param array field value and description
     * return null
     */

    public function selectOptionSettings($fields) {
        $options = get_option('wpevelocity_options');
        $field = $fields[0];
        if ($options[$field] == 'test')
            echo "<select id='" . $field . "' name='wpevelocity_options[" . $field . "]'><option value='test' select='selected'>Test</option><option value='production'>Production</option></select>";
        else
            echo "<select id='" . $field . "' name='wpevelocity_options[" . $field . "]'><option value='test'>Test</option><option value='production' select='selected'>Production</option></select>";
        if (isset($fields[1]))
            echo "<p class='description'>" . $fields[1] . "</p>";
    }

    /*
     * This function create textarea field in admin config form.
     * 
     * @param array field value and description
     * return null
     */

    public function textareaSettings($fields) {
        $options = get_option('wpevelocity_options');
        $field = $fields[0];
        echo "<textarea id='" . $field . "' name='wpevelocity_options[" . $field . "]' rows='3' cols='40' >{$options[$field]}</textarea>";
        if (isset($fields[1]))
            echo "<p class='description'>" . $fields[1] . "</p>";
    }

    /*
     * This function create text field in admin config form.
     * 
     * @param array field value and description
     * return null
     */

    public function textfieldSettings($fields) {
        $options = get_option('wpevelocity_options');
        $field = $fields[0];
        echo "<input id='" . $field . "' name='wpevelocity_options[" . $field . "]' size='40' type='text' value='{$options[$field]}' />";
        if (isset($fields[1]))
            echo "<p class='description'>" . $fields[1] . "</p>";
    }

}
