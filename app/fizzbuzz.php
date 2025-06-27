<?
const FIZZ_2_BUZZ = [
	3 => 'Fizz',
	5 => 'Buzz',
	6 => 'Bazz',
];

for($i=0; $i<100; $i++) {
	$line = '';
	foreach(FIZZ_2_BUZZ as $fizz => $buzz) {
		if($i % $fizz == 0) {
			$line .= $buzz;
		}
	}
	if(empty($line)) {
		$line = $i;
	}
	echo $line . "\n";
}
