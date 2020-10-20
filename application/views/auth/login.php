<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Document</title>
  <link type="text/css" rel="Stylesheet" href="<?php echo CaptchaUrls::LayoutStylesheetUrl() ?>" />

</head>

<body>
  <h1><?php echo lang('login_heading'); ?></h1>
  <p><?php echo lang('login_subheading'); ?></p>

  <div id="infoMessage"><?php echo $message; ?></div>

  <?php echo form_open("auth/login"); ?>

  <p>
    admin@admin.com <br>
    password
  </p>

  <p>
    <?php echo lang('login_identity_label', 'identity'); ?>
    <?php echo form_input($identity); ?>
  </p>

  <p>
    <?php echo lang('login_password_label', 'password'); ?>
    <?php echo form_input($password); ?>
  </p>
  <p>
    <?php echo $captchaHtml; ?>
    <input type="text" name="CaptchaCode" id="CaptchaCode" value="" />
  </p>
  <p>
    <?php echo lang('login_remember_label', 'remember'); ?>
    <?php echo form_checkbox('remember', '1', FALSE, 'id="remember"'); ?>
  </p>


  <p><?php echo form_submit('submit', lang('login_submit_btn')); ?></p>

  <?php echo form_close(); ?>

  <p><a href="forgot_password"><?php echo lang('login_forgot_password'); ?></a></p>
</body>

</html>