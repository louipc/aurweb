<?php

set_include_path(get_include_path() . PATH_SEPARATOR . '../lib' . PATH_SEPARATOR . '../lang');

include("index_po.inc");
include("pkgfuncs_po.inc"); # Add to handle the i18n of My Packages
include("aur.inc");
set_lang();
check_sid();

# Need to do the authentication prior to sending any HTML (including header)
#
$login_error = "";
if (isset($_REQUEST["user"]) || isset($_REQUEST["pass"])) {
	# Attempting to log in
	#
	if (!isset($_REQUEST["user"])) {
		$login_error = __("You must supply a username.");
	}
	if (!isset($_REQUEST["pass"])) {
		$login_error = __("You must supply a password.");
	}
	if (!$login_error) {
		# Try and authenticate the user
		#

		#md5 hash it
		$_REQUEST["pass"] = md5($_REQUEST["pass"]);
		$dbh = db_connect();
		$q = "SELECT ID, Suspended FROM Users ";
		$q.= "WHERE Username = '" . mysql_real_escape_string($_REQUEST["user"]) . "' ";
		$q.= "AND Passwd = '" . mysql_real_escape_string($_REQUEST["pass"]) . "'";
		$result = db_query($q, $dbh);
		if (!$result) {
			$login_error = __("Error looking up username, %s.",
						array(htmlspecialchars($_REQUEST["user"])));
		} else {
			$row = mysql_fetch_row($result);
			if (empty($row)) {
				$login_error = __("Incorrect password for username, %s.",
						array(htmlspecialchars($_REQUEST["user"])));
			} elseif ($row[1]) {
				$login_error = __("Your account has been suspended.");
			}
		}

		if (!$login_error) {
			# Account looks good.  Generate a SID and store it.
			#
			$logged_in = 0;
			$num_tries = 0;
			while (!$logged_in && $num_tries < 5) {
				$new_sid = new_sid();
				$q = "INSERT INTO Sessions (UsersID, SessionID, LastUpdateTS) ";
				$q.="VALUES (". $row[0]. ", '" . $new_sid . "', UNIX_TIMESTAMP())";
				$result = db_query($q, $dbh);
				# Query will fail if $new_sid is not unique
				#
				if ($result) {
					$logged_in = 1;
					break;
				}
				$num_tries++;
			}
			if ($logged_in) {
				# set our SID cookie
				#
				setcookie("AURSID", $new_sid, 0, "/");
				header("Location: /index.php");
			} else {
				$login_error = __("Error trying to generate session id.");
			}
		}
	}
}

# Any cookies have been sent, can now display HTML
#
html_header();

# Big Top Level Table (Table 1)
echo "<div class=\"pgbox\">\n";
echo "  <div class=\"pgboxtitle\"><span class=\"f3\">AUR ".__("Home")."</span></div>\n";
echo "  <div class=\"frontpgboxbody\">\n";
echo "<table border='0' cellpadding='0' cellspacing='3' width='90%'>\n";
echo "<tr>\n";

# MAIN: Top Left
print "<td class='boxSoft' valign='top'>";

print "<p>".__("Welcome to the AUR! Please read the %hAUR User Guidelines%h and %hAUR TU Guidelines%h for more information.", array('<a href="http://wiki.archlinux.org/index.php/AUR_User_Guidelines">', '</a>', '<a href="http://wiki.archlinux.org/index.php/AUR_Trusted_User_Guidelines">', '</a>'))."<br>";
print __("Contributed PKGBUILDs <b>must</b> conform to the %hArch Packaging Standards%h otherwise they will be deleted!", array('<a href="http://wiki.archlinux.org/index.php/Arch_Packaging_Standards">', '</a>'))."</p>";
print "<p>".__("Remember to vote for your favourite packages!")."<br>";
print __("The most popular packages will be provided as binary packages in [community].")."</p>";

# MAIN: Top Right
print "</td>";
print "<td class='boxSoft' valign='top'>";

