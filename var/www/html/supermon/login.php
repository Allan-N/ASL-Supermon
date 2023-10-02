<?php
//	Updated password management so that bcrypt, etc., works.
//	And, allow arbitrarily complex passwords. kb4fxc 2017-03-19
include("session.inc");
$_SESSION['sm61loggedin'] = false;


if (http_authenticate($_POST['user'], $_POST['passwd'])) {
    print "Login succeeded.";
    $_SESSION['sm61loggedin'] = true;
    $myvar1 = explode('/', $_SERVER['REQUEST_URI']);
    $myvar2 = array_pop($myvar1);
    $my_url = urldecode($myvar2);
    print "<meta http-equiv='REFRESH' content='0;url='.$my_url'>";
} else {
    print "Sorry, login failed.";
}
# print "<pre>"; print_r($_POST); print "</pre>";

//
// crypt_apr1_md5 is from here:
//	http://stackoverflow.com/questions/2994637/how-to-edit-htpasswd-using-php/8786956#8786956
//
// Implements the APR1-MD5 algorithm. Slightly modified by kb4fxc, 2017-03-19
//

function crypt_apr1_md5($plainpasswd, $saltstr = null)
{
    $tmp = "";
    if ($saltstr == null) {
        return "FAIL";
    }

    $salt = substr($saltstr, 6, 8);
    $len = strlen($plainpasswd);
    $text = $plainpasswd.'$apr1$'.$salt;
    $bin = pack("H32", md5($plainpasswd.$salt.$plainpasswd));
    for ($i = $len; $i > 0; $i -= 16) {
        $text .= substr($bin, 0, min(16, $i));
    }
    for ($i = $len; $i > 0; $i >>= 1) {
        $text .= ($i & 1) ? chr(0) : $plainpasswd[0];
    }
    $bin = pack("H32", md5($text));
    for ($i = 0; $i < 1000; $i++) {
        $new = ($i & 1) ? $plainpasswd : $bin;
        if ($i % 3) {
            $new .= $salt;
        }
        if ($i % 7) {
            $new .= $plainpasswd;
        }
        $new .= ($i & 1) ? $bin : $plainpasswd;
        $bin = pack("H32", md5($new));
    }
    for ($i = 0; $i < 5; $i++) {
        $k = $i + 6;
        $j = $i + 12;
        if ($j == 16) {
            $j = 5;
        }
        $tmp = $bin[$i].$bin[$k].$bin[$j].$tmp;
    }
    $tmp = chr(0).chr(0).$bin[11].$tmp;
    $tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
        "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
        "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");

    $pass = '$apr1$'.$salt.'$'.$tmp;
    return "$pass";
}


/**
 * Authenticate a user against a password file generated by Apache's httpasswd
 * using PHP rather than Apache itself.
 *
 * @param  string  $user  The submitted user name
 * @param  string  $pass  The submitted password
 * @param  string  $pass_file  ='.htpasswd' The system path to the htpasswd file
 * @return bool
 */
function http_authenticate($user, $pass, $pass_file = '.htpasswd')
{
    // get the information from the htpasswd file

    if (file_exists($pass_file) && is_readable($pass_file) && ($fp = fopen($pass_file,
            'r'))) { // the password file exists, open it

        while ($line = fgets($fp)) { // for each line in the file, try to find a match.

            $line = preg_replace('`[\r\n]$`', '', $line);
            list($fuser, $fpass) = explode(':', $line);

            if ($fuser == $user) { // the user name matches this line, test the password.

                fclose($fp);

                // First, try DES and Blowfish.
                $test_pw = crypt($pass, $fpass);        // Use original crypt'ed password to supply salt.
                if ($test_pw == $fpass) // authentication success.
                {
                    return true;
                }

                // If we're still testing, try APR1-MD5
                $test_pw = crypt_apr1_md5($pass, $fpass);    // Use original crypt'ed password to supply salt.
                if ($test_pw == $fpass) // authentication success.
                {
                    return true;
                }

                // Password didn't match. Bail out.
                return false;
            }
        }
        fclose($fp);
    }

    return false;
}

?>
