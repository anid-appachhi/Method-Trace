<?php

$fileName = "/home/anid/Desktop/log1.html";
//$fileName = "/home/anid/Android/Sdk/platform-tools/log1.html";

$fileContentArr = file($fileName);

$firstPreIndex = -1;
$secondPreIndex = -1;
$totalLines = sizeof($fileContentArr);
// Go to first occerences of <pre>
$lineNo = 0;
for(; $lineNo<$totalLines; $lineNo++) {
	$str = trim($fileContentArr[$lineNo]);
	if( $str == "<pre>") {
		$firstPreIndex = $lineNo;
		break;
	}
}
$totalCpuTime = 0;
// Get total Cpu Time
if( $firstPreIndex == -1 ) {
	exit("Program is exiting");
}
$lineNo++;

// Get the second occurence of <pre>
for(; $lineNo<$totalLines; $lineNo++) {
	$str = trim($fileContentArr[$lineNo]);
	if( $str == "<pre>" ) {
		$secondPreIndex = $lineNo;
		break;
	}
}

$lineNo = $secondPreIndex + 3;

$str = trim( $fileContentArr[$lineNo] );
$arr = preg_split("/\s+/", $str);
// Get total cpu time from line
if( sizeof($arr) > 0 ) {
	$totalCpuTime = $arr[ sizeof($arr) - 2];
	$lineNo++;
}

/**************** Retrieve Mthod name along with package and its info along with children and parents ******************/

// starting from start anchor tag
$lineNo = $secondPreIndex + 2;
$str = trim( $fileContentArr[ $lineNo ] );
echo "<table>"."\n";

		echo "<tr>"."\n";
			echo "<td>"."Id"."</td>"."\n";
			echo "<td class=\"name\";  style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">"."Method Name"."</td>"."\n";
			echo "<td>"."Incl Cpu Time(%)"."</td>"."\n";
			echo "<td>"."Incl Cpu Time"."</td>"."\n";
			echo "<td>"."Excl Cpu Time(%)"."</td>"."\n";
			echo "<td>"."Excl Cpu Time"."</td>"."\n";
			echo "<td>"."Total Call"."</td>"."\n";
			echo "<td>"."Cpu Time / Call"."</td>"."\n";
		echo "</tr>"."\n";
// loop for each method
while( !isEndPreTag( $str ) ) {

	//echo "Hello". "\n";
	$traversingParents = true;
	$callType = "normal";

	// array for each method
	$method = array();
	$method["info"] = array();
	$method["parents"] = array();
	$method["parents"]["normal"] = array();
	$method["parents"]["recur"] = array();
	$method["children"] = array();
	$method["children"]["normal"] = array();
	$method["children"]["recur"] = array();

	// for each method informations
	while( !isStartAnchorTag( $str ) && !isEndPreTag( $str ) ) { 

		$arr = preg_split("/\s+/", $str);

		if( isSeparator($str) ) {
			$callType = "recur";
			$lineNo++;
			$str = trim( $fileContentArr[ $lineNo ] );
			continue;
		}

		if( isSelf( $arr ) ) {
			//$currentMethod = getInfo( $arr );
			$method["info"] = getInfo( $arr );
			//print_r( getInfo( $arr ) );
			$callType = "normal";

			// for excl line
			$lineNo++;
			$str = trim( $fileContentArr[ $lineNo ] );
			$arr = preg_split("/\s+/", $str);
			//echo "        >Excl Cpu Time : ".getExclCpuTime( $arr )."\n";
			$exclCpuTime = getExclCpuTime( $arr );
			$method["info"]["exclCpuTime"] = $exclCpuTime;

			// line after excl, i.e normal line
			$lineNo++;
			$str = trim( $fileContentArr[ $lineNo ] );
			$traversingParents = false;
			continue;
		} 

		if(isExclLine($arr)) {
			$lineNo++;
			$str = trim( $fileContentArr[ $lineNo ] );
			continue;
		}

		// Traverse parents
		if( $traversingParents ) {
			if( $callType == "normal" ) {
				array_push( $method["parents"]["normal"], getInfo( $arr ) );
				//$method["parents"]["normal"] = getInfo( $arr );
				//print_r( getInfo( $arr ) );
			}
			else {
				array_push( $method["parents"]["recur"], getInfo( $arr ) );
				//$method["parents"]["recur"] = getInfo( $arr );
				//print_r( getInfo( $arr ) );
			}
		}
		else {  // Traverse children

			if( $callType == "normal" ) {
				array_push( $method["children"]["normal"], getInfo( $arr ) );
				//$method["children"]["normal"] = getInfo( $arr );
				//print_r( getInfo( $arr ) );
			} else {
				array_push( $method["children"]["recur"], getInfo( $arr ) );
				//$method["children"]["recur"] = getInfo( $arr );
				//print_r( getInfo( $arr ) );
			}

		}

		$lineNo++;
		$str = trim( $fileContentArr[ $lineNo ] );
	}

	if( !empty( $method['info'] ) ) {
		calculateInclCpuTimePercent( $method, $totalCpuTime );
		//print_r( $method );
		displayInfo( $method );
	}

	if( isEndPreTag( $str ) ) {
		break;
	}
	$lineNo++;
	$str = trim( $fileContentArr[ $lineNo ] );
}

