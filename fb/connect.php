<?php
	session_start();

	$appID 	   = "304416966345499";
	$appSecret = "773cbd12a3706e491bc4ad3247b66386";
	$URL 	   = "http://muucms.com/vbregistration/fb/connect.php";

	$code = $_REQUEST["code"];

	if(!$code) {
		if($_GET["login"]) {
			$_SESSION["state"] = md5(uniqid(rand(), TRUE)); 

			$loginURL = "https://www.facebook.com/dialog/oauth?client_id=". $appID ."&redirect_uri=". urlencode($URL) ."&state=". $_SESSION["state"] ."&scope=user_birthday,read_stream";

			?>
			<script type="text/javascript">			
				window.location = "<?php echo $loginURL; ?>";			
			</script>
			<?php
		} else {
			?>
			<!DOCTYPE html>
			<html lang="en">
				<head>
					<meta http-equiv="content-type" content="text/html;charset=utf-8"/>
					<link rel="stylesheet" href="css/login.css" />
				</head>

				<body>
					<div class="inset">
		            	<a class="fb login_button" href="connect.php?login=1">
		                	<div class="logo_wrapper"><span class="logo"></span></div>
		                	<span>Inicia sesión con Facebook</span>
		            	</a>
		        	</div>
				</body>
			</html>		
        	<?php
		}		
	} else {		
		if($_SESSION["state"] and $_SESSION["state"] === $_REQUEST["state"]) {
			$tokenURL = "https://graph.facebook.com/oauth/access_token?client_id=". $appID ."&redirect_uri=". urlencode($URL) ."&client_secret=". $appSecret ."&code=". $code;
	     	$response = file_get_contents($tokenURL);
	     	$params   = NULL;
	     	
	     	parse_str($response, $params);

	     	$_SESSION["access_token"] = $params["access_token"];

	     	$graphURL = "https://graph.facebook.com/me?access_token=". $params["access_token"];
	 
	     	$user = json_decode(file_get_contents($graphURL));
	     	
	     	echo("Hello ". utf8_decode($user->name));
		} else {
			echo "Login fails";
		}
	}