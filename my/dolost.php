<?php
/*
    Prosody Account Manager
    Copyright (C) 2014 Benjamin Sonntag <benjamin@sonntag.fr>, SKhaen <skhaen@cyphercat.eu>   

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU Affero General Public License as
    published by the Free Software Foundation, either version 3 of the
    License, or (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Affero General Public License for more details.

    You should have received a copy of the GNU Affero General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.

    You can find the source code of this software at https://github.com/LaQuadratureDuNet/JabberService
 */

require_once("config.php"); 

$debug=false;
$fields=array("email","login","csrf","cap","url");
$found=0;
foreach($fields as $f) if (isset($_POST[$f])) $found++;
$error=array();
$info=array();

if ($found==5 && $_POST["url"]=="") {
  if ($_SESSION["captcha"]!=$_POST["cap"]) {
    $error[]=_("The captcha is incorrect, please try again"); 
  }
  if (!csrf_check($_POST["csrf"])) {
    $error[]=_("The captcha is incorrect, please try again (2)"); 
  }
  $login=fixlogin($_POST["login"]);
  if ($login!=$_POST["login"] || strlen($login)<3 || strlen($login)>80) {
    $error[]=_("The login must be between 3 and 80 characters long, and must not contains special characters (unicode and accents authorized though)");
  }
  if (count($error)==0) {
    sleep(5); // Let create some artificial waiting for the one who want to restore many accounts ...
    // Does it exist? 
    $already=@mysql_fetch_assoc(mysql_query("SELECT * FROM accounts WHERE jabberid='".addslashes($_POST["login"]."@jabber.lqdn.fr")."';"));
    if (!$already) {
      $error[]=sprintf(_("This account doesn't exist, or have been permanently destroyed. <a href=\"%s\">Click here to create a new account with this login</a>."),"create.php");
    }
    if ($already["disabledate"]!="") {
      $error[]=sprintf(_("This account have been disabled. <a href=\"%s\">Click here to restore it</a>."),"recover.php");
    }
    if ($already["email"]!=hashmail($_POST["email"],$already["email"])) { 
      $error[]=_("This account's email address is not the one you entered. Please try again with another email address.");
    }
    $key=substr(md5($csrf_key."-".$already["id"]."-".$already["jabberid"]),0,16);
    if (count($error)==0) {
      require_once("class.phpmailer.php");
      require_once("class.smtp.php");
      $mail = new PHPMailer;
      $mail->isSMTP();
      $mail->Host = 'localhost';
      $mail->From = $mail_from;
      $mail->FromName = $mail_fromname;
      $mail->addAddress($_POST["email"]);
      $mail->Subject = sprintf(_("Password lost on %s"),$domain);
      $mail->Body = sprintf(_("You receive this email because you created an Jabber Chat account on %s and lost your pasword.\n\nPlease click the link below to reset your password.\n\n%s\n\nIf you didn't asked for this password reminder, please ignore this message or contact us.\n\nThanks a lot for your understanding.\nRegards\nThe Jabber Chat Team\n"),$domain,$rooturl."/recover/".$already["id"]."/".$key);
      if(!$mail->send()) {
	$error[]=_("The email has NOT been sent, please try again later or contact us");
      } else {
	$info[]=_("An email has been sent to the address you entered. Please check your mail and click the link to reset your password");
      }
    } // still no error ? 
  } // no error ?
} // isset ?