echo "</table>"."\n";

echo "first Index of <pre> : ".$firstPreIndex. "\n";
echo "second Index of <pre> : ".$secondPreIndex. "\n";
echo "total Cpu Time : ".$totalCpuTime. "\n";


/********************** Functions ************************/


// Check whether the line is separator or not

function isSeparator( $lineAsStr ) {
	$lineAsStr = trim($lineAsStr);
	if( substr($lineAsStr, 0, 5) == "+++++" ) {
		return true;
	}
	return false;
}

// Check start of Anchor tag
function isStartAnchorTag( $str ) {
	$str = trim( $str );
	if( substr($str, 0, 7) == "<a name" ) {
		return true;
	}
	return false;
}

function isEndPreTag( $str ) {
	$str = trim( $str );
	if( substr($str, 0, 6) == "</pre>" ) {
		return true;
	}
	return false;
}

// is current method self(method for which we are retrieving infor)
function isSelf($arr) {
	if( sizeof( $arr ) > 1 ) {	
		$firstWord = trim( $arr[0] );
		$secondWord = trim( $arr[1] );
	}
	else {
		exit("Inside isSelf()");
	}
	if( preg_match( "/[\d+]/", $firstWord ) && ( $secondWord[ strlen( $secondWord) - 1 ] ) == "%" ) {
		//echo "$strLine";
		return true;
	}
	return false;
}

// Get id of method
function getIdForMethod( $arr ) {
	for($i=0; $i<sizeof($arr); $i++) {
		if( preg_match("/[d+]/", $arr[ $i ] ) ) {
			return $i;
		}
	}
	return -1;
}

// Get method name
function getMethodName( $arr ) {
	$methodName = "";
	//$idIndex = getIdForMethod( $arr );
	// Retrieve method name which is 3 offset from idIndxe
	if( isSelf(  $arr ) ) {
		for($i=4; $i<sizeof($arr); $i++) {
			$str = trim( $arr[$i] );
			$methodName = $methodName." ".$str." ";
		}
	} else {
		$id = getAnchorIndex( $arr );
		for($i=$id+4; $i<sizeof($arr); $i++) {
			$str = trim( $arr[$i] );
			$methodName = $methodName." ".$str." ";
		}
	}
	return trim($methodName);
}

// check for excl line
function isExclLine( $arr ) {
	if( !isSet($arr[1])) {
		print_r($arr); exit;
	}
	if( trim( $arr[1] ) == "excl" ) {
		return true;
	}
	return false;
}

// index of closing anchor
function getClosingAnchorIndex( $arr ) {
	for ($i=0; $i < sizeof($arr); $i++) { 
		if( endsWith( $arr[$i], "</a>" ) ) {
			return $i;
		}
	}
	return -1;
}

