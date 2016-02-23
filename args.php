<?php

#CHA: xhaisv00

define("EXIT_SUCCESS", 0);
define("ARG_FAIL", 1);
define("INPUT_FAIL", 2);
define("OUTPUT_FAIL", 3);
define("FORMAT_FAIL", 4);


/**
* Funkce zjistujici, zda je v retezci zadany podretezec. Funkce v pripade
* nalezu podretezce vraci true, v opacnem pripade false
* @param retezec $string je retezec, ve kterem hledame podretezec
* @param $str je hledany podretezec
*/

function startWith($string, $str) {
	if (substr($string, 0, strlen($str)) == $str) {
		return true;
	}
	return false;
}

/**
* funkce pro chyby v programu
*/
function failParam($param) {
	file_put_contents('php://stderr', "spatny format parametru " . $param . "!\n");
	exit(ARG_FAIL);
}

function failInput() {
	file_put_contents('php://stderr', "neexistuji soubor nebo chyba otevreni souboru pro cteni\n");
	exit(INPUT_FAIL);
}

function failOutput() {
	file_put_contents('php://stderr', "chyba otevreni noveho nebo existujiciho souboru pro zapis\n");
	exit(OUTPUT_FAIL);
}

function formatFail() {
	file_put_contents('php://stderr', "chybny format souboru\n");
	exit(FORMAT_FAIL);
}

/**
* funkce testuje, zda neni duplicitni zadani argumentu
* @param pole $received obsahujici doposud zpracovane argumenty
* @param string $arg obsahuji prave zpracovavany argument
*/
function argumentIsSet($received, $arg) {
	if (isset($received[$arg])) {
		failParam(". Vice stejnych argumentu");
	}
}

/**
* Funkce zpracovava argumenty prikazove radky a vraci pole, ktere je naplneno hodnotami argumentu
* a s defaultnimi hodnotami nezadanych argumentu
*/
function arguments($argv) {
	$default = array(
		"help" => false,
		"input" => './',
		"output" => false,
		"pretty" => false,
		"inline" => false,
		"max-par" => -1,
		"no-duplicates" => false,
		"remove-whitespace" => false,
		);
	$received = array();
	foreach(array_slice($argv, 1) as $argument) {		//prvni parametr je nazev souboru, proto jej orizneme
		if ($argument === "--help") {
			argumentIsSet($received, "help");
			$received['help'] = true;
		}
		else if (startWith($argument, "--input=")) {	
			argumentIsSet($received, "input");
			$value = substr($argument, 8);
			if (strlen($value) == 0) {
				failParam("--input");
			}
			$received['input'] = $value;
		}
		else if (startWith($argument, "--output=")) {
			argumentIsSet($argument, "--output");
			$value = substr($argument, 9);
			if (strlen($value) == 0) {
				failParam("--output");
			}
			$received['output'] = $value;
		}
		else if (startWith($argument, "--pretty-xml")) {
			argumentIsSet($received, "pretty");
			$split = preg_split('/=/', $argument);
			if (count($split) == 1) {
				$received["pretty"] = 4;
				continue;
			}
			if ((count($split) == 2) && ($split[1] === '')) {	//neni zadane k
				failParam("--pretty-xml");
			}
			if (preg_match('/^[0-9]/', $split[1])) {
				$received["pretty"] = intval($split[1]);
			}
			else {
				failParam("--pretty-xml");
			}
		}
		else if ($argument == "--no-inline") {
			argumentIsSet($received, "inline");
			$received["inline"] = true;
		}
		else if (startWith($argument, "--max-par=")) {
			argumentIsSet($received, "max-par");
			$split = preg_split('/=/', $argument);
			if ($split[1] === '') {
				failParam("--max-par");
			}
			if (preg_match('/^\d+$/', $split[1])) {
				$received["max-par"] = intval($split[1]);
			}
			else {
				failParam("--max-par");
			}
		}
		else if ($argument == "--no-duplicates") {
			argumentIsSet($received, "no-duplicates");
			$received["no-duplicates"] = true;
		}
		else if ($argument == "--remove-whitespace") {
			argumentIsSet($received, "remove-whitespace");
			$received["remove-whitespace"] = true;
		}
		else {
			failParam("");
		}
	}

	if (isset($received['help']) && (count($received) > 1)) {
		failParam("--help s dalsim parametrem");
	}

	return $received + $default;
}

