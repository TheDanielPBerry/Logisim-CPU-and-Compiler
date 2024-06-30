<?php
//Test to see if I can get a file line count from memory
$FILENAME = "main.dink";

$text = file_get_contents($FILENAME);

$lineCount = 0;
$filePointer = 0;
do {
	$filePointer = strpos($text, "\n", $filePointer);
	if($filePointer !== false) {
		$lineCount++;
		$filePointer++;
	}
} while($filePointer !== false);
echo $lineCount;

