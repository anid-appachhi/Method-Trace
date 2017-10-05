<?php

$packageName = 'com.appachhi.bugninja';
//$packageName = 'com.bugtags.demo';
$traceFile = 'methodProfiling';

$fileLocation = "/sdcard/$traceFile.trace";
$saveLocation = "/home/anid/Desktop/.";
$deviceId = "4203aa3cca654100";

runMethodTraceCommand( $packageName, $deviceId );
stopMethodTraceCommand( $packageName, $deviceId );

// Get the generated trace file to specified location
pullMethodTraceFile( $fileLocation, $saveLocation, $deviceId );

// Convert trace file to html
runDmTraceDumpCommand( $traceFile );
sleep(1);

// Get data from html trace fill and display it with customization
buildMehodProfileViewerHtml( $traceFile );

/******************************Functions***********************************/

// function to start tracing the app
function runMethodTraceCommand( $packageName, $deviceId) {

	// Location to generate file
	$location = "/sdcard/methodProfiling.trace";
	shell_exec( "adb -s $deviceId shell am start -n $packageName/$packageName.activities.MainActivity --start-profiler $location" );
	sleep(5);
}

// function to stop tracing the app
function stopMethodTraceCommand( $packageName, $deviceId ) {

	system( "adb -s $deviceId shell am profile stop $packageName" );
	sleep(2);
}

// function to pull trace file from the device
function pullMethodTraceFile( $fileLocation, $saveLocation, $deviceId ) {

	//$fileName = "methodProfiling";
	//$traceFile = $dirReports . $fileName . "device_app_run_log.txt";
	system( "adb -s " . $deviceId . " pull $fileLocation $saveLocation" );
}

// funciton to run the ddmtracedump function to convert .trace file to html file
function runDmTraceDumpCommand( $traceFile ) {

	$fileLocation = "/home/anid/Desktop/$traceFile.trace";
	$saveLocation = "/home/anid/Desktop/.";
	shell_exec( "dmtracedump -h $fileLocation > $saveLocation/$traceFile.html" );
}

// Function to wait for given milliseconds
function wait( $timeInMilliSeconds ) {

	$timeInSeconds = floor( $timeInMilliSeconds / 1000 );
	$timeInNanoSeconds = ( $timeInMilliSeconds % 1000 ) * 1000;
	$nanoSeconds = time_nanosleep( $timeInSeconds, $timeInNanoSeconds );
	if( $nanoSeconds === true ) {
		//echo "Hello\n Hello\n Hello\n Hello\n Hello";
	}

}


function runTrace( $packageName, $timeInMilliSeconds ) {

	runMethodTraceCommand( $packageName );
	wait( $timeInMilliSeconds );
	stopMethodTraceCommand( $packageName );

}