/**
* Vypise napovedu
*/
function help() {
	echo "C Header Analysis (CHA)\n";
	echo "--help\t\t\t tiskne napovedu k programu\n";
	echo "--input=fileordir\t vstupni soubor nebo adresar na zpracovani\n";
	echo "--output=filename\t zadany vystupni soubor ve formatu XML v kodovani UTF-8\n";
	echo "--pretty-xml=k\t\t zformatovani vysledneho souboru. Kazde nove zanoreni bude posunuto o k mezer (bez zadani k o 4)\n";
	echo "--no-inline\t\t skript preskoci funkce deklarovane specifikatorem inline";
	echo "--max-par=n\t\t skript bere v uvahu pouze funkce s n a mene argumenty\n";
	echo "--no-duplicates\t\t do vysledneho XML souboru se ulozi pouze prvni vyskyt funkce\n";
	echo "--remove-whitespace\t odstraneni veskery bilych znaku krome mezer u nekterych atributu\n";
}

/**
* do pole $files nacte zadany soubor/vsechny soubory s priponou .h, ktere se posleze budou zpracovavat
* @param retezec $dir obsahuje cestu k pozadovanemu souboru/pozadovane slozce 
*/
function getInputFiles($dir) {
	$files = array();
	if (is_dir($dir)) {
		$directories = new RecursiveDirectoryIterator($dir);
		$filesInDir = new RecursiveIteratorIterator($directories);
		foreach ($filesInDir as $file) {
			$suf = substr($file, strlen($file)-2);
	        if ($suf == ".h") { 
	            array_push($files, strval($file));
	        }
	    }
	}
	else {
		array_push($files, $dir);
	}
	return $files;
}


/**
* zapise vysledek do vystupniho souboru (pripadne na stdout - zalezi na argumentu)
* @param retezec $file obsahuje cestu k souboru nebo false v pripade, ze chceme vypis na stdout
* @param retezec $outputText obsahuje vysledek skritpu
*/
function writeOutputFile($file, $outputText) {
	if ($file) {
		$substr = substr($file, strlen($file)-4);
		if ($substr == ".xml" || $substr == ".XML") {
			$output = file_put_contents($file, $outputText);
		}
		else {
			formatFail();
		}
	}
	else {
		$output = file_put_contents('php://stdout', $outputText);
	}
	return $output;	
}

/**
* funkce vraci relativni cestu k souboru
* @param string $path je cesta k souboru
* @param string $input je zadana cesta v parametru
*/
function relativePath($path, $input) {
	return substr($path, strlen($input));
}


function paramProcess($params, $outputText, $spaces, $whitespace) {
	$counter = 1;
	foreach ($params as $param) {
		$param = preg_replace("/\w+$/", "", $param);
		$param = trim($param);
		$outputText .= $spaces . $spaces . 'param number="' . $counter . '" type="' . $param . '" />' . "\n";
		$counter++;
	}
	return $outputText;
}

function functionsTag($argumenty, $outputText) {
	if ($argumenty["pretty"]) {
		$outputText .= "\n";
	}
	if (is_dir($argumenty["input"])) {
		if (!preg_match("/\/$/", $argumenty["input"])) {
			$argumenty["input"] .= "/";
		}
		$outputText .= '<functions dir="' . $argumenty["input"] . '">' . "\n";
	}
	else {
		$outputText .= '<functions dir="">' . "\n";
	}
	return $outputText;
}

