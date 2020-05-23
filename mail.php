<?php
// We only do stuff if there's a POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {
	try {
		// Sanitize user input
		$_POST = array_map("strip_tags", $_POST);
		$_POST = array_map("htmlspecialchars", $_POST);

		// Check if at least "subject" and "email" exist in the $_POST vars
		if ( !isset($_POST["subject"] ) || !isset($_POST["email"]) )
			json(false, "Not all required fields are present");

		// Check if a valid e-mail address is provided
		if ( !filter_var($_POST["email"], FILTER_VALIDATE_EMAIL) )
			json(false, "Invalid e-mail address");


		// Subject
		$subject = $_POST["subject"];
		unset($_POST["subject"]);


		// If "message" exists, replace \n with <br>
		if ( isset($_POST["message"]) )
			$_POST["message"] = str_replace("\n", "<br>", $_POST["message"]);


		// reCaptcha
		$recaptcha = "0";
		$rcpScore = "0.5";

		if ($recaptcha == "3") {
			if (!isset($_POST["g-recaptcha-response"])) {
				json(false, "POST did not contain g-recaptcha-response");
			}

			// Build POST request and execute it
			$rcpUrl = 'https://www.google.com/recaptcha/api/siteverify';
			$rcpSecret = "";
			$rcpResponse = $_POST["g-recaptcha-response"];
			unset($_POST["g-recaptcha-response"]);	// Remove this, as we don't want this to end up in the template ;)
			$rcpResponse = file_get_contents($rcpUrl . "?secret=" . $rcpSecret . "&response=" . $rcpResponse);
			$rcpResponse = json_decode($rcpResponse, true);

			// Check response
			if (!$rcpResponse["success"]) {
				json(false, "Invalid reCAPTCHA token");
			}

			// Check score
			if ($rcpResponse["score"] < intval($rcpScore)) {
				json(false, "Request did not pass reCAPTCHA");
			}
		}


		// Get the template in order (Mobirise puts an actual template in there)
		$template = "Hi,<br><br>You have received a new message from your website.<br><br>{formdata}<br><br>Date: {date}<br>Remote IP: {ip}<br><br>Have a nice day.";

		// Extract all variables from the template
		preg_match_all("/\{([a-zA-Z0-9_-]+)\}/", $template, $matches);

		// Check what postvars don't exist in the template vars and put that in {formdata}
		$formdata = "";
		foreach ($_POST as $k => $v) {
			if ( !in_array($k, $matches[1]) ) {
				// Implode array to make it look better
				if (is_array($v))
					$v = implode(", ", $v);

				// Replace some chars
				$k = str_replace("_", " ", $k);
				$v = str_replace("\n", "<br>", $v);

				// Add to formdata
				$formdata .= ($formdata ? "<br><br>" : "") . ucfirst($k) . ":<br>" . $v;
			}
		}
		$_POST["formdata"] = $formdata;

		// Add some additional variables to the play
		$_POST["ip"] = $_SERVER["REMOTE_ADDR"];
		$_POST["date"] = date('Y-m-d H:i:s');

		// Loop through all variables of the template
		foreach($matches[1] as $val) {
			// Try to replace all variables in the template with the corresponding postvars (if they exist)
			$template = str_replace("{" . $val . "}", (isset($_POST[$val]) ? $_POST[$val] : ""), $template);
		}


		// Body
		$body = "<html><body>" . $template . "</body></html>";

		// Sender as From Email or predefined From Email
		$from = ("0" == "1" ? $_POST["email"] : "melissa@nextpropertygroup.com.au");

		// Sender as From Name or predefined From Name
		$fromName = "";
		if ("0" == "1") {
			$fromName = "{name}";

			// Extract variable names from the name field
			preg_match_all("/\{([a-zA-Z0-9_-]+)\}/", $fromName, $nameMatches);

			// Loop through all variables of the name field
			foreach($nameMatches[1] as $val) {
				// Try to replace all variables with the corresponding postvars (if they exist)
				$fromName = str_replace("{" . $val . "}", (isset($_POST[$val]) ? $_POST[$val] : ""), $fromName);
			}
		}

		// Double check if fromName isn't empty, otherwise fill it with the predefined name
		$fromName = trim($fromName);
		$fromName = ($fromName ? $fromName : "Melissa Schembri");
		$fromName = preg_replace('/(["“”‘’„”«»]|&quot;)/', "", $fromName, -1);


		// Set headers
		$headers  = "MIME-Version: 1.0\r\n";
		$headers .= "Content-type:text/html;charset=UTF-8\r\n";
		$headers .= "From: \"" . $fromName . "\" <" . $from . ">\r\n";
		$headers .= "Reply-To: " . $from;

		// Send the mail
		if ( mail("melissa@nextpropertygroup.com.au", $subject, $body, $headers) ) {
			json(true);
		}
	}
	catch (Exception $e) {
		json(false, "An error occured");
	}
}

// Function to return JSON
function json($success, $msg="") {
	$arr = array("success" => $success);

	if ($msg)
		$arr["message"] = $msg;

	// Send the JSON and stop the presses
	header("Content-type: application/json");
	echo json_encode($arr);
	die();
}
?>