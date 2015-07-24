<?php
/**
 * library/setup/views/confirm.php.
 *
 * @author Jacob Morrison <jacob@canis.io>
 */
echo '<div class="flash-confirm">';
if (!isset($question)) {
    $question = 'Would you like to continue?';
}
echo $question;

echo '<a class="confirm confirm-yes" href="' . $this->getConfirmLink($task->id) . '">Yes</a>';
echo '<a class="confirm confirm-no" href="/">No</a>';
echo '</div>';
