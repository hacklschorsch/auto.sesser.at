<?php

// Scope: Download single file from GitHub. Web-Hook to be called by GitHub.
// 
// Author: Florian Sesser
// License: Public domain / CC0
// Rant: The PHP curl API is the worst I have seen in years.
//       It's bad even by PHP standards.

// This makes PHP return the exception string and set an HTTP error code.
// "Execution will stop after the callback is called." say the docs.
function exception_handler($exception) {
	http_response_code(500);
	echo "Exception: " , $exception->getMessage(), "\n";
	flock($fp, LOCK_UN); // release the lock
}
set_exception_handler('exception_handler');


// Open file for writing
if (!($fp = fopen("index.html", "c"))) {
	throw new Exception("Could not open file.");
}

// Try to obtain exclusive lock, don't block
if (!flock($fp, LOCK_EX | LOCK_NB)) {
	throw new Exception("Could not lock file.");
} else {
	// Download file
	$ch = curl_init("https://raw.githubusercontent.com/hacklschorsch/auto.sesser.at/main/index.html");
	curl_setopt($ch, CURLOPT_FAILONERROR, true); // Without this, curl_errno does not work (???)
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$c = curl_exec($ch);

	if (curl_errno($ch)) {
		throw new Exception("Could not download file, error: " . curl_error($ch));
	} else {
		if (!fwrite($fp, $c)) {
			throw new Exception("Could not write to file.");
		}
		if (!ftruncate($fp, strlen($c))) {
			throw new Exception("File truncate failed.");
		}
		if (!file_put_contents("index.html.gz", gzencode($c, 9), LOCK_EX)) {
			throw new Exception("Could not write compressed copy.");
		}
	}

	// Rate limit: Sleep for ~ pi seconds.
	usleep(3141593);

	flock($fp, LOCK_UN); // release the lock
}

fclose($fp);

echo "OK\n";

?>