# Now present the user login stuff
if (!isset($_COOKIE["AURSID"])) {
	# the user is not logged in, give them login widgets
	#
	if ($login_error) {
		print "<span class='error'>" . $login_error . "</span><br />\n";
	}
	print "<table border='0' cellpadding='0' cellspacing='0' width='100%'>\n";
	print "<form action='/index.php' method='post'>\n";
	print "<tr>\n";
	print "<td>".__("Username:")."</td>";
	print "<td><input type='text' name='user' size='30' maxlength='64'></td>";
	print "</tr>\n";
	print "<tr>\n";
	print "<td>".__("Password:")."</td>";
	print "<td><input type='password' name='pass' size='30' maxlength='32'></td>";
	print "</tr>\n";
	print "<tr>\n";
	print "<td colspan='2' align='right'>&nbsp;<br />";
	print "<input type='submit' class='button'";
	print " value='".__("Login")."'></td>";
	print "</tr>\n";
	print "</form>\n";
	print "</table>\n";

} else {
	print __("Logged-in as: %h%s%h",
			array("<b>", username_from_sid($_COOKIE["AURSID"]), "</b>"));
}

# MAIN: Bottom Left
print "</td>";
print "</tr>";
print "<tr>";
print "<td class='boxSoft' valign='top'>";

#Hey, how about listing the newest pacakges? :D
$q = "SELECT * FROM Packages ";
$q.= "WHERE DummyPkg != 1 ";
$q.= "ORDER BY GREATEST(SubmittedTS,ModifiedTS) DESC ";
$q.= "LIMIT 0 , 10";
$result = db_query($q,$dbh);
# Table 2
print '<table class="boxSoft">';
print '<tr>';
print '<th colspan="2" class="boxSoftTitle" style="text-align: right">';
print ' <a href="/rss2.php"><img src="/images/rss.gif"></a> <span class="f3">'.__("Recent Updates").' <span class="f5"></span></span>';
print '</th>';
print '</tr>';

while ($row = mysql_fetch_assoc($result)) {
	print '<tr>';
        print '<td class="boxSoft">';

        print '<span class="f4"><span class="blue"><a href="/packages.php?do_Details=1&ID='.intval($row["ID"]).'">';
	print $row["Name"]." ".$row["Version"]."</a></span></span>";

        print '</td>';
	print '<td class="boxSoft" style="text-align: right">';

        # figure out the mod string
        $mod_int = intval($row["ModifiedTS"]);
        $sub_int = intval($row["SubmittedTS"]);
        if ($mod_int != 0) {
	  $modstring = gmdate("r", $mod_int);
        }
        elseif ($sub_int != 0) {
          $modstring = '<img src="/images/new.gif"/> '.gmdate("r", $sub_int);
        }
        else {
          $mod_string = "(unknown)";
        }
        print '<span class="f4">'.$modstring.'</span>';
        print '</td>';
	print '</tr>'."\n";
}
print "</td>";
print "</tr>";
print "</table>";
# End Table 2

# MAIN: Bottom Right
print "</td>";
print "<td class='boxSoft' valign='top'>";

# AUR STATISTICS 

$q = "SELECT count(*) FROM Packages,PackageLocations WHERE Packages.LocationID = PackageLocations.ID AND PackageLocations.Location = 'unsupported'";
$result = db_query($q, $dbh);
$row = mysql_fetch_row($result);
$unsupported_count = $row[0];

$q = "SELECT count(*) FROM Packages,PackageLocations WHERE Packages.LocationID = PackageLocations.ID AND PackageLocations.Location = 'community'";
$result = db_query($q, $dbh);
$row = mysql_fetch_row($result);
$community_count = $row[0];

$q = "SELECT count(*) from Users";
$result = db_query($q, $dbh);
$row = mysql_fetch_row($result);
$user_count = $row[0];

$q = "SELECT count(*) from Users,AccountTypes WHERE Users.AccountTypeID = AccountTypes.ID AND AccountTypes.AccountType = 'Trusted User'";
$result = db_query($q, $dbh);
$row = mysql_fetch_row($result);
$tu_count = $row[0];

$targstamp = intval(strtotime("-7 days"));
$q = "SELECT count(*) from Packages WHERE (Packages.SubmittedTS >= $targstamp OR Packages.ModifiedTS >= $targstamp)";
$result = db_query($q, $dbh);
$row = mysql_fetch_row($result);
$update_count = $row[0];

# Added the user statistcs.
# Added by: dsa <dsandrade@gmail.com>
$user = username_from_sid($_COOKIE["AURSID"]);