function endsWith($haystack, $needle)
{
    $length = strlen($needle);

    return $length === 0 || 
    (substr($haystack, -$length) === $needle);
}

function getAnchorIndex( $arr ) {
	for ($i=0; $i < sizeof($arr); $i++) { 
		$str = trim( $arr[$i] );
		if(substr($str, 0, 2) == "<a") {
			return $i;
		}
	}
	return -1;
}


function checkTypesOfLine( $line ) {
	$line = trim( $line );
	if( isSeparator( $line ) ) {
		return "separator";
	} 
	$arr = preg_split("/\s+/", $line);

	if( isSelf( $arr ) ) {
		return "self";
	} else if( isNormalLine( $arr ) ) {
		return "normal";
	} else if( isExclLine( $arr ) ) {
		return "excl";
	} else {
		return "unknown";
	}

}

function isNormalLine( $arr ) {
	for ($i=0; $i < sizeof($arr); $i++) { 
		$str = trim( $arr[$i] );
		if(substr($str, 0, 7 ) == "<a href") {
			return true;
		}
	}
	return false;
}


function getMethodId( $arr ) {
	$id = -1;
	if( isSelf( $arr ) ) {
		$str = trim( $arr[ 0 ] );
			$id = substr($str, 1, strlen( $str ) - 2 );

	}
	else {
		$pattern = "/\[\d+\]/";
		$j = -1;
		for($i=0; $i<sizeof($arr); $i++) {
			$s = trim( $arr[$i] );
			if($s == "<a") {
				$j = $i + 1;
				break;
			}
		}
		$str = trim( $arr[ $j ] );
		preg_match($pattern, $str, $matches);
		$str = $matches[ 0 ];
		//echo $str;
		$id = substr( $str, 1, strlen( $str ) - 2 );	
	}
	return $id;
}

/*************** Calculation Func  ******************/

function getInclCpuTime( $arr ) {
	if( isSelf( $arr ) ) {
		$str = trim( $arr[ 3 ] );
		return $str / 1000;
	} else {
		$id = getAnchorIndex( $arr );
		$str = trim( $arr[ $id + 3 ] );
		return $str / 1000;
	}
}

function convertCallToNumber( $call ) {
	$arr = preg_split( "/[+]/", trim( $call ) );
	if( $arr[0] + $arr[1]  == 0 ) {
		return 1;
	} else {
		return $arr[0] + $arr[1];
	}
}

function calculateInclCpuTimePercent( & $method, $totalInclCpuTime ) {
 
 	//print_r( $method ); exit;

 	$inclCpuTime = $method["info"]["inclCpuTime"] * 1000;
 	//print_r($method["info"]);

 	// Cpu Time / Call 
 	$callInStrForm = $method["info"]["totalCall"];
 	$cpuTimePerCall = $inclCpuTime / convertCallToNumber( $callInStrForm ) / 1000;
 	$method["info"]["cpuTimePerCall"] = round( $cpuTimePerCall, 3 );

 	// Excl Cpu Time (%)
 	$exclCpuTime = $method["info"]["exclCpuTime"];
 	$exclCpuTimePercent = ( ( $exclCpuTime / $totalInclCpuTime ) * 100 );
 	$method["info"]["exclCpuTimePercent"] = round( $exclCpuTimePercent , 1 );

 	// Incl Cpu Time (%)
	$percent = ( ( $inclCpuTime / $totalInclCpuTime ) * 100 );
	$method["info"]["inclCpuTimePercent"] = round( $percent, 1 );

	// For parents and children
	$totalInclCpuTime = $method["info"]["inclCpuTime"] * 1000;

	// Normal parents
	if( !empty( $method["parents"]["normal"] ) ) {
		for($index=0; $index<sizeof( $method['parents']['normal'] ); $index++ ) {
			$infoArr = $method['parents']['normal'][$index];

			$percent = ( $method['parents']['normal'][$index]["inclCpuTime"] / $totalInclCpuTime ) * 100;
			$method['parents']['normal'][$index]["inclCpuTimePercent"] = round( $percent, 1 );
		}
	}

	// Recur parents
	if( !empty( $method["parents"]["recur"] ) ) {
		for($index=0; $index<sizeof( $method['parents']['recur'] ); $index++ ) {
			$infoArr = $method['parents']['recur'][$index];

			$percent = ( $method['parents']['recur'][$index]["inclCpuTime"] / $totalInclCpuTime ) * 100;
			$method['parents']['recur'][$index]["inclCpuTimePercent"] = round( $percent, 1 );
		}
	}

	// Normal children
	if( !empty( $method["children"]["normal"] ) ) {
		for($index=0; $index<sizeof( $method['children']['normal'] ); $index++ ) {
			$infoArr = $method['children']['normal'][$index];

			$percent = ( $method['children']['normal'][$index]["inclCpuTime"] / $totalInclCpuTime ) * 100;
			$method['children']['normal'][$index]["inclCpuTimePercent"] = round( $percent, 1 );
		}
	}

	// Recur children
	if( !empty( $method["parents"]["recur"] ) ) {
		for($index=0; $index<sizeof( $method['children']['recur'] ); $index++ ) {
			$infoArr = $method['children']['recur'][$index];

			$percent = ( $method['children']['recur'][$index]["inclCpuTime"] / $totalInclCpuTime ) * 100;
			$method['children']['recur'][$index]["inclCpuTimePercent"] = round( $percent, 1 );
		}
	}
	//print_r( $method ); exit;
}

