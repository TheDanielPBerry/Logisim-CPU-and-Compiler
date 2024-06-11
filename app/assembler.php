<?php
	error_reporting(E_ERROR);

	

	$sourceCode = "
	#INCLUDEIFNOT 'lib/stdio.dink'
	#INCLUDEIFNOT 'lib/string.dink'

	DEFINE stwing: *char = (char)[40]

	DEFINE string2: *char = \"Cia\"

	FUNC main()
		CALL scanline(&stwing)
		CALL str_cmp(&stwing, &string2)
		IF !returnVal
			SET *putchar = 'T'
		ELSE
			SET *putchar = 'F'
		ENDIF
	ENDFUNC
	";


	$TYPES = [
		'char' => 1,
		'word' => 2,
		'*char' => 2,
		'*word' => 2,
		'func' => 2
	];

	$ALU_OPS = [
		'&' => '0',
		'|' => '1',
		'^' => '2',
		'+' => '3',
		'-' => '4',
		'*' => '5',
		'/' => '7'
	];


	function getMemoryRegister($var) {
		switch($var['address_type']) {
			case "immediate":
				return $var['size'] == 1 ? 'E' : 'F';

			case "global":
				return '1';
				break;
				
			case "stack":
				return '2';
				break;

				
			case "index":
				return '3';
				break;

		}
	}

	$CMP_FLAGS = [
		'==' => '01',
		'>' => '02',
		'>=' => '80',
		'<' => '04',
		'<=' => '40',
		'!=' => '08'
	];

	$CMP_FLAGS_INVERTED = [
		'==' => '08',
		'>' => '40',
		'>=' => '04',
		'<' => '80',
		'<=' => '02',
		'!=' => '01'
	];

	//Starting commands 
	$result = [
		'FA', 'FF', '00',	//Initialize the stack
		'F9', ['main', 'top'], ['main', 'bottom']	//GOTO main function or defintion
	];

	$stack = [
		'main' => [
			'val' => '0006'
		]
	];
	$stackFrame = &$stack;
	$scopeTracker = [];
	
	$lineNumber = 0;


	$includeList = [];
	$sourceCode = linker($sourceCode);

	function linker($source) {
		global $includeList;

		$test = preg_match_all("/\#INCLUDE(?<if>IFNOT)?\s+[\'\"](?<filePath>\S+)[\'\"]/", $source, $links);
		for($i=0; $i<$test; $i++) {
			$filePath = $links['filePath'][$i];
			if(!array_key_exists($filePath, $includeList)) {
				$includeList[$filePath] = file_get_contents($filePath);
				$includeList[$filePath] = linker($includeList[$filePath]);
				if($links['if'][$i] == 'IFNOT') {
					$source = preg_replace("/\#INCLUDEIFNOT\s+[\'\"](?:\S+)[\'\"]/", $includeList[$filePath], $source, 1);
					continue;
				}
			}
			if(empty($links['if'][$i])) {
				$source = preg_replace("/\#INCLUDE\s+[\'\"](?<filePath>\S+)[\'\"]/", $includeList[$filePath], $source);
			}
		}
		return $source;
	}


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
					writeRaw($hexString);
					break;

				case "CONST":
					[$leftExpr, $rightToken] = expTrim('=', $args);
					[$varName, $type] = expTrim(':', $leftExpr);
					//Valdiate varName
					preg_match("/^[a-zA-Z]([A-Za-z_\d]+)?$/", $varName, $varMatch);
					if(count($varMatch) == 0) {
						error("Invalid const name " . $varName . "\n");
					}
					if(in_array($type, ['*char', '*word'])) {
						$address_type = 'global';
					} else {
						$address_type = 'immediate';
					}
					
					//Look for a type casting
					preg_match("/^\((?<type>\*?char|\*?word)\)\s*(?<literal>.+)$/", $rightToken, $typeMatch);
					if(empty($typeMatch['type'])) {
						//If no type is boldly cast, then make it match the type of the destination
						$rightToken = '(' . $type . ')' . $rightToken;
					}

					$stackFrame[$varName] = [
						'name' => $varName,
						'val' => parseLiteral($rightToken),
						'type' => $type,
						'address_type' => $address_type,
						'size' => $TYPES[$type]
					];
					break;


				case "DEFINE":
					[$leftExpr, $rightToken] = expTrim('=', $args);
					[$varName, $type] = expTrim(':', $leftExpr);
					//Valdiate varName
					preg_match("/^[a-zA-Z_](?:[A-Za-z_\d]+)?$/", $varName, $varMatch);
					if(count($varMatch) == 0) {
						error("Invalid variable name \"" . $varName . "\"\n");
					}
					if(array_key_exists($varName, $stackFrame) || array_key_exists($varName, $stack)) {
						error("Variable already defined: \"" . $varName . "\"\n");
					}

					$size = $TYPES[$type];
					
					if(empty($rightToken)) {
						//If assignment given, then initialize as 0
						$rightToken = '0';
					}

					//Look for a type casting
					preg_match("/^\((?<type>\*?char|\*?word)\)\s*(?<literal>.+)$/", $rightToken, $typeMatch);
					if(empty($typeMatch['type'])) {
						//If no type is boldly cast, then make it match the type of the destination
						$rightToken = '(' . $type . ')' . $rightToken;
					}

					
					//If in the main global stack, then set a global var
					if(count($scopeTracker) == 0) {
						//Global memory addresses
						$address_type = 'global';
						//The val is the current memory position for the variable
						$val = parseLiteral('u' . count($result));

						$token = findToken($rightToken);
						if($token['address_type'] == 'immediate') {
							writeRaw($token['val']);
						}
					} else {
						$address_type = 'stack';
						
						//If inside of a function, dynamically increase the frame size by the size of the variable
						$stackFrame['frame']['size'] += $size;
						//variables on the stack are marked by their offset from the stack pointer
						//Dynamically allocated local variables are added on top
						$val = $stackFrame['frame']['size'] * -1;	

						//If on the stack, initialize the value with something
						[$assignmentRegister, $right]  = fetchToken($rightToken);
						//If in a function save initialized variable onto stack
						$result[] = $assignmentRegister . '2';
						$result[] = [$varName, 'top'];
						$result[] = [$varName, 'bottom'];
					}
					
					$stackFrame[$varName] = [
						'name' => $varName,
						'val' => $val,
						'type' => $type,
						'address_type' => $address_type,
						'size' => $size
					];
					
					
					break;




				case "SET":	//Variable Assignment
					[$leftToken, $rightToken] = expTrim('=', $args);
					
					preg_match("/(?<varName>[\&\*]?[^\s\[\]]+)(?:\[(?<index>[^\s\[\]]+)\])?/", $leftToken, $leftMatches);
					$left = findToken($leftMatches['varName']);

					$assignmentType = $left['type'];
					if(!empty($leftMatches['index'])) {
						$assignmentType = str_replace('*', '', $assignmentType);
					}
					
					//Look for a type casting
					preg_match("/^\((?<type>\*?char|\*?word)\)\s*(?<literal>.+)$/", $rightToken, $typeMatch);
					if(empty($typeMatch['type'])) {
						//If no type is boldly cast, then make it match the type of the destination
						$rightToken = '(' . $assignmentType . ')' . $rightToken;
					}
					[$assignmentReg, $right] = fetchExpression($rightToken)
					
					setToken($leftToken, $assignmentReg);
					
					
					break;
				

				case "JE":
					$result[] = "ec";
					$result[] = "01";	//SET JE

					$entry = findToken($args);
					$result[] = getMemoryRegister($entry) . '9';
					$result[] = [&$entry, 'top'];
					$result[] = [&$entry, 'bottom'];
					break;

				case "GOTO":
					$result[] = "EC";
					$result[] = "00";	//Clear out the flags
				case "JMP":
					$entry = findToken($args);
					$result[] = getMemoryRegister($entry) . '9';
					$result[] = [&$entry, 'top'];
					$result[] = [&$entry, 'bottom'];
					break;
				
				case "IF":
					[$conditional] = insertConditional($args);
					$scopeTracker[] = $conditional;
					break;

				case "ELSEIF":
				case "ELSE":
					if($scopeTracker > 0) {
						$conditional = end($scopeTracker);
						if($conditional['type'] == 'conditional') {
							//Insert an unconditional jump to very end of the ifblock
							$result[] = 'EC';
							$result[] = '00';
							$result[] = 'F9';
							$result[] = [$conditional['conditionalId'] . '-exit', 'top'];
							$result[] = [$conditional['conditionalId'] . '-exit', 'bottom'];

							//Next block is now 
							$nextId = $conditional['conditionalId'] . '-next-' . count($conditional['next']);
							$stackFrame[$nextId] =  [
								'name' => $nextId,
								'val' => parseLiteral(count($result)),
								'type' => 'conditional',
								'address_type' => 'immediate',
								'size' => 2
							];

							if($operation == 'ELSEIF') {
								[$conditional] = insertConditional($args, $conditional);
								$scopeTracker[count($scopeTracker)-1] = $conditional;
							}
						} else {
							error("Unbalanced conditional blocks");
						}
					} else {
						error("Unbalanced conditional blocks");
					}
					
					break;

				case "ENDIF":
						$conditional = array_pop($scopeTracker);
						$stackFrame[$conditional['conditionalId'] . '-exit'] =  [
							'name' => $conditional['conditionalId'] . '-exit',
							'val' => parseLiteral(count($result)),
							'type' => 'conditional',
							'address_type' => 'immediate',
							'size' => 2
						];
						$nextId = $conditional['conditionalId'] . '-next-1';
						if(!isset($stackFrame[$nextId])) {
							$stackFrame[$nextId] =  [
								'name' => $nextId,
								'val' => parseLiteral(count($result)),
								'type' => 'conditional',
								'address_type' => 'immediate',
								'size' => 2
							];
						}
						
					break;


				case "WHILE":
				case "LOOP":
					$loopCount = count(array_filter($stackFrame, fn($var) => $var['type'] == 'loop'));
					$loopId = 'loop-' . $loopCount;
					$loop = [
						'exit_id' => $loopId . '-exit',
						'entry_id' => $loopId . '-entry',
						'entry' => parseLiteral(count($result))
					];

					$comparator = parseBooleanExpr($args);
					$result[] = 'EC';
					$result[] = $CMP_FLAGS_INVERTED[$comparator];


					$result[] = 'F9';
					$result[] = [$loop['exit_id'], 'top'];
					$result[] = [$loop['exit_id'], 'bottom'];

					$loop['exit'] = parseLiteral(count($result));
					$scopeTracker[] = $loop;

					break;
				
				case "ENDWHILE":
				case "ENDLOOP":
					$loop = array_pop($scopeTracker);
					
					$result[] = 'EC';
					$result[] = '00';
					$result[] = 'F9';
					$result[] = [$loop['entry_id'], 'top'];
					$result[] = [$loop['entry_id'], 'bottom'];

					$stackFrame[$loop['entry_id']] =  [
						'name' => $loop['entry_id'],
						'val' => $loop['entry'],
						'type' => 'loop',
						'address_type' => 'immediate',
						'size' => 2
					];
					
					$stackFrame[$loop['exit_id']] =  [
						'name' => $loop['exit_id'],
						'val' => parseLiteral(count($result)),
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
							
							//Look for a type casting
							preg_match("/^\((?<type>\*?char|\*?word)\)\s*(?<literal>.+)$/", $argToken, $typeMatch);
							if(count($typeMatch) == 0) {
								$argToken = '(' . $param['type'] . ')' . $argToken;
							}
							
							[$assignmentReg, $arg] = fetchToken($argToken);
							
							//Move fetched argument onto stack
							$result[] = $assignmentReg . '2';
							if($scopeTracker > 0  && $param['address_type'] == 'stack') {
								if(gettype($param['val']) != 'string') {
									$stackOffset = ['val' => parseLiteral($param['val'])];
									$result[] = top($stackOffset);
									$result[] = bottom($stackOffset);
								} else {
									$result[] = [$param, 'top'];
									$result[] = [$param, 'bottom'];
								}
							} else {
								$result[] = [$param, 'top'];
								$result[] = [$param, 'bottom'];
							}
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
						$result[] = [$func, 'top'];
						$result[] = [$func, 'bottom'];	
					} else {
						error("Function not found " . $varName . "\n");
					}

					$stackFrame['!returnVal'] = [
						'name' => '!returnVal',
						'val' => parseLiteral($func['stack']['!return']['val']),
						'type' => $func['stack']['!return']['type'],
						'address_type' => 'stack',
						'size' => $func['stack']['!return']['size']
					];
					
					break;

					
				
				case "FUNC":
					preg_match("/^(?<funcName>[a-zA-Z_](?:[A-Za-z_\d]*)?)\((?<params>[A-Za-z_\d, \:\*]*)\)(?: *\:* *)(?<returnType>[\*a-w]*)$/", $args, $funcMatch);
					if(count($funcMatch) > 2) {
						$stackFrame[$funcMatch['funcName']] = [
							'name' => $funcMatch['funcName'],
							'val' => parseLiteral(count($result)),
							'type' => 'func',
							'address_type' => 'immediate',
							'size' => 2,
							'stack' => []
						];

						$scopeTracker[] = &$stackFrame;
						$temp = &$stackFrame[$funcMatch['funcName']]['stack'];
						unset($stackFrame);
						$stackFrame = &$temp;
						unset($temp);
						
						$stackFrame['frame'] = [
							'name' => $funcMatch['funcName'],
							'val' => count($result),
							'type' => 'func',
							'address_type' => 'stack',
							'size' => 2	//Set frame size to 2
							
						];

						if(!empty($funcMatch['returnType'])) {
							if(!in_array($funcMatch['returnType'], array_keys($TYPES))) {
								error("Invalid return type " . $funcMatch['returnType'] . "\n");
							}
							
							$stackFrame['frame']['size'] += $TYPES[$funcMatch['returnType']];
							$stackFrame['!return'] = [
								'val' => $stackFrame['frame']['size'] * -1,
								'type' => $funcMatch['returnType'],
								'size' => $TYPES[$funcMatch['returnType']],
								'address_type' => 'stack'
							];
						}

						$params = expTrim(',', $funcMatch['params']);
						foreach($params as $param) {
							if(empty($param)) continue;
							[$varName, $type] = expTrim(':', $param);
							
							preg_match("/^[a-zA-Z_](?:[A-Za-z_\d]+)?$/", $varName, $varMatch);
							if(count($varMatch) == 0) {
								error("Invalid parameter name \"" . $varName . "\"\n");
							}

							$stackFrame['frame']['size'] += $TYPES[$type];
							$stackFrame[$varName] = [
								'name' => $varName,
								'val' => $stackFrame['frame']['size'] * -1,
								'type' => $type,
								'address_type' => 'stack',
								'size' => $TYPES[$type],
								'param' => true
							];
						}
						
						$result[] = 'E5';
						$result[] = ['frame', function($frame) {
							return bottom(['val' => str_pad(dechex($frame['size']), 2, '0', STR_PAD_LEFT)]);
						}];
						$result[] = "CA";
						$result[] = "A5";
						$result[] = "40";
						
					} else {
						error("Invalid FUNC Definition\n");
					}
					break;
				
				case "RETURN":
					preg_match("/^(?<returnToken>.*)$/", $args, $returnMatch);
					
					$returnToken = $returnMatch['returnToken'];
					if(!empty($returnToken)) {
						//Look for a type casting
						preg_match("/^\((?<type>\*?char|\*?word)\)\s*(?<literal>.+)$/", $returnToken, $typeMatch);
						if(empty($typeMatch['type'])) {
							$type = $stackFrame['!return']['type'];
							//If no type is boldly cast, then make it match the type of the destination
							$returnToken = '(' . $type . ')' . $returnToken;
						}

						[$assignmentReg, $ret] = fetchToken($returnToken);
						
						setToken('!return', $assignmentReg);
					}

					//Exit the function
					//Move stack pointer back down
					$result[] = 'E5';	//load A with frame size
					$result[] = ['frame', function($frame) {
						return bottom(['val' => str_pad(dechex($frame['size']), 2, '0', STR_PAD_LEFT)]);
					}];
					$result[] = "CA";
					$result[] = "A5";
					$result[] = "30";	//Add it to the stack pointer

					$result[] = 'EC';	//Clear jmp flags
					$result[] = '00';

					$result[] = '29';	//Go to return address
					$result[] = 'FF';
					$result[] = 'FE'; 
					break;




				case "ENDFUNC":
					//Calculate all local vars and param offsets based on text
	 				for($i = $stackFrame['frame']['val']; $i < count($result); $i++) {
						$byte = $result[$i];
						if(is_array($byte)) {
							[$ref, $post] = $byte;
							if(gettype($ref) == 'string') {
								if(in_array($ref, array_keys($stackFrame))) {
									if(in_array($stackFrame[$ref]['type'], ['loop', 'conditional', 'func', 'return']) || $stackFrame[$ref]['return']) {
										$result[$i] = $post($stackFrame[$ref]);
									} else {
										//Write reference to variable on stack as a positive offset based on frame size
										$result[$i] = $post(['val' => parseLiteral($stackFrame['frame']['size'] +  $stackFrame[$ref]['val'])]);
									}
								}
								else {
									error("Undefined reference to a variable outside of scope \"". $ref . "\"\n");
								}
							} 
						}
					}

					//Now the function is finished all references to params should be their negative offset from the stack pointer in hex
					foreach($stackFrame as $key => $var) {
						if($var['param']) {
							$stackFrame[$key]['val'] = parseLiteral($stackFrame[$key]['val']);
						}
					}
					
					//Exit the function with no return val
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
					$stackFrame = &$scopeTracker[count($scopeTracker)-1];
					array_pop($scopeTracker);
					break;

				case "ALU":
					preg_match("/^(?<varName>[a-zA-Z_](?:[A-Za-z_\d]+))\s*(?<op>[\&\|\^\!\+\-\*\/])\s*(?<immediate>\-?[\d]+)$/", $args, $aluMatch);
					if(count($aluMatch) > 0) {
						[$assignmentReg, $var] = fetchToken($aluMatch['varName']);

						$mathArg = findToken('(char)' . $aluMatch['immediate']);
						if($mathArg['address_type'] == 'immediate') {
							$result[] = 'C' . $assignmentReg;
							$result[] = $assignmentReg . 0;
							$result[] = $ALU_OPS[$aluMatch['op']] . substr($mathArg['val'], -1);
							
							setToken($aluMatch['varName'], $assignmentReg);
						} else {
							error("Cannot do a quick alu operation with this kind of variable: \"". $aluMatch['immediate'] . "\"\n");
						}
							
					} else {
						error("Malformed alu operation: \"". $aluMatch['immediate'] . "\"\n");
					}
					break;

				case "#INCLUDE":
					break;
			}
		}
	}

	if(count($scopeTracker) > 1) {
		error("Unclosed Functions\n");
	}



	//Setup the appropriate registers before doing an indexed operation
	function prepIndex($baseToken, $offsetToken, $indexRegister = '8') {
		//Look for a type casting
		preg_match("/^\(\*?(?<type>char|word)\)\s*(?<token>.+)$/", $offsetToken, $indexMatch);
		if(count($indexMatch) == 0) {
			$offsetToken = '(word)' . $offsetToken;
		}
		$index = findToken($offsetToken);
		if($index['address_type'] != 'immediate') {
			fetchToken($offsetToken, $indexRegister);
		}


		//Move the base of the index into the index register
		[$idx, $base] = fetchToken($baseToken, 'B');
		
		return [$base, $index];
	}

	function fetchToken($rightToken, $assignmentReg = null) {
		global $result;
		global $TYPES;
		global $stackFrame;
		global $scopeTracker;
		
		if(!is_null($assignmentReg)) {
			$assignmentSize = in_array($assignmentReg, ['5', '6']) ? 1 : 2;
		}
		
		//Look for a type casting
		preg_match("/^\((?<type>(\*)?(?:char|word))\)\s*(?<token>.+)$/", $rightToken, $typeMatch);
		if(count($typeMatch) > 0) {
			$assignmentSize ??= $TYPES[$typeMatch['type']];
			$assignmentReg ??= ($assignmentSize <= 1 ? '5' : '7');
		}
		
		preg_match("/(?<pointer>\*)?(?<varName>&?[^\s\[\]]+)(?:\[(?<index>[^\s\[\]]+)\])?/", $rightToken, $rightMatches);
		
		
		//If the token is indexed, then setup indexed fetch
		if(!empty($rightMatches['index'])) {
			$varIndexRegister = '8';	//Any variable indexes should go into the D register
			[$right, $index] = prepIndex($rightMatches['varName'], $rightMatches['index'], $varIndexRegister);
			
			if($index['address_type'] == 'immediate') {
				$opCode = '3' . $assignmentReg;
				$result[] = $opCode;
				$result[] = [$index, 'top'];
				$result[] = [$index, 'bottom'];
			} else {
				$assignmentReg ??= strpos($right['type'], 'char') === false ? '7' : '5';

				$result[] = '4' . $assignmentReg;
				$result[] = 'B' . $varIndexRegister;
				$result[] = "30";
			}
			
		} else {
			$right = findToken($rightMatches['varName']);
			if($right['address_type'] == 'index') {
				[$pointer, $right] = prepIndex($right['name'], '0', 'B');
				$type = str_replace('*', '', $pointer['type']);
				$right = [
					'val' => $right['val'],
					'type' => $type,
					'size' => $TYPES[$type],
					'address_type' => 'index'
				];
			}
			
			if($right['addressModified']) {
				$assignmentSize = 2;
			}
			
			if($right['address_type'] == 'immediate') {
				//If immediate base instant size on the destination
				$opCode = getMemoryRegister($right);
			} else {
				//Figure out if working with 1 or 2 bytes
				$assignmentSize ??= $right['size'];
				$opCode = getMemoryRegister($right);
			}
			$assignmentReg ??= $assignmentSize == 1 ? '5' : '7';
			$opCode .= $assignmentReg;
			$result[] = $opCode;
			
			if(count($scopeTracker) > 0 && $right['address_type'] == 'stack' && $right['name'] != '!returnVal') {
				$result[] = [$right['name'], 'top'];
				$result[] = [$right['name'], 'bottom'];
			} else {
				if($right['address_type'] != 'immediate' || $assignmentSize > 1){
					$result[] = [$right, 'top'];
				}
				$result[] = [$right, 'bottom'];
			}
		}
		return [$assignmentReg, $right];
	}


	
	function setToken($leftToken, $assignmentReg = null) {
		global $scopeTracker;
		global $result;


		preg_match("/(?<pointer>\*)?(?<varName>&?[^\s\[\]]+)(?:\[(?<index>[^\s\[\]]+)\])?/", $leftToken, $leftMatches);
		$left = findToken($leftMatches['varName']);
		
		//Move from register to saved variable  
		if(!empty($leftMatches['index'])) {
			$varIndexRegister = '8'; //D is default register to hold index+var
			[$left, $index] = prepIndex($leftMatches['varName'], $leftMatches['index'], $varIndexRegister);
			if($index['address_type'] == 'immediate') {
				$result[] = $assignmentReg . '3';
				$result[] = [$index, 'top'];
				$result[] = [$index, 'bottom'];
			} else {
				$result[] = $assignmentReg . '4';
				$result[] = 'B' . $varIndexRegister;
				$result[] = "30";
			}
		} else {
			$opCode = $assignmentReg;
			$opCode .= getMemoryRegister($left);
			$result[] = $opCode;
			if(count($scopeTracker) > 0 && $left['address_type'] == 'stack') {
				$result[] = [$leftToken, 'top'];
				$result[] = [$leftToken, 'bottom'];
			} else {
				$result[] = [$left, 'top'];
				$result[] = [$left, 'bottom'];
			}
		}
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


	function fetchExpression($expr, $assignmentReg = null) {
		global $result;
		global $lineNumber;

		preg_match("/^(.+)(?<operation>\!\=|\>\=?|\<\=?|\=\=|\+|\-|\*|\/|\&|\||\^)(.+)/", $expr, $exprMatch);
		if($exprMatch) {
			[$leftToken, $rightToken] = split($expressionSplit, $expr);
		} else {
			$leftToken = trim($expr);
		}

		[$leftToken, $rightToken] = split($expressionSplit, $expr);
		$leftToken = trim($leftToken);
		if(strtoupper($leftToken) == 'TRUE') {
			$leftToken = '(char)1';
		} else if(strtoupper($leftToken) == 'FALSE') {
			$leftToken = '(char)0';
		}
		if(is_null($rightToken)) {
			$rightToken = '(char)1';
		} else {
			$rightToken = trim($rightToken);
		}
		
		//$left = findToken($leftToken);
		//$leftToken = '(' . $left['type'] . ')' . $leftToken;
		[$leftReg, $left] = fetchToken($leftToken);
		$rightReg = ($leftReg == '5') ? '6' : '8';
		
		preg_match("/^\((?<type>\*?char|\*?word)\)\s*(?<literal>.+)$/", $rightToken, $typeMatch);
		if(count($typeMatch) == 0) {
			$rightToken = '(' . str_replace('*', '', $left['type']) . ')' . $rightToken;
		}
		[$rightReg, $right] = fetchToken($rightToken, $rightReg);


		$result[] = 'C0'; //Compare the 2 values and save to null
		$result[] = $leftReg . $rightReg;
		$result[] = '10';
		
		preg_match("/^\S+\s*(?:(?<condition>[\!\=\>\<]{1,2})\s*\S+)?$/", $expr, $boolMatch);
		if(count($boolMatch) == 0) {
			error("Invalid boolean expression: \"" . $expr . "\"\n");
		} else if(empty($boolMatch['condition'])) {
			return '==';
		}
		return [$assignmentReg, $right];
	}

	//load the left and right elements of a boolean expression into 2 separate registers and then do an ALU OR to NUL on them
	function parseBooleanExpr($expr) {
		global $result;
		global $lineNumber;

		[$leftToken, $rightToken] = preg_split("/(\!\=|\>\=?|\<\=?|\=\=)/", $expr);
		$leftToken = trim($leftToken);
		if(strtoupper($leftToken) == 'TRUE') {
			$leftToken = '(char)1';
		} else if(strtoupper($leftToken) == 'FALSE') {
			$leftToken = '(char)0';
		}
		if(is_null($rightToken)) {
			$rightToken = '(char)1';
		} else {
			$rightToken = trim($rightToken);
		}
		
		//$left = findToken($leftToken);
		//$leftToken = '(' . $left['type'] . ')' . $leftToken;
		[$leftReg, $left] = fetchToken($leftToken);
		$rightReg = ($leftReg == '5') ? '6' : '8';
		
		preg_match("/^\((?<type>\*?char|\*?word)\)\s*(?<literal>.+)$/", $rightToken, $typeMatch);
		if(count($typeMatch) == 0) {
			$rightToken = '(' . str_replace('*', '', $left['type']) . ')' . $rightToken;
		}
		[$rightReg, $right] = fetchToken($rightToken, $rightReg);


		$result[] = 'C0'; //Compare the 2 values and save to null
		$result[] = $leftReg . $rightReg;
		$result[] = '10';
		
		preg_match("/^\S+\s*(?:(?<condition>[\!\=\>\<]{1,2})\s*\S+)?$/", $expr, $boolMatch);
		if(count($boolMatch) == 0) {
			error("Invalid boolean expression: \"" . $expr . "\"\n");
		} else if(empty($boolMatch['condition'])) {
			return '==';
		}
		return $boolMatch['condition'];
	}


	//Add a string of hex characters to the result
	function writeRaw($hexString, $size = null) {
		global $result;
		$offset = 0;
		if(!is_null($size)) {
			$offset = strlen($hexString) - ($size*2);
		}

		for($i=$offset; $i<strlen($hexString); $i+=2) {
			$result[] = str_pad(substr($hexString, $i, 2), 2, '0', STR_PAD_LEFT);
		}
	}

	function parseLiteral($literal, $size = 2) {
		global $TYPES;
		
		//Look for a type casting
		preg_match("/^\((?<type>\*?char|\*?word)\)\s*(?<literal>.+)$/", $literal, $typeMatch);
		if(count($typeMatch) > 0) {
			$size = $TYPES[$typeMatch['type']];
			$literal = $typeMatch['literal'];
		}

		
		//Match to an ascii character literal
		preg_match("/^(?:TRUE|FALSE)$/", strtoupper($literal), $boolMatch);
		if(count($boolMatch) > 0) {
			return strtoupper($boolMatch[0]) == 'TRUE' ? '01' : '00';
		}


		//Match to an ascii character literal
		preg_match("/^\'([\S\s]{1,2})\'$/", $literal, $charMatch);
		if(count($charMatch) > 1) {
			$charMatch[1] = str_replace("\\n", "\n", $charMatch[1]);
			$charMatch[1] = str_replace("\\0", "\0", $charMatch[1]);
			return str_pad(bin2hex($charMatch[1]), 2, '0', STR_PAD_LEFT);
		}
		
		//Match to a string literal
		preg_match("/^\"(.*)\"$/", $literal, $strMatch);
		if(count($strMatch) > 1) {
			$strMatch[1] = str_replace("\\0", "\0", $strMatch[1]);
			$strMatch[1] = str_replace("\\n", "\n", $strMatch[1]);
			return bin2hex($strMatch[1]) . '00';
		}
		
		//Match to an array literal
		preg_match("/^\{(.*)\}$/", $literal, $arrayMatch);
		if(count($arrayMatch) > 1) {
			return array_reduce(expTrim(',', $arrayMatch[1]), function($acc, $element) use($size) {
				return $acc . parseLiteral($element, $size);
			}, '');
		}

		//Match to a definition of empty bytes
		preg_match("/^\[(\d*)\]$/", $literal, $emptyBytes);
		if(count($emptyBytes) > 1) {
			return str_repeat('00', $size * $emptyBytes[1]);
		}
		
		//Match to an integer
		preg_match("/^(u(?!-))?(-?\d+)$/", $literal, $numMatch);
		if(is_numeric($numMatch[2])) {
			$pad = '0';
			if($numMatch[1] != 'u') {
				$pad = intval($numMatch[2]) < 0 ? 'F' : '0';
			}
			$hexString = substr(dechex($numMatch[2]), -4);
			return str_pad($hexString, $size*2, $pad, STR_PAD_LEFT);
		} 
		
		//Match to a hex string
		preg_match("/^0[xX]([A-Fa-f\d]{1,4})$/", $literal, $hexMatch);
		if(count($hexMatch) > 1) {
			$pad = '0';
			return str_pad($hexMatch[1], $size*2, $pad, STR_PAD_LEFT);
		}
		return false;
	}



	function findToken($token) {
		global $stack;
		global $stackFrame;
		global $TYPES;
		global $lineNumber;

		//Look for a type casting
		preg_match("/^\((?<type>\*?char|\*?word)\)\s*(?<token>.+)$/", $token, $typeMatch);
		if(count($typeMatch) > 0) {
			$type = $typeMatch['type'];
			$token = $typeMatch['token'];
		}

		if(substr($token, 0, 1) == '&') {
			$token = substr($token, 1);
			$addressType = 'immediate';
			$size = 2;
			$addressModified = true;
		}

		if(substr($token, 0, 1) == '*') {
			$token = substr($token, 1);
			$addressType = 'index';
			$inference = true;
		}

		$return = $stackFrame[$token];
		$return ??= $stack[$token];

		if(is_null($return)) { 
			if(substr($token, -1) == 'b') {
				$token = substr($token, 0, -1);
				$size = 1;
			}
			if(!empty($type)) {
				$token = '(' . $type . ')' . $token;
			}
			$literal = parseLiteral($token);
			if($literal) {
				$type ??= strlen($literal) == 2 ? 'char' : 'word';
				$return = [
					'val' => $literal,
					'type' => $type,
					'size' => $TYPES[$type],
					'address_type' => 'immediate'
				];
			} else {
				error("Undefined: \"" . $token . "\" not found\n");
			}
		}

		if($inference) {
			$return['type'] = str_replace('*', '', $return['type']);
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

	function insertConditional($booleanExpr, $conditional = null) {
		global $stackFrame;
		global $result;
		global $CMP_FLAGS_INVERTED;

		if(is_null($conditional)) {
			$conditional = [
				'type' => 'conditional',
				'conditionalId' => ('if-' . rand(1000, 9999999)),
				'next' => []
			];
		}

		$comparator = parseBooleanExpr($booleanExpr);
		$result[] = 'EC';
		$result[] = $CMP_FLAGS_INVERTED[$comparator];
		
		$nextId = $conditional['conditionalId'] . '-next-' . (count($conditional['next'])+1);
		$result[] = 'F9';
		$result[] = [$nextId, 'top'];
		$result[] = [$nextId, 'bottom'];
		
		$exitLabel = parseLiteral(count($result));
		$conditional['next'][] = $exitLabel;
		return [$conditional];
	}


	if(count($scopeTracker) > 0) {
		error("Syntax error - Unbalanced blocks\n");
	}

	//Post Processing
	function top($ref) {
		return substr(str_pad($ref['val'], 4, '0', STR_PAD_LEFT), 0, 2);
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

	function error($msg) {
		global $lineNumber;

		echo "Compile Error on Line: " . $lineNumber . "\n<br/>";
		echo $msg;

		printSourceCode($lineNumber);
		exit;
	}

	function printSourceCode($lineNumber = null) {
		global $sourceCode;

		$formattedCode = preg_replace("/\\r(?!\\n)/", "\n\r", $sourceCode);
		$lines = explode("\n", $formattedCode);

		echo "<pre>";
		foreach($lines as $number => $line) {
			$num = $number + 1;
			echo "<span style='background-color: grey;'>";
			echo $num . "\t";
			echo "</span>";
			if($lineNumber == $num) {
				echo "<span style='background-color: red; color: white;'>";
			}
			echo $line;
			if($lineNumber == $num) {
				echo "</span>";
			} else if(substr($line, -1) != "\n") {
				echo "\n";
			}

		}
		echo "</pre>";
	}


	//split and trim a string
	function expTrim($delimiter, $str) {
		return array_map(function($s) {
			return trim($s);
		}, explode($delimiter, $str));
	}

	function mathExpression($args) {
		
	}
	echo "\n<br/>\n<br/>\n<br/>";
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
