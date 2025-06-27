<?

	const LENGTH = 4;
	$in = 'abacbfadsce';


	function first_uniq_substring(string $in, int $length): int {
		$letters = array_fill(97, 26, -1);

		$sub_len = 1;

		for($i = 0; $i < strlen($in); $i++) {
			$char = ord($in[$i]);	//Get ascii integer value of character
			if($letters[$char] < -1 || ($i - $letters[$char]) >= $length) {
				$sub_len++;
			} else {
				$sub_len = 1;
			}
			$letters[$char] = $i;
			if($sub_len >= $length) {
				return $i - $length;
			}
		}

		return -1;
	}


	print_r(first_uniq_substring($in, LENGTH));

