<?php
/**
 * library/setup/views/layout.php.
 *
 * @author Jacob Morrison <jacob@canis.io>
 */
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?php echo $this->pageTitle; ?> <?php echo $this->name; ?></title>
<style type="text/css">
body {
    font-family: Helvetica, Arial, sans-serif;
    font-size: 14px;
    background-color: #CCC;
    color: #666;
}
#container {
    width: 800px;
    margin: 10px auto;
    background-color: #fff;
    padding: 10px;
}
.row {
    margin: .5em;
}

.row .error {
    color: red;
    font-weight: bold;
}

fieldset {
    margin-bottom: 1em;
}
label {
    display: block;
    font-size: 1.1em;
    font-weight: bold;
}
legend {
    font-size: 1.2em;
    font-weight: bold;
}
input[type=text] {
    font-size: 1.2em;
    width: 99%;
}
input[type=submit] {
    font-size: 1.2em;
    font-weight: bold;
}
div.flash-error,div.flash-notice,div.flash-success,div.flash-confirm,td.error {
    padding:.8em;
    margin-bottom:1em;
    border:2px solid #ddd;
    margin:10px;
    font-weight:bold;
}

a.confirm,
a.visit-app {
    padding:.4em;
    margin-bottom:1em;
    border:2px solid #ddd;
    margin:10px;
    font-weight:bold;
    text-decoration: none;
    opacity: .9;
    font-size: 1.2em;
}

a.visit-app	{
    margin: 20	px;
    display: block;

    background:#b2f0fa;
    color:#0a8092;
    border-color:#0fc5e2;

    text-align: right;
}

a.confirm:hover {
    opacity: 1;
}

div.flash-error,td.error,td.import-error,a.confirm-no {
    background:#FBE3E4;
    color:#8a1f11;
    border-color:#FBC2C4;
}

div.flash-notice,td.import-notice,
div.flash-confirm,td.import-confirm {
    background:#FFF6BF;
    color:#514721;
    border-color:#FFD324;
}

div.flash-success,td.import-okay,a.confirm-yes {
    background:#E6EFC2;
    color:#264409;
    border-color:#C6D880;
}

div.flash-error a {
    color:#8a1f11;
}

div.flash-notice a,
div.flash-confirm a {
    color:#514721;
}

div.flash-success a {
    color:#264409;
}
.section {
    border: 1px solid #ddd;
    margin: 2px;
    padding: 5px;
}
.section h2 {
    border-bottom: 1px solid #DDDDDD;
    margin: -5px -5px 5px;
    padding: 5px;
    position: relative;
    font-weight: 400;
}
</style>
</head>

<body>
    <div id="container">
      <h1><?php echo $this->name; ?> Installation</h1>
<?php
if (isset($_GET['message'])) {
    echo '<div class="flash-success">';
    echo $_GET['message'];
    echo '</div>';
}

if (isset($task)) {
    echo '<div class="section">';
    echo '<h2>Set Up ' . $task->title . '</h2>';
}
echo $content;
if (isset($task)) {
    echo '</div>';
}

if (empty($error) and ($this->isAvailable or !empty($forceContinue))) {
    echo '<a class="visit-app" href="/">Go to Application &gt;&gt;</a>';
}
?>

    </div>
</body>
</html>