function getExclCpuTime ( $arr ) {
	$exclTime = -1;
	if(	isExclLine( $arr ) ) {
		$exclTime = trim( $arr[ 2 ] );
	}
	return $exclTime;
}

function getTotalCalls( $arr ) {
	if( isSelf( $arr ) ) {
		$str = trim( $arr[ 2 ] );
		return $str;
	} else {
		$id = getAnchorIndex( $arr );
		$str = trim( $arr[ $id + 2 ] );
		return $str;
	}
}

function getInfo( $arr ) {

	// Getting data
	$newArr = array();
	$name = getMethodName( $arr );
	$id = getMethodId( $arr );
	$inclCpuTime = getInclCpuTime( $arr );
	$totalCall = getTotalCalls( $arr );

	// Assigning data
	$newArr["id"] = $id;
	$newArr["name"] = $name;
	$newArr["inclCpuTimePercent"] = -1;
	$newArr["inclCpuTime"] = $inclCpuTime;
	$newArr["totalCall"] = $totalCall;
	$newArr["exclCpuTimePercent"] = -1;
	$newArr["exclCpuTime"] = -1;
	$newArr["cpuTimePerCall"] = -1;

	return $newArr;
}

function printInfo( $arr ) {
	echo $arr["id"]."***".$arr["name"]."***".$arr["inclCpuTime"]."***".$arr["totalCall"]."\n";
}