if (!empty($user)) {
    $q = "SELECT count(*) FROM Packages,PackageLocations,Users WHERE Packages.MaintainerUID = Users.ID AND Packages.LocationID = PackageLocations.ID AND PackageLocations.Location = 'unsupported' AND Users.Username='".mysql_real_escape_string($user)."'";
    $result = db_query($q, $dbh);
    $row = mysql_fetch_row($result);
    $maintainer_unsupported_count = $row[0];
    
    $q = "SELECT count(*) FROM Packages,Users WHERE Packages.OutOfDate = 1 AND Packages.MaintainerUID = Users.ID AND Users.Username='".mysql_real_escape_string($user)."'";
    $result = db_query($q, $dbh);
    $row = mysql_fetch_row($result);
    $flagged_outdated = $row[0];
    
    print "<table class='boxSoft'>";
    
    print "<tr>";
    print "<th colspan='2' class='boxSoftTitle' style='text-align: right'>";
    print "<span class='f3'>".__("My Statistics")."</span>";
    print "</th>";
    print "</tr>";
    
    # Number of packages in unsupported
    print "<tr>";
    print "<td class='boxSoft'>";
    print "<span class='f4'>".__("Packages in unsupported")."</span>";
    print "</td>";
    print "<td class='boxSoft'><span class='f4'>$maintainer_unsupported_count</span></td>";
    print "</tr>";
    
    # If the user is a TU calculate the number of the packages
    $atype = account_from_sid($_COOKIE["AURSID"]);
    
    if ($atype == 'Trusted User') {    
        $q = "SELECT count(*) FROM Packages,PackageLocations,Users WHERE Packages.MaintainerUID = Users.ID AND Packages.LocationID = PackageLocations.ID AND PackageLocations.Location = 'community' AND Users.Username='".mysql_real_escape_string($user)."'";
        $result = db_query($q, $dbh);
        $row = mysql_fetch_row($result);
        $maintainer_community_count = $row[0];
        
        print "<tr>";
        print "<td class='boxSoft'>";
        print "<span class='f4'>".__("Packages in [community]")."</span>";
        print "</td>";
        print "<td class='boxSoft'><span class='f4'>$maintainer_community_count</span></td>";
        print "</tr>";
    }
    
    # Number of outdated packages    
    print "<tr>";
    print "<td class='boxSoft'>";
    print "<span class='f4'>".__("Out-of-date")."</span>";
    print "</td>";
    print "<td class='boxSoft'><span class='f4'>$flagged_outdated</span></td>";
    print "</tr>";    
        
    print "</table><br />";
}

print "<table class='boxSoft'>";

print "<tr>";
print "<th colspan='2' class='boxSoftTitle' style='text-align: right'>";
print "<span class='f3'>".__("Statistics")."</span>";
print "</th>";
print "</tr>";

print "<tr>";
print "<td class='boxSoft'>";
print "<span class='f4'>".__("Packages in unsupported")."</span>";
print "</td>";
print "<td class='boxSoft'><span class='f4'>$unsupported_count</span></td>";
print "</tr>";

print "<tr>";
print "<td class='boxSoft'>";
print "<span class='f4'>".__("Packages in [community]")."</span>";
print "</td>";
print "<td class='boxSoft'><span class='f4'>$community_count</span></td>";
print "</tr>";

print "<tr>";
print "<td class='boxSoft'>";
print "<span class='f4'>".__("Packages added or updated in the past 7 days")."</span>";
print "</td>";
print "<td class='boxSoft'><span class='f4'>$update_count</span></td>";
print "</tr>";

print "<tr>";
print "<td class='boxSoft'>";
print "<span class='blue'><span class='f4'>".__("Registered Users")."</span></span>";
print "</td>";
print "<td class='boxSoft'><span class='f4'>$user_count</span></td>";
print "</tr>";

print "<tr>";
print "<td class='boxSoft'>";
print "<span class='f4'>".__("Trusted Users")."</span>";
print "</td>";
print "<td class='boxSoft'><span class='f4'>$tu_count</span></td>";
print "</tr>";

print "</table>";

# Close out the right column
print "  </td>";
print "</tr>\n";
print "</table>\n";
echo "</div>\n";
# End Table 1
echo "<span class='f4'>".__("DISCLAIMER: Unsupported PKGBUILDs are user produced content, by downloading them you agree to do so at your own risk.")."</span>";
echo "  </div>";
echo "</div>";
html_footer(AUR_VERSION);
# vim: ts=2 sw=2 noet ft=php
?>
