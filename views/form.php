<?php
/**
 * library/setup/views/form.php.
 *
 * @author Jacob Morrison <jacob@canis.io>
 */
echo '<form id="setup-form" name="setup-form" method="post" action="">';
echo '<input type="hidden" name="confirm" value="' . $this->getConfirmSalt($task->id) . '" />';
echo '<input type="hidden" name="task" value="' . $task->id . '" />';
foreach ($fields as $fieldsetName => $fieldset) {
    echo '<fieldset>';
    echo '<legend>' . $fieldset['label'] . '</legend>';
    foreach ($fieldset['fields'] as $fieldNameShort => $settings) {
        $fieldId = 'field_' . $task->id . '_' . $fieldsetName . '_' . $fieldNameShort . '';
        $fieldName = $task->id . '[' . $fieldsetName . '][' . $fieldNameShort . ']';
        $value = null;
        if (isset($settings['value'])) {
            if (is_callable($settings['value'])) {
                $value = $settings['value']();
            } else {
                $value = $settings['value'];
            }
            if ($value === true) {
                $value = 'true';
            } elseif ($value === false) {
                $value = 'false';
            }
        }
        if (isset($_POST[$task->id][$fieldsetName][$fieldNameShort])) {
            $value = $_POST[$task->id][$fieldsetName][$fieldNameShort];
        }
        if (isset($settings['label'])) {
            echo '<div class="row">';
            echo '<label for="' . $fieldId . '">' . $settings['label'] . '</label>';
        }
        switch ($settings['type']) {
        case 'text':
        case 'password':
        case 'hidden':
            echo '<input id="' . $fieldId . '" type="' . $settings['type'] . '" name="' . $fieldName . '" value="' . $value . '" />';
            break;
        case 'select':
            echo '<select id="' . $fieldId . '" name="' . $fieldName . '">';
            foreach ($settings['options'] as $k => $v) {
                $extra = null;
                if ($k == $value) {
                    $extra = ' selected="selected"';
                }
                echo '<option value="' . $k . '"' . $extra . '>' . $v . '</option>';
            }
            echo '</select>';
            break;
        }
        if (isset($settings['label'])) {
            if (isset($task->fieldErrors[$fieldId])) {
                echo '<div class="error">';
                echo $task->fieldErrors[$fieldId];
                echo '</div>';
            }
            echo '</div>';
        }
    }
    echo '</fieldset>';
}
echo '<input name="setup" value="Proceed &gt;&gt;" type="submit" />';
echo '</form>';