/**
* funkce na zaklade argumentu naplni promennou $outputText (ktera je zaroven navratovou hodnotou) pozadovanymi tagy
* @param string $name obsahuje jmeno zpracovavane funkce
* @param boolean $varArgs obsahuje zaznam o tom, zda ma funkce promenny pocet argumentu
* @param string $retType obsahuje typ navratove hodnoty zpracovavane funkce
* @param pole $params obsahuje veskere argumenty zpracovavane funkce
* @param string $outputText obsahuje dosavadni vysledky skriptu
* @param pole $argumenty obsahuje zaznamy o argumentech programu
* @param string $file obsahuje prave zpracovavany soubor
*/
function programOutput($name, $varArgs, $retType, $params, $outputText, $argumenty, $file) {
	$spaces = "";
	if ($argumenty["pretty"]) {
		for ($i = 1; $i <= $argumenty["pretty"]; $i++) {
			$spaces .= " ";
		}
	}
	if (is_dir($argumenty["input"])) {
		if (!preg_match("/\/$/", $argumenty["input"])) {
			$argumenty["input"] .= "/";
		}
		$outputText .= $spaces . 'function file="' . relativePath($file, $argumenty["input"]) . '" ';
	}
	else {
		$outputText .= $spaces . 'function file="' . $argumenty["input"] . '" ';
	}
	$outputText .=  'name="' . $name . '" varargs="' . (($varArgs) ? "yes" : "no") . '" rettype="';
	if ($argumenty["remove-whitespace"]) {
		preg_replace("/\s+/", " ", $retType);
	}
	$outputText .= trim($retType) . '">' . "\n";
	$outputText = paramProcess($params, $outputText, $spaces, $argumenty["remove-whitespace"]);
	$outputText .= $spaces . "</function>\n";
	return $outputText;
}

/**
* Funkce na zaklade vstupnich parametru vyfiltruje funkce splnujici pozadavky uzivatele
* @param pole $argumenty obsahuje zaznamy ze vstupnich argumentu programu
* @param dvojrozmerne pole $array obsahuje zaznamy o deklaracich funkci (pole ma prvky retType, funcName a params, ktere obsahuji 
*			informace o dane funkci [navratovou hodnotu, jeji nazev a parametry])
* @param string $filePath obsahuje cestu ke zpracovanemu souboru
* @param string @outputText je vysledny string, ktery bude zapsan do vystupniho XML souboru
*/
function prepForWrite($argumenty, $array, $filePath, $outputText) {
	$argArray = array();
	$varArgs;
	for ($i = 0; $i < count($array["funcName"]); $i++) {
		if ($argumenty["inline"] && preg_match("/inline/u", $array["retType"][$i])) {
			continue;
		}
		if ($argumenty["no-duplicates"]) {
			$j = $i-1;
			while($j >= 0) {
				if ($array["funcName"][$i] == $array["funcName"][$j]) {		//jsou duplicitni
					break;
				}
				$j--;
			}
			if ($j >= 0) {		//byla zjistena duplicita funkci
				continue;
			}
		}
		$varArgs = preg_match("/\.\.\./", $array["params"][$i]);
		$argArray = preg_split("/,/", $array["params"][$i]);
		if ($argumenty["max-par"] > -1) {
			if ((count($argArray)-intval($varArgs)) > $argumenty["max-par"]) {
				continue;
			}
		}
		$outputText = programOutput($array["funcName"][$i], $varArgs, $array["retType"][$i], $argArray, $outputText, $argumenty, $filePath);
	}
	return $outputText;
}


$argumenty = arguments($argv);
if ($argumenty["help"]) {
	help();
	exit(EXIT_SUCCESS);
}

$files = getInputFiles(realpath($argumenty["input"]));
$patterns = array();
$outputText = '<?xml version="1.0" encoding="utf-8"?>' . "\n";
$outputText = functionsTag($argumenty, $outputText);
array_push($patterns, "/\/\*(.|\\n)*\*\//u");
array_push($patterns, "/\/\/.*\\n/u");
array_push($patterns, "/\"(.|\\n)*\"/u");
array_push($patterns, "/\'(.|\\n)*\'/u");
array_push($patterns, "/\#.*\\n/u");
$allPattern = "/\s*(?<retType>(?:\s*[A-Za-z_]\w+[\s\*]+)+)\s*(?<funcName>(?:[A-Za-z_]\w+))\s*\((?<params>(?:[\s\S]*?)*)\)\s*[;|{]/u";
foreach ($files as $file) {
	$content = file_get_contents($file);
	foreach($patterns as $pattern) {
		$content = preg_replace($pattern, "", $content);
	}
	preg_match_all($allPattern, $content, $match);
	if (count($match["funcName"]) > 0) {		//test zda je v souboru vubec nejaka deklarace funkce
		$outputText = prepForWrite($argumenty, $match, $file, $outputText);	
	}
}
$outputText .= "</functions>\n";
writeOutputFile($argumenty["output"], $outputText);

?>