// funciton to parse the generated html file form the above function to build the html format just like ddms method profile viewer
function buildMehodProfileViewerHtml( $ddmsGeneratedHtmlFile ) {

	//use file function to read it into a file
	//$htmlContent = file($ddmsGeneratedHtmlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

	$fileLocation = "/home/anid/Desktop";
	$customizedHtmlLocation = "/home/anid/Desktop/.";

	$htmlContent = file( $fileLocation."/".$ddmsGeneratedHtmlFile.".html" );
	$totalLines = sizeof( $htmlContent );

	//$secondPreIndex = getLineNumberOfPreAnchorTag( $ddmsGeneratedHtmlFile, 2 );
	$totalMethodsCount = 0;
	$totalCpuCyclesTime = 0;
	$totalCalls = 0;
	$count = 0;

	$methodsWithMoreTime = array();
	/*$methodsWithMoreTime["info"] = array();
	$methodsWithMoreTime["parents"] = array();
	$methodsWithMoreTime["parents"]["normal"] = array();
	$methodsWithMoreTime["parents"]["recur"] = array();
	$methodsWithMoreTime["children"] = array();
	$methodsWithMoreTime["children"]["normal"] = array();
	$methodsWithMoreTime["children"]["recur"] = array();*/

	$firstPreIndex = -1;
	$secondPreIndex = -1;

	$lineNo = 0;
	// Get first line number of <pre> tag
	for(; $lineNo<$totalLines; $lineNo++) {

		$str = trim( $htmlContent[ $lineNo ] );
		if( $str == "<pre>") {
			$firstPreIndex = $lineNo;
			break;
		}
	}

	// Move ahead to next line from <pre> tag
	$lineNo++;

	// Get the second line number of <pre> tag
	for(; $lineNo<$totalLines; $lineNo++) {

		$str = trim( $htmlContent[ $lineNo ] );
		if( $str == "<pre>" ) {
			$secondPreIndex = $lineNo;
			break;
		}
	}

	$totalCpuTime = 0;

	// Got to line which contains total cpu time
	$lineNo = $secondPreIndex + 3;

	$str = trim( $htmlContent[ $lineNo ] );
	$arr = preg_split("/\s+/", $str);

	// Get total cpu time from line
	if( sizeof($arr) > 0 ) {
		$totalCpuTime = $arr[ sizeof($arr) - 2];
		$lineNo++;
	}

	// Total execution time of cpu
	$totalCpuCyclesTime = $totalCpuTime;

	// starting from start anchor tag
	$lineNo = $secondPreIndex + 2;
	$str = trim( $htmlContent[ $lineNo ] );

	// start writing to the customized html file
	$handle = fopen( $customizedHtmlLocation."/customizedHtmlmethodProfiling.html", 'w');

	// writing header part
	fwrite($handle, "<!DOCTYPE html>
	<html>
	<head>
		<title>Bugninja Method Trace</title>

		<script src=\"https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.js\"></script>
	</head>
	<body>");

	// writing middle part

	fwrite( $handle, "<table>

		<tr>
			<td>Id</td>
			<td class=\"name\";  style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">Method Name</td>
			<td>Incl Cpu Time(%)</td>
			<td>Incl Cpu Time</td>
			<td>Excl Cpu Time(%)</td>
			<td>Excl Cpu Time</td>
			<td>Total Call</td>
			<td>Cpu Time / Call</td>
		</tr>");

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

		// count to only consider top 6 methods w.r.t execution time
		$count++;

		// for each method informations
		while( !isStartAnchorTag( $str ) && !isEndPreTag( $str ) ) { 

			$arr = preg_split("/\s+/", $str);

			if( isSeparator($str) ) {
				$callType = "recur";
				$lineNo++;
				$str = trim( $htmlContent[ $lineNo ] );
				continue;
			}

			if( isSelf( $arr ) ) {
				//$currentMethod = getInfo( $arr );
				$method["info"] = getInfo( $arr );
				$totalMethodsCount++;

				// including only top 6 methods w.r.t. execution time
				/*if( $count <= 6 ) {
					$methodsWithMoreTime[ "info" ] = getInfo( $arr );
				}*/
				//print_r( getInfo( $arr ) );
				$callType = "normal";

				// for excl line
				$lineNo++;
				$str = trim( $htmlContent[ $lineNo ] );
				$arr = preg_split("/\s+/", $str);
				//echo "        >Excl Cpu Time : ".getExclCpuTime( $arr )."\n";
				$exclCpuTime = getExclCpuTime( $arr );
				$method["info"]["exclCpuTime"] = $exclCpuTime;

				// line after excl, i.e normal line
				$lineNo++;
				$str = trim( $htmlContent[ $lineNo ] );
				$traversingParents = false;
				continue;
			} 

			if(isExclLine($arr)) {
				$lineNo++;
				$str = trim( $htmlContent[ $lineNo ] );
				continue;
			}

			// Traverse parents
			if( $traversingParents ) {
				if( $callType == "normal" ) {
					array_push( $method["parents"]["normal"], getInfo( $arr ) );
					//array_push( $methodsWithMoreTime, $method );
				}
				else {
					array_push( $method["parents"]["recur"], getInfo( $arr ) );
					//array_push( $methodsWithMoreTime, $method );
				}
			}
			else {  // Traverse children

				if( $callType == "normal" ) {
					array_push( $method["children"]["normal"], getInfo( $arr ) );
					//array_push( $methodsWithMoreTime, $method );
				} else {
					array_push( $method["children"]["recur"], getInfo( $arr ) );
					//array_push( $methodsWithMoreTime, $method );
				}

			}

			$lineNo++;
			$str = trim( $htmlContent[ $lineNo ] );
		}

		if( !empty( $method['info'] ) ) {
			calculateInclCpuTimePercent( $method, $totalCpuTime );
			$totalCalls += getCallForEachMethod( $method );
			if(  $count <= 6 ) {
				array_push( $methodsWithMoreTime, $method );
			}
			displayInfo( $handle, $method );
		}

		if( isEndPreTag( $str ) ) {
			break;
		}
		$lineNo++;
		$str = trim( $htmlContent[ $lineNo ] );
	}

	displayTopExecutionMethod( $handle, $methodsWithMoreTime );
	
	fwrite($handle, "
	</table>");



	// writing script and ending part
	fwrite($handle, "$totalMethodsCount;
	$totalCpuCyclesTime;
	$totalCalls;

		<script type=\"text/javascript\">
	$(document).ready(function() {

		var elelmentID;
		$('.parent-click-class').click(function(e) {
			var childClassName = '.class-';
			elelmentID = $(this).attr('data-my-id');
			childClassName += elelmentID;
			console.log(childClassName);
			$(childClassName).toggle();
			var cChildClassName = '.cchild-class-'+elelmentID;
			var pChildClassName = '.pchild-class-'+elelmentID;
			$(cChildClassName).hide();
			$(pChildClassName).hide();
		});

		$('.cchild-click-class').click(function(e) {
			var cChildClassName = '.cchild-class-';
			elelmentID = $(this).attr('data-id');
			cChildClassName += elelmentID;
			$(cChildClassName).toggle();
		});

		$('.pchild-click-class').click(function(e) {
			var pChildClassName = '.pchild-class-';
			elelmentID = $(this).attr('data-id');
			pChildClassName += elelmentID;
			$(pChildClassName).toggle();
		});

	});

	</script>
	</body>
	</html>");
	

}


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

function getCallForEachMethod( & $method ) {

	$callInStrForm = $method["info"]["totalCall"];
 	$callInNum = convertCallToNumber( $callInStrForm );
 	return $callInNum;
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
function displayInfo( $handle, $method ) {
	
		fwrite( $handle, "<tr data-my-id='".$method['info']['id']."' class=\"currentMethodRow parent-click-class\"; style=\"text-overflow: ellipsis;\">
			<td id=\"m".$method['info']['id']."\">".$method['info']['id']."</td>
			<td class=\"name\";  style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">".$method['info']['name']."</td>
			<td>".$method['info']['inclCpuTimePercent']."%</td>
			<td>".$method['info']['inclCpuTime']."</td>
			<td>".$method['info']['exclCpuTimePercent']."%</td>
			<td>".$method['info']['exclCpuTime']."</td>
			<td>".$method['info']['totalCall']."</td>
			<td>".$method['info']['cpuTimePerCall']."</td>
		</tr>
		");
		// Parents
		fwrite( $handle, "<tr class='pchild-click-class class-".$method['info']['id']."' data-id='".$method['info']['id']."' style=\"background-color:#9999ff; margin-left:300px;display:none;\">
			<td>Parents</td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
		</tr>
		");

		// Normal parents
		if( !empty( $method['parents']['normal'] ) ) {
			for($i=0; $i<sizeof( $method['parents']['normal'] ); $i++ ) {
				$arr = $method['parents']['normal'][$i];
				fwrite( $handle, "<tr class='normalParents child-click-class pchild-class-".$method['info']['id']."' style='display:none'; bgcolor=\"#9999ff\">
					<td><a href=\"#m".$arr['id']."\">".$arr['id']."</a></td>
					<td class=\"name\";  style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">".$arr['name']."</td>
					<td>".$arr['inclCpuTimePercent']."%</td>
					<td>".$arr['inclCpuTime']."</td>
					<td></td>
					<td></td>
					<td>".$arr['totalCall']."</td>
					<td></td>
				</tr>
				");
			}
		}


		// Recur parents
		for($i=0; $i<sizeof( $method['parents']['recur'] ); $i++ ) {
				$arr = $method['parents']['recur'][$i];
				fwrite( $handle, "<tr class='recurParents child-click-class pchild-class-".$method['info']['id']."' style='display:none'; bgcolor=\"#ccccff\">
					<td><a href=\"#m".$arr['id']."\">".$arr['id']."</a></td>
					<td class=\"name\";  style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">".$arr['name']."</td>
					<td>".$arr['inclCpuTimePercent']."%</td>
					<td>".$arr['inclCpuTime']."</td>
					<td></td>
					<td></td>
					<td>".$arr['totalCall']."</td>
					<td></td>
				</tr>
				");
		}

		// Children
		fwrite( $handle, "<tr class='cchild-click-class class-".$method['info']['id']."' data-id='".$method['info']['id']."' bgcolor=\"#ffff4d\" style='display:none'>
			<td>Children</td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
			<td></td>
		</tr>
		");
		// Normal children
		for($i=0; $i<sizeof( $method['children']['normal'] ); $i++ ) {
				$arr = $method['children']['normal'][$i];
				fwrite( $handle, "<tr class='normalChildren cchild-click-class cchild-class-".$method['info']['id']."' style='display:none'; bgcolor=\"#ffff4d\">
					<td><a href=\"#m".$arr['id']."\">".$arr['id']."</a></td>
					<td class=\"name\";  style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">".$arr['name']."</td>
					<td>".$arr['inclCpuTimePercent']."%</td>
					<td>".$arr['inclCpuTime']."</td>
					<td></td>
					<td></td>
					<td>".$arr['totalCall']."</td>
					<td></td>
				</tr>
				");
		}


		// Recur children
		for($i=0; $i<sizeof( $method['children']['recur'] ); $i++ ) {
				$arr = $method['children']['recur'][$i];
				fwrite( $handle, "<tr class='recurChildren cchild-click-class cchild-class-".$method['info']['id']."' style='display:none'; bgcolor=\"#ffffcc\">
					<td><a href=\"#m".$arr['id']."\">".$arr['id']."</a></td>
					<td class=\"name\"; style=\"max-width:300px; text-overflow: ellipsis; overflow:hidden;\">".$arr['name']."</td>
					<td>".$arr['inclCpuTimePercent']."%</td>
					<td>".$arr['inclCpuTime']."</td>
					<td></td>
					<td></td>
					<td>".$arr['totalCall']."</td>
					<td></td>
				</tr>
				");
		}
}

function displayTopExecutionMethod( $handle, $methodsWithMoreTime ) {

	for($index=0; $index<5; $index++ ) {

		displayInfo( $handle, $methodsWithMoreTime[ $index ] );

	}
}

?>