/********************** Display Info as html********************************/
function displayInfo( $method ) {
	
		echo "<tr data-my-id='".$method['info']['id']."' class=\"currentMethodRow parent-click-class\"; style=\"text-overflow: ellipsis;\">";
			echo "<td id=\"m".$method['info']['id']."\">".$method['info']['id']."</td>"."\n";
			echo "<td class=\"name\";  style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">".$method['info']['name']."</td>"."\n";
			echo "<td>".$method['info']['inclCpuTimePercent']."%</td>"."\n";
			echo "<td>".$method['info']['inclCpuTime']."</td>"."\n";
			echo "<td>".$method['info']['exclCpuTimePercent']."%</td>"."\n";
			echo "<td>".$method['info']['exclCpuTime']."</td>"."\n";
			echo "<td>".$method['info']['totalCall']."</td>"."\n";
			echo "<td>".$method['info']['cpuTimePerCall']."</td>"."\n";
		echo "</tr>"."\n";

		// Parents
		echo "<tr class='pchild-click-class class-".$method['info']['id']."' data-id='".$method['info']['id']."' style=\"background-color:#9999ff; margin-left:300px;display:none;\">";
			echo "<td>Parents</td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
		echo "</tr>"."\n";

		// Normal parents
		if( !empty( $method['parents']['normal'] ) ) {
			for($i=0; $i<sizeof( $method['parents']['normal'] ); $i++ ) {
				$arr = $method['parents']['normal'][$i];
				echo "<tr class='normalParents child-click-class pchild-class-".$method['info']['id']."' style='display:none'; bgcolor=\"#9999ff\">";
					echo "<td><a href=\"#m".$arr['id']."\">".$arr['id']."</a></td>"."\n";
					echo "<td class=\"name\";  style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">".$arr['name']."</td>"."\n";
					echo "<td>".$arr['inclCpuTimePercent']."%</td>"."\n";
					echo "<td>".$arr['inclCpuTime']."</td>"."\n";
					echo "<td></td>"."\n";
					echo "<td></td>"."\n";
					echo "<td>".$arr['totalCall']."</td>"."\n";
					echo "<td></td>"."\n";
				echo "</tr>"."\n";
			}
		}


		// Recur parents
		for($i=0; $i<sizeof( $method['parents']['recur'] ); $i++ ) {
				$arr = $method['parents']['recur'][$i];
				echo "<tr class='recurParents child-click-class pchild-class-".$method['info']['id']."' style='display:none'; bgcolor=\"#ccccff\">";
					echo "<td><a href=\"#m".$arr['id']."\">".$arr['id']."</a></td>"."\n";
					echo "<td class=\"name\";  style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">".$arr['name']."</td>"."\n";
					echo "<td>".$arr['inclCpuTimePercent']."%</td>"."\n";
					echo "<td>".$arr['inclCpuTime']."</td>"."\n";
					echo "<td></td>"."\n";
					echo "<td></td>"."\n";
					echo "<td>".$arr['totalCall']."</td>"."\n";
					echo "<td></td>"."\n";
				echo "</tr>"."\n";
		}

		// Children
		echo "<tr class='cchild-click-class class-".$method['info']['id']."' data-id='".$method['info']['id']."' bgcolor=\"#ffff4d\" style='display:none'>";
			echo "<td>Children</td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
			echo "<td></td>"."\n";
		echo "</tr>"."\n";
		// Normal children
		for($i=0; $i<sizeof( $method['children']['normal'] ); $i++ ) {
				$arr = $method['children']['normal'][$i];
				echo "<tr class='normalChildren cchild-click-class cchild-class-".$method['info']['id']."' style='display:none'; bgcolor=\"#ffff4d\">";
					echo "<td><a href=\"#m".$arr['id']."\">".$arr['id']."</a></td>"."\n";
					echo "<td class=\"name\";  style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">".$arr['name']."</td>"."\n";
					echo "<td>".$arr['inclCpuTimePercent']."%</td>"."\n";
					echo "<td>".$arr['inclCpuTime']."</td>"."\n";
					echo "<td></td>"."\n";
					echo "<td></td>"."\n";
					echo "<td>".$arr['totalCall']."</td>"."\n";
					echo "<td></td>"."\n";
				echo "</tr>"."\n";
		}


		// Recur children
		for($i=0; $i<sizeof( $method['children']['recur'] ); $i++ ) {
				$arr = $method['children']['recur'][$i];
				echo "<tr class='recurChildren cchild-click-class cchild-class-".$method['info']['id']."' style='display:none'; bgcolor=\"#ffffcc\">";
					echo "<td><a href=\"#m".$arr['id']."\">".$arr['id']."</a></td>"."\n";
					echo "<td class=\"name\"; style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">".$arr['name']."</td>"."\n";
					echo "<td>".$arr['inclCpuTimePercent']."%</td>"."\n";
					echo "<td>".$arr['inclCpuTime']."</td>"."\n";
					echo "<td></td>"."\n";
					echo "<td></td>"."\n";
					echo "<td>".$arr['totalCall']."</td>"."\n";
					echo "<td></td>"."\n";
				echo "</tr>"."\n";
		}
}