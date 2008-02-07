<span id="login_bar">
<?php
if (isset($_COOKIE["AURSID"])) {
	print __("Logged-in as: %h%s%h",
		array("<b>", username_from_sid($_COOKIE["AURSID"]), "</b>"));
}
else {
	if ($login_error) {
		print "<span class='error'>" . $login_error . "</span><br />\n";
	} ?>
        <form method='post'>
         <?php print __("Username:"); ?>
	<input type='text' name='user' size='30'
		maxlength="<?php print USERNAME_MAX_LEN; ?>"
		value='<?php
			if (isset($_POST['user'])) {
				print htmlspecialchars($_POST['user'], ENT_QUOTES);
			 } ?>'>
         <?php print __("Password:"); ?>
	<input type='password' name='passwd' size='30'
		maxlength="<?php print PASSWD_MAX_LEN; ?>">
	<input type='submit' class='button'
		 value='<?php  print __("Login"); ?>'>
</form>
<?php } ?>
</span>


