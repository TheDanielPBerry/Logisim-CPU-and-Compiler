<?php
error_reporting(E_ERROR);
	$sourceCode = "
	CONST a: word = 1
	CONST b: char = 69
	CONST c: word= 3
	CONST d: word=4
	SET a = 5
	SET a[1] =6
	SET b= a[2]
	SET b=a[c]
	SET a[b]  =   c
	SET a[b]  =  c[b]
	";
	$sourceCode = "CONST STACK_POINTER: *word = 0xFF00
	DEFINE string: char = \"Hello\"
	DEFINE str_pointer: *char = &string
	DEFINE a: char = 'A'
	DEFINE index: word = 2

	DEFINE main: char = 0x00
	SET &string[index] = 'n'
	SET str_pointer[4] = a
	SET a = str_pointer[index]

	SET index = str_pointer[2]
	";
	$sourceCode = "
	DEFINE j: char = \"Hello\"
	DEFINE j: char = \"Walle\"

	FUNC replaceCharAt(test: *char, index: word, replace: char)
		SET test[index] = replace
	ENDFUNC
	FUNC str_copy(src: *char, dest: *char)
		DEFINE index: word = 0

		SET test[index] = replace
	ENDFUNC

	FUNC main()
		CALL replaceCharAt(&j, 2, 'K')
	ENDFUNC


	";
	$sourceCode = "
	DEFINE string: char = \"Hola Mundo\"
	CONST putchar: *char = 0xFF02

	FUNC print(str: *char)
		DEFINE index: word = 0
		WHILE str[index] != 0b
			SET putchar = str[index]
		ENDWHILE
	ENDFUNC

	FUNC main()
		CALL print(&string)
	ENDFUNC


	";
	$sourceCode = "
	DEFINE string: char = \"Hola Mundo\"
	CONST putchar: word = 0xFF02

	FUNC main()
		DEFINE index: word = 0
		WHILE &string[index] != 0b
			SET putchar = &string[index]
		ENDWHILE
	ENDFUNC


	";
	

	$TYPES = [
		'char' => 1,
		'word' => 2,
		'*char' => 2,
		'*word' => 2,
		'func' => 2
	];
	$ADDRESS_TYPES = [
		'immediate' => function($var) {
			if($var['type'] == '*char') {
				return '1';
			}
			return $var['size'] == 1 ? 'E' : 'F';
 		},
		'const' => function($var) {
			if(in_array($var['type'], ['*char', '*word'])) {
				return '1';
			}
			return $var['size'] == 1 ? 'E' : 'F';
 		},
		'global' => function($var) {
			return '1';
		},
		'stack' => function($var) {
			return '2';
		},
		'index' => function($var) {
			return '3';
		}
	];

	$VARIABLE_CHAR_SET = "[A-Za-z][A-Za-z_\d]*";

	$CMP_FLAGS = [
		'==' => '01',
		'>' => '02',
		'>=' => '03',
		'<' => '04',
		'<=' => '05',
		'!=' => '08'
	];

	$result = [
		'FA', 'FF', '00',
		'F9', ['main', 'top'], ['main', 'bottom']
	];

	$stack = [
	];
	$stackFrame = &$stack;
	$stackTracker = [];
	
	$lineNumber = 0;

	$lines = expTrim("\n", $sourceCode);
	foreach($lines as $line) {
		$lineNumber++;
		$line = trim(explode('//', $line)[0]);
		$words = expTrim(' ', $line);
		if(COUNT($words) > 0) {
			$operation = strtoupper($words[0]);
			$args = preg_replace("/^[A-Za-z]+ /", "", $line);

			switch($operation) {
				case "RAW":
					$val = parseLiteral($args);
					$result[] = top(['val' => $val]);
					$result[] = bottom(['val' => $val]);
					break;

				case "RAWBYTE":
					$val = parseLiteral($args);
					$result[] = bottom(['val' => $val]);
					break;

				case "CONST":
					[$leftExpr, $rightToken] = expTrim('=', $args);
					[$varName, $type] = expTrim(':', $leftExpr);
					//Valdiate varName
					preg_match("/^[a-zA-Z]([A-Za-z_\d]+)?$/", $varName, $varMatch);
					if(count($varMatch) == 0) {
						throw new Exception("\nLine Number: " . $lineNumber . "  Invalid const name " . $varName . "\n");
					}
					$stackFrame[$varName] = [
						'val' => parseLiteral($rightToken),
						'refs' => [],
						'type' => $type,
						'address_type' => 'const',
						'size' => $TYPES[$type]
					];
					break;


				case "DEFINE":
					[$leftExpr, $rightToken] = expTrim('=', $args);
					[$varName, $type] = expTrim(':', $leftExpr);
					//Valdiate varName
					preg_match("/^[a-zA-Z_]([A-Za-z_\d]+)?$/", $varName, $varMatch);
					if(count($varMatch) == 0) {
						throw new Exception("\nLine Number: " . $lineNumber . "  Invalid variable name " . $varName . "\n");
					}
					if(count($stackTracker) == 0) {
						$address_type = 'global';
						$val = parseLiteral('u' . count($result));
					} else {
						$address_type = 'stack';
						$stackFrame['frame']['val'] += $TYPES[$type];
						$val = end($stackFrame)['val'] - $TYPES[$type];	//Next index
						$src = $TYPES[$type] > 1 ? 'F' : 'E';
						$assignmentRegister = $TYPES[$type] > 1 ? '7' : '5';
						$result[] .= $src . $assignmentRegister;
					}
					
					$stackFrame[$varName] = [
						'val' => $val,
						'refs' => [],
						'type' => $type,
						'address_type' => $address_type,
						'size' => $TYPES[$type]
					];
					if(empty($rightToken)) {
						$rightToken = '0';
					}
					$token = findToken($rightToken);
					if($token['address_type'] == 'immediate') {
						$hex = $token['val'];
						if(strlen($hex) > 4) {
							for($i=0; $i<strlen($hex); $i+=2) {
								$result[] = str_pad(substr($hex, $i, 2), 2, '0');
							}
						} else {
							if($TYPES[$type] > 1) {
								$result[] = top(['val' => $hex]);
							}
							$result[] = bottom(['val' => $hex]);
						}
					} else {
						if($TYPES[$type] > 1) {
							$result[] = [&$token, 'top'];
						}
						$result[] = [&$token, 'bottom'];
					}
					
					if(count($stackTracker) > 0) {
						$result[] = $assignmentRegister . '2';
						$result[] = [$varName, 'top'];
						$result[] = [$varName, 'bottom'];
					}
					break;





				case "SET":	//Variable Assignment
					[$leftToken, $rightToken] = expTrim('=', $args);
					
					preg_match("/^(\*)?(&?[^\s\[\]]+)(\[([^\s\[\]]+)\])?$/", $leftToken, $leftMatches);
					$left = findToken($leftMatches[2]);

					[$assignmentReg, $right] = fetchToken($rightToken, $left);
					

					//Move from register to saved variable  
					if(count($leftMatches) > 3) {
						$indexRegister = '8'; //D is default register to hold index+var
						if($left['address_type'] == 'const') {
							$opCode = 'F';
						} else {
							$opCode = $ADDRESS_TYPES[$left['address_type']]($left);
						}
						$opCode .= 'B';	//Load into Index register
						$result[] = $opCode;
						if(count($stackTracker) > 0 && $left['address_type'] == 'stack') {
							$result[] = [$leftMatches[2], 'top'];
							$result[] = [$leftMatches[2], 'bottom'];
						} else {
							$result[] = [&$left, 'top'];
							$result[] = [&$left, 'bottom'];
						}

						$index = findToken($leftMatches[4]);
						if($index['address_type'] == 'immediate') {
							$opCode = $assignmentReg . '3';
						} else {
							$opCode = $ADDRESS_TYPES[$index['address_type']]($index);
							$opCode .= $indexRegister;
						}
						$result[] = $opCode;
						if(count($stackTracker) > 0 && $index['address_type'] == 'stack') {
							$result[] = [$leftMatches[4], 'top'];
							$result[] = [$leftMatches[4], 'bottom'];
						} else {
							$result[] = [&$index, 'top'];
							$result[] = [&$index, 'bottom'];
						}
						
						$opCode = $assignmentReg;
						if($index['address_type'] != 'immediate') {
							$opCode .= '4';
							$result[] = $opCode;
							
							$result[] = "B" . $indexRegister;
							$result[] = "30";
						}

					} else {
						$opCode = $assignmentReg;
						$opCode .= $ADDRESS_TYPES[$left['address_type']]($left);
						$result[] = $opCode;
						if(count($stackTracker) > 0 && $left['address_type'] == 'stack') {
							$result[] = [$leftToken, 'top'];
							$result[] = [$leftToken, 'bottom'];
						} else {
							$result[] = [&$left, 'top'];
							$result[] = [&$left, 'bottom'];
						}
					}
					
					break;
				

				case "JE":
					$result[] = "ec";
					$result[] = "01";	//SET JE

					$entry = findToken($args);
					$result[] = $ADDRESS_TYPES[$entry['address_type']]($entry) . '9';
					$result[] = [&$entry, 'top'];
					$result[] = [&$entry, 'bottom'];
					break;

				case "GOTO":
					$result[] = "ec";
					$result[] = "00";	//Clear out the flags
				case "JMP":
					$entry = findToken($args);
					$result[] = $ADDRESS_TYPES[$entry['address_type']]($entry) . '9';
					$result[] = [&$entry, 'top'];
					$result[] = [&$entry, 'bottom'];
					break;

				case "WHILE":
				case "LOOP":
					$comparator = parseBooleanExpr($args);
					$loopCount = count(array_filter($stackFrame, fn($var) => $var['type'] == 'loop'));
					$loopId = 'loop-' . $loopCount;
					$loop = [
						'exit_id' => $loopId . '-exit',
						'entry_id' => $loopId . '-entry',
						'entry' => parseLiteral(count($result))
					];
					$result[] = 'EC';
					$result[] = $CMP_FLAGS[$comparator];


					$result[] = 'F9';
					$result[] = [$loop['exit_id'], 'top'];
					$result[] = [$loop['exit_id'], 'bottom'];

					$loop['exit'] = parseLiteral(count($result));
					$stackTracker[] = $loop;

					break;
				
				case "ENDWHILE":
				case "ENDLOOP":
					$loop = array_pop($stackTracker);
					
					$result[] = 'EC';
					$result[] = '00';
					$result[] = 'F9';
					$result[] = [$loop['entry_id'], 'top'];
					$result[] = [$loop['entry_id'], 'bottom'];

					$stackFrame[$loop['entry_id']] =  [
						'val' => $loop['entry'],
						'refs' => [],
						'type' => 'loop',
						'address_type' => 'immediate',
						'size' => 2
					];
					
					$stackFrame[$loop['exit_id']] =  [
						'val' => parseLiteral(count($result)),
						'refs' => [],
						'type' => 'loop',
						'address_type' => 'immediate',
						'size' => 2
					];
					
					break;

				
				case "CALL":
					preg_match("/^(?<funcName>[a-zA-Z_](?:[A-Za-z_\d]*)?)\((?<args>[\&A-Za-z_\d, \:\']*)\)$/", $args, $call);
					$func = findToken($call['funcName']);
					if($func['type'] == 'func') {
						$params = array_filter($func['stack'], fn($var) => $var['param']);
						$args = expTrim(',', $call['args']);
						foreach($args as $index => $argToken) {
							$param = $func['stack'][array_keys($params)[$index]];
							[$assignmentReg, $arg] = fetchToken($argToken, $param);
							
							//Move fetched argument onto stack
							$result[] = $assignmentReg . '2';
							$result[] = [$param, 'top'];
							$result[] = [$param, 'bottom'];
						}

						$result[] = 'EC';	//Clear jmp flags
						$result[] = '00';

						$result[] = 'C8';	//Add 8 to the program counter into D
						$result[] = '90';
						$result[] = '36';

						$result[] = '82';	//Store the program counter as the return address
						$result[] = 'FF';
						$result[] = 'FE'; 

						$result[] = 'F9';	//JMP to the new function entry
						$result[] = [&$func, 'top'];
						$result[] = [&$func, 'bottom'];	
					} else {
						throw new Exception("\nLine Number: " . $lineNumber . "  Function not found " . $varName . "\n");
					}
					
					break;

					
				
				case "FUNC":
					preg_match("/^(?<funcName>[a-zA-Z_](?:[A-Za-z_\d]*)?)\((?<params>[A-Za-z_\d, \:\*]*)\)(?: *\:* *)(?<returnType>[\*a-w]*)$/", $args, $funcMatch);
					if(count($funcMatch) > 2) {
						$stackFrame[$funcMatch['funcName']] = [
							'val' => parseLiteral(count($result)),
							'refs' => [],
							'type' => 'func',
							'address_type' => 'const',
							'size' => 2,
							'stack' => []
						];

						$stackTracker[] = &$stackFrame;
						$temp = &$stackFrame[$funcMatch['funcName']]['stack'];
						unset($stackFrame);
						$stackFrame = &$temp;
						unset($temp);
						
						$stackFrame['frame'] = [
							'val' => 2,
							'refs' => [],
							'type' => 'word',
							'address_type' => 'stack',
							'size' => 2,
							'name' => $funcMatch['funcName'],
							'entry' => count($result)
						];
						if(!empty($funcMatch['returnType'])) {
							if(!in_array($funcMatch['returnType'], array_keys($TYPES))) {
								throw new Exception("\n\tLine Number: " . $lineNumber . "  Invalid return type " . $funcMatch['returnType'] . "\n");
							}

							$stackFrame['frame']['val'] += $TYPES[$funcMatch['returnType']];
							$stackFrame['return_val'] = [
								'val' => $stackFrame['frame']['val'] * -1,
								'refs' => [],
								'type' => $funcMatch['returnType'],
								'address_type' => 'stack',
								'size' => $TYPES[$funcMatch['returnType']]
							];
						}

						$params = expTrim(',', $funcMatch['params']);
						foreach($params as $param) {
							if(empty($param)) continue;
							[$varName, $type] = expTrim(':', $param);
							preg_match("/^[a-zA-Z_](?:[A-Za-z_\d]+)?$/", $varName, $varMatch);
							if(count($varMatch) == 0) {
								throw new Exception("\nLine Number: " . $lineNumber . "  Invalid variable name " . $varName . "\n");
							}

							$stackFrame['frame']['val'] += $TYPES[$type];
							$stackFrame[$varName] = [
								'name' => $varName,
								'val' => $stackFrame['frame']['val'] * -1,
								'refs' => [],
								'type' => $type,
								'address_type' => 'stack',
								'size' => $TYPES[$type],
								'param' => true
							];
						}

						$result[] = 'E5';
						$result[] = [&$stackFrame['frame'], function($frame) {
							return bottom(['val' => str_pad(dechex($frame['size']), 2, '0', STR_PAD_LEFT)]);
						}];
						$result[] = "CA";
						$result[] = "A5";
						$result[] = "40";
						
					} else {
						throw new Exception("\n\tLine Number: " . $lineNumber . " Invalid FUNC Definition\n");
					}
					break;


				case "ENDFUNC":
					//Calculate all local vars and param offsets based on text
					$stackFrame['frame']['size'] = $stackFrame['frame']['val'];
					
	 				for($i = $stackFrame['frame']['entry']; $i < count($result); $i++) {
						$byte = $result[$i];
						if(is_array($byte)) {
							[$ref, $post] = $byte;
							if(gettype($ref) == 'string' && in_array($ref, array_keys($stackFrame))) {
								$result[$i] = $post(['val' => parseLiteral($stackFrame['frame']['size'] +  $stackFrame[$ref]['val'])]);
							}
						}
					}
					foreach($stackFrame as $key => $var) {
						if($key != 'frame') {
							$stackFrame[$key]['val'] = parseLiteral($stackFrame[$key]['val']);
						}
					}
					
					//Move stack pointer back down
					$result[] = 'E5';	//load A with frame size
					$result[] = str_pad(dechex($stackFrame['frame']['size']), 2, '0', STR_PAD_LEFT);
					$result[] = "CA";
					$result[] = "A5";
					$result[] = "30";	//Add it to the pointer

					$result[] = 'EC';	//Clear jmp flags
					$result[] = '00';

					$result[] = '29';	//Go to return address
					$result[] = 'FF';
					$result[] = 'FE'; 
					//Go to return value
					
					unset($stackFrame);
					$stackFrame = &$stackTracker[count($stackTracker)-1];
					unset($stackTracker[count($stackTracker)-1]);
					break;
			}
		}
	}
	//print_r($stack); exit;

	if(count($stackTracker) > 1) {
		throw new Exception("\n\Unclosed Functions\n");
	}


	function fetchToken($rightToken, $left, $assignmentReg = null) {
		global $result;
		global $TYPES;
		global $stackFrame;
		global $stackTracker;
		global $ADDRESS_TYPES;
		if(!empty($assignmentReg)) {
			$assignmentSize = in_array($assignmentReg, ['5', '6']) ? 1 : 2;
		}
		

		preg_match("/(\*)?(&?[^\s\[\]]+)(\[([^\s\[\]]+)\])?/", $rightToken, $rightMatches);
		$right = findToken($rightMatches[2]);
		
		$indexRegister = '8'; //D is default register to hold index+var
		//If the token is indexed, then setup indexed fetch
		if(count($rightMatches) > 3) {
			$index = findToken($rightMatches[4]);
			if($right['address_type'] == 'const') {
				$opCode = 'F';
			} else {
				$opCode = $ADDRESS_TYPES[$right['address_type']]($right);
			}
			$opCode .= 'B';
			$result[] = $opCode;
			$result[] = [&$right, 'top'];
			$result[] = [&$right, 'bottom'];
			
			$assignmentSize ??= maxAssignmentSize($right, $left);
			$assignmentReg ??= ($assignmentSize <= 1 ? '5' : '7');
			
			
			
			if($index['address_type'] == 'immediate') {
				$opCode = '3' . $assignmentReg;
			} else {
				$opCode = $ADDRESS_TYPES[$index['address_type']]($index);
				$opCode .= $indexRegister;
			}
			$result[] = $opCode;
			if($index['address_type'] == 'stack') {
				$result[] = [$rightMatches[4], 'top'];
				$result[] = [$rightMatches[4], 'bottom'];
			} else {
				$result[] = [&$index, 'top'];
				$result[] = [&$index, 'bottom'];
			}
			
			if($index['address_type'] != 'immediate') {
				$opCode = '4';
				$opCode .= $assignmentReg;
				$result[] = $opCode;
				$result[] = 'B' . $indexRegister;
				$result[] = "30";
			}
		} else {
			//Figure out if working with 1 or 2 bytes
			$assignmentSize ??= maxAssignmentSize($right, $left);
			if($right['addressModified']) {
				$assignmentSize = 2;
			}
			$assignmentReg ??= $assignmentSize == 1 ? '5' : '7';
			if($right['address_type'] == 'immediate') {
				//If immediate base instant size on the destination
				$opCode = $ADDRESS_TYPES[$right['address_type']]($left);
			} else {
				$opCode = $ADDRESS_TYPES[$right['address_type']]($right);
			}
			$opCode .= $assignmentReg;
			$result[] = $opCode;
			if(count($stackTracker) > 0 && $right['address_type'] == 'stack') {
				$result[] = [$rightToken, 'top'];
				$result[] = [$rightToken, 'bottom'];
			} else {
				if($right['address_type'] != 'immediate' || $assignmentSize > 1){
					$result[] = [&$right, 'top'];
				}
				$result[] = [&$right, 'bottom'];
			}
		}
		return [$assignmentReg, $right];
	}

	//
	//
	//
	//
	//
	//

	

	function maxAssignmentSize($left, $right) {
		if(strpos($left['type'], 'char') !== false && (strpos($right['type'], 'char') !== false) || $right['address_type'] == 'immediate') {
			return 1;
		}
		return max($left['size'], $right['size']);
	}

	function parseBooleanExpr($expr) {
		global $result;
		global $lineNumber;

		[$leftToken, $rightToken] = preg_split("/[\!\=\>\<]{1,2}/", $expr);
		$leftToken = trim($leftToken);
		$rightToken = trim($rightToken);
		
		[$leftReg, $left] = fetchToken(trim($leftToken), ['type' => '*char']);
		$rightReg = ($leftReg == '5') ? '6' : '8';
		$size = ($leftReg == '5') ? 1 : 2;
		[$rightReg, $right] = fetchToken(trim($rightToken), ['size' => $size], $rightReg);


		$result[] = 'C0'; //Compare the 2 values
		$result[] = $leftReg . $rightReg;
		$result[] = '10';
		
		preg_match("/\S+\s*([\!\=\>\<]{1,2})\s*\S+/", $expr, $boolMatch);
		if(count($boolMatch) <= 1) {
			throw new Exception("\nLine Number: " . $lineNumber . "  Invalid boolean expression: \"" . $expr . "\"\n");
		}
		return $boolMatch[1];
	}

	function parseLiteral($literal) {

		//Match to an ascii character literal
		preg_match("/^\'(\S)\'$/", $literal, $charMatch);
		//Match to a string literal
		preg_match("/^\"(.*)\"$/", $literal, $strMatch);
		//Match to an integer
		preg_match("/^(u(?!-))?(-?\d+)$/", $literal, $numMatch);
		//Match to a hex string
		preg_match("/^0[xX]([A-Fa-f\d]{1,4})$/", $literal, $hexMatch);
		
		if(count($charMatch) > 1) {
			$charMatch[1] = str_replace("\\n", "\n", $charMatch[1]);
			$charMatch[1] = str_replace("\\0", "\0", $charMatch[1]);
			return bin2hex($charMatch[1]);
		} if(count($strMatch) > 1) {
			$strMatch[1] = str_replace("\\0", "\0", $strMatch[1]);
			$strMatch[1] = str_replace("\\n", "\n", $strMatch[1]);
			return bin2hex($strMatch[1]) . '00';
		} else if(is_numeric($numMatch[2])) {
			$pad = '0';
			if($numMatch[1] != 'u') {
				$pad = intval($numMatch[2]) < 0 ? 'F' : '0';
			}
			$hexString = substr(dechex($numMatch[2]), -4);
			return str_pad($hexString, 4, $pad, STR_PAD_LEFT);
		} else if(count($hexMatch) > 1) {
			$pad = '0';
			return str_pad($hexMatch[1], 4, $pad, STR_PAD_LEFT);
		}
		return false;
	}

	function findToken($token) {
		global $stack;
		global $stackFrame;
		global $TYPES;
		global $lineNumber;

		if(substr($token, 0, 1) == '&') {
			$token = substr($token, 1);
			$addressType = 'const';
			$size = 2;
			$addressModified = true;
		}

		$return = $stackFrame[$token];
		$return ??= $stack[$token];

		if(is_null($return)) { 
			if(substr($token, -1) == 'b') {
				$token = substr($token, 0, -1);
				$size = 1;
			}
			$literal = parseLiteral($token);
			if($literal) {
				$type = strlen($literal) / 2 == 1 ? 'char' : 'word';
				$return = [
					'val' => $literal,
					'type' => $type,
					'size' => $TYPES[$type],
					'address_type' => 'immediate'
				];
			} else {
				throw new Exception("\nLine Number: " . $lineNumber . "  Undefined: \"" . $token . "\"\n");
			}
		}

		if(!empty($addressType)) {
			$return['address_type'] = $addressType;
		}
		if(!empty($size)) {
			$return['size'] = $size;
		}
		if($addressModified) {
			$return['addressModified'] = true;
		}

		return $return;
	}
	//Post Process
		

	function top($ref) {
		return substr($ref['val'], 0, 2);
	}

	function bottom($ref) {
		return substr($ref['val'], -2);
	}

	foreach($result as $byte) {
		if(is_array($byte)) {
			[$ref, $post] = $byte;
			if(gettype($ref) == 'string') {
				$ref = $stack[$ref];
			}
			if(!empty($post)) {
				echo $post($ref);
			} else {
				echo $ref['val'];
			}
		} else {
			echo $byte;
		}
		echo ' ';
	}

	function expTrim($delimiter, $str) {
		return array_map(function($s) {
			return trim($s);
		}, explode($delimiter, $str));
	}

	function mathExpression($args) {
		
	}

	print_r($result);
?>


<?php exit; ?>
<form action="assembler.php" method="GET">
	<textarea name="source_code">
		<?php echo $sourceCode; ?>
	</texterea>
	<textarea name="source_code">
		<?php echo $result; ?>
	</texterea>
</form>
