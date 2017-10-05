<?php

	$resultWithoutThread = array();
	$resultWithoutThread = buildMehodProfileViewerHtml( "/home/anid/Desktop/methodProfiling.html" );
	//print_r( $resultWithoutThread['allMethods'] );
	addThreadNameToMethod( $resultWithoutThread['allMethods'], "/home/anid/Desktop/trace.txt" );
	print_r( $resultWithoutThread['allMethods'] );


	/**
	 * this function will take html file as input (one generated from trace file) and returns all the informations of each method as array
	 *
	 * @param  $ddmsGeneratedHtmlFile
	 * @return  $result
	 */
	function buildMehodProfileViewerHtml( $ddmsGeneratedHtmlFile ) {

		$htmlContent = file( $ddmsGeneratedHtmlFile );

		$totalLines = sizeof( $htmlContent );

		$result = array();

		$totalMethodsCount = 0;
		$totalCpuCyclesTime = 0;
		$totalCalls = 0;
		$count = 0;

		$methodsWithMoreTime = array();
		$allMethods = array();

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

		$str = trim( isset($htmlContent[ $lineNo ]) ? $htmlContent[ $lineNo ] : '' );
		$arr = preg_split("/\s+/", $str);

		// Get total cpu time from line
		if( sizeof($arr) > 0 ) {
			$totalCpuTime = isset($arr[ sizeof($arr) - 2]) ? $arr[ sizeof($arr) - 2] : '' ;
			$lineNo++;
		}

		// Total execution time of cpu
		$totalCpuCyclesTime = round($totalCpuTime/1000,2);

		// starting from start anchor tag
		$lineNo = $secondPreIndex + 2;
		$str = trim( isset( $htmlContent[ $lineNo ] ) ? $htmlContent[ $lineNo ]  : '' );

		$toplevelMethodCount = 0;

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
					$method["info"] = getInfo( $arr );
					$totalMethodsCount++;
					$callType = "normal";

					// for excl line
					$lineNo++;
					$str = trim( $htmlContent[ $lineNo ] );
					$arr = preg_split("/\s+/", $str);
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
					}
					else {
						array_push( $method["parents"]["recur"], getInfo( $arr ) );
					}
				}
				else {  // Traverse children

					if( $callType == "normal" ) {
						array_push( $method["children"]["normal"], getInfo( $arr ) );
					} else {
						array_push( $method["children"]["recur"], getInfo( $arr ) );
					}

				}

				$lineNo++;
				$str = trim( isset($htmlContent[ $lineNo ]) ? $htmlContent[ $lineNo ] : '' );
			}

			if( !empty( $method['info'] ) ) {
				calculateInfos( $method, $totalCpuTime );
				$totalCalls += getCallForEachMethod( $method );


				if( $method['info']['methodName'] == "unknown" ) {
					//$toplevelMethodCount++;


					// reset values
					$totalMethodsCount = 0;
					$totalCalls = 0;
					$toplevelMethodCount = 1;
					unset( $methodsWithMoreTime );
					$methodsWithMoreTime = array();
					unset( $allMethods );
					$allMethods = array();
				} else {


					//if(  $count <= 6 ) {
					if(  sizeof( $methodsWithMoreTime ) < 5 ) {
							array_push( $methodsWithMoreTime, $method );
					}
					array_push( $allMethods, $method );
					$totalCpuCyclesTime = $allMethods[0]['info']['inclCpuTime'];
				}
			}

			if( isEndPreTag( $str ) ) {
				break;
			}
			$lineNo++;
			$str = trim( $htmlContent[ $lineNo ] );
		}
		$result["moreTime"] = $methodsWithMoreTime;
		$result["allMethods"] = $allMethods;
		$result["methodCount"] = $totalMethodsCount;
		$result["totalCpuCycles"] = $totalCpuCyclesTime;
		$result["totalCalls"] = $totalCalls;

		return $result;
	}


	/**
	 * this function will Check  whether the line is separator or not i.e line which contains +++++++++. It is used to separate
	 * normal and recursive parents as well as children in the html
	 *
	 * @param  $lineAsStr
	 * @return  boolean
	 */
	function isSeparator( $lineAsStr ) {
		$lineAsStr = trim($lineAsStr);
		if( substr($lineAsStr, 0, 5) == "+++++" ) {
			return true;
		}
		return false;
	}

	/**
	 * this function will Check start of Anchor tag i.e looking for start of anchor tag (<a name)
	 * For more understanding look in the souce code of generated html file
	 *
	 * @param  $str
	 * @return  boolean
	 */
	function isStartAnchorTag( $str ) {
		$str = trim( $str );
		if( substr($str, 0, 7) == "<a name" ) {
			return true;
		}
		return false;
	}

	/**
	 * this function will Check for end of pre tag i.e </pre>
	 *
	 * @param  $str
	 * @return  boolean
	 */
	function isEndPreTag( $str ) {
		$str = trim( $str );
		if( substr($str, 0, 6) == "</pre>" ) {
			return true;
		}
		return false;
	}

	/**
	 * check whethe this is line in html for which we are retreving informations like parents and children along 
	 * with info like incl Cpu time, excl cpu time etc
	 *
	 * @param  $arr()
	 * @return  boolean
	 */
	function isSelf($arr) {
		$firstWord = '';
		$secondWord = '';
		if( sizeof( $arr ) > 1 ) {	
			$firstWord = trim( $arr[0] );
			$secondWord = trim( $arr[1] );
		}
		if( preg_match( "/[\d+]/", $firstWord ) && ( $secondWord[ strlen( $secondWord) - 1 ] ) == "%" ) {
			return true;
		}
		return false;
	}


	/**
	 * This function will retrieve total no calls which is in string form, eg 5+6, 110+4
	 * info in already stored in array
	 * @param  $arr()
	 * @return  $callInNum
	 */
	function getCallForEachMethod( & $method ) {
		$callInStrForm = $method["info"]["totalCall"];
	 	$callInNum = convertCallToNumber( $callInStrForm );
	 	return $callInNum;
	}

	/**
	 * This function will get the name of function along with package name, class name, parameters name and return type
	 *
	 * @param  $arr()
	 * @return  $methodName
	 */
	function getCompleteName( $arr ) {
		$methodName = "";
		// Retrieve method name which is 3 offset from idIndxe
		if( isSelf(  $arr ) ) {
			for($i=4; $i<sizeof($arr); $i++) {
				$str = trim( $arr[$i] );
				$methodName = $methodName." ".$str;
			}
		} else {
			$id = getAnchorIndex( $arr );
			for($i=$id+4; $i<sizeof($arr); $i++) {
				$str = trim( $arr[$i] );
				$methodName = $methodName." ".$str;
			}
		}
		return trim($methodName);
	}

	/**
	 * This function will get the id of the method from html generated
	 * look into html for more clearification
	 *
	 * @param  $arr()
	 * @return  boolean values
	 */
	function isExclLine( $arr ) {
		if( isset($arr[1] ) && trim( $arr[1] ) == "excl" ) {
			return true;
		}
		return false;
	}

	/**
	 * This function will get the id of the method from html generated
	 *
	 * @param  $arr()
	 * @return  $id
	 */
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
			$str = trim( isset($arr[ $j ]) ? $arr[ $j ] : '' );
			preg_match($pattern, $str, $matches);
			$str = isset($matches[ 0 ]) ? $matches[ 0 ] : '';
			$id = substr( $str, 1, strlen( $str ) - 2 );	
		}
		return $id;
	}

	/*************** Calculation Func  ******************/

	/**
	 * This function will get the inclusive cpu time for the function
	 *
	 * @param  $arr()
	 * @return  $str / 1000
	 */
	function getInclCpuTime( $arr ) {
		if( isSelf( $arr ) ) {
			$str = trim( $arr[ 3 ] );
			return $str / 1000;
		} else {
			$id = getAnchorIndex( $arr );
			$str = trim( isset($arr[ $id + 3 ]) ? $arr[ $id + 3 ] : '' );
			return $str / 1000;
		}
	}


	/**
	 * This function will get the line no of start anchor tag i.e <a>
	 *
	 * @param  $arr()
	 * @return  $i
	 */
	function getAnchorIndex( $arr ) {
		for ($i=0; $i < sizeof($arr); $i++) { 
			$str = trim( $arr[$i] );
			if(substr($str, 0, 2) == "<a") {
				return $i;
			}
		}
		return -1;
	}

	/**
	 * This function will convert call from string to number eg, 5+4 = 9
	 *
	 * @param  $call
	 * @return  sum of total calls
	 */
	function convertCallToNumber( $call ) {
		$arr = preg_split( "/[+]/", trim( $call ) );
		if( $arr[0] + $arr[1]  == 0 ) {
			return 1;
		} else {
			return $arr[0] + $arr[1];
		}
	}


	/**
	 * This function will get the followings
	 * 1) it calculates cpu time / call for the given method
	 * 2) it calculates exclusive cpu time in percent for the given method
	 * 3) it calculates inclusive cpu time in percent for the given method
	 * 4) it calculates inclusive cpu time in percent for every parents and children for the given method
	 *
	 * @param  $method()
	 * @param  $totalInclCpuTime
	 * @return  no return type, array is passed by reference
	 */
	function calculateInfos( & $method, $totalInclCpuTime ) {

	 	$inclCpuTime = $method["info"]["inclCpuTime"] * 1000;

	 	// Cpu Time / Call 
	 	$callInStrForm = $method["info"]["totalCall"];
	 	$cpuTimePerCall = $inclCpuTime / convertCallToNumber( $callInStrForm ) / 1000;
	 	$method["info"]["cpuTimePerCall"] = round( $cpuTimePerCall, 2 );

	 	// Excl Cpu Time (%)
	 	$exclCpuTime = round($method["info"]["exclCpuTime"]/1000,2);
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
				$percent = ( $totalInclCpuTime ) ? ( $method['parents']['normal'][$index]["inclCpuTime"] / $totalInclCpuTime ) * 100 : 1;
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

				$percent = ( $totalInclCpuTime ) ? ( $method['children']['normal'][$index]["inclCpuTime"] / $totalInclCpuTime ) * 100 : 0;
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
	}

	/**
	 * This function will get excl cpu time from array produced from line in html
	 * 
	 * @param  $arr()
	 * @return  $exclTime
	 */
	function getExclCpuTime ( $arr ) {
		$exclTime = -1;
		if(	isExclLine( $arr ) ) {
			$exclTime = trim( $arr[ 2 ] );
		}
		return $exclTime;
	}

	/**
	 * This function will total no of calls in string form from array produced from line in html
	 * 
	 * @param  $arr()
	 * @return  $str
	 */
	function getTotalCalls( $arr ) {
		if( isSelf( $arr ) ) {
			$str = trim( $arr[ 2 ] );
			return $str;
		} else {
			$id = getAnchorIndex( $arr );
			$str = trim( isset($arr[ $id + 2 ]) ? $arr[ $id + 2 ] : '' );
			return $str;
		}
	}


	/**
	 * This function will get  the followings list for the method and store in array and returns it
	 * 
	 * 1) id 
	 * 2) name (complete name along with package, class, parameter and return type name)
	 * 3) package name
	 * 4) parameter name
	 * 5) class name
	 * 6) method name
	 * 7) return type
	 * 8) incl cpu time in percent
	 * 9) incl cpu time
	 * 10) total no of calls
	 * 11) excl cpu time in percent
	 * 12) excl cpu time
	 * 13) cpu time per call
	 * @param  $arr()
	 * @return  $newArr
	 */
	function getInfo( $arr ) {

		// Getting data
		$newArr = array();
		$name = getCompleteName( $arr );
		$id = getMethodId( $arr );
		$inclCpuTime = getInclCpuTime( $arr );
		$totalCall = getTotalCalls( $arr );
		$methodName = getMethodName( $name );
		$className = getClassName( $name );
		$packageName = getPackageName( $name );
		$parameterName = getParameter( $name );
		$returnType = getReturnType( $name );

		// Assigning data
		$newArr["id"] = $id;
		$newArr["name"] = $name;
		$newArr["threadName"] = "";
		$newArr["packageName"] = $packageName;
		$newArr["parameterName"] = $parameterName;
		$newArr["className"] = $className;
		$newArr["methodName"] = $methodName;
		$newArr["returnType"] = $returnType;
		$newArr["inclCpuTimePercent"] = -1;
		$newArr["inclCpuTime"] = round($inclCpuTime,2);
		$newArr["totalCall"] = $totalCall;
		$newArr["exclCpuTimePercent"] = -1;
		$newArr["exclCpuTime"] = -1;
		$newArr["cpuTimePerCall"] = -1;

		return $newArr;
	}

	/**
	 * This function will get  method name i.e no package, class, parameter or return type name only method name
	 * 
	 * @param  $completeName
	 * @return  $methodName
	 */
	function getMethodName( $completeName ) {
		if( $completeName == "(toplevel)" )  {
			return "toplevel";
		}
		if( $completeName == "(unknown)" )  {
			return "unknown";
		}
		$arr = explode("(", $completeName );
		$fullMethodName = $arr[0];
		$methodName = substr($arr[0], strrpos($fullMethodName, '.')+1);
		return $methodName;
	}

	/**
	 * This function will get package name from complete name (complete name = name with package, class, parameter and return type)
	 * 
	 * @param  $completeName
	 * @return  $packageName
	 */
	function getPackageName( $completeName ) {

		$className = getClassName ( $completeName );
		if ( $className == '' ) return '';
		$packageName = substr($completeName, 0, strpos($completeName, $className));
		// If package Name contains '/' replace it with .
		$packageName = str_replace('/', '.', $packageName);
		return rtrim( $packageName, '.');
	}

	// it gets class name
	function getClassName ( $completeName ) {
		$arr = explode("(", $completeName );
		$packageWithClass = $arr[0];
		$explodeChar = "/";
		$classIndexDecremenetValue = 1;
		if ( substr_count($packageWithClass,'.') > 1 ) {
			$classIndexDecremenetValue = 2;
			$explodeChar = ".";
		}
		$classArr = explode( $explodeChar, $packageWithClass );
		if ( $classIndexDecremenetValue == 1 )
			return explode('.', $classArr[sizeof($classArr)-$classIndexDecremenetValue])[0];
		else return $classArr[ sizeof($classArr) - $classIndexDecremenetValue ];
	}


	/**
	 * This function will get parameters of the function
	 * 
	 * @param  $completeName
	 * @return  $params
	 */
	function getParameter( $completeName ) {
        $mehtodName = getMethodName( $completeName );
		$params = substr($completeName, strpos($completeName, $mehtodName), strpos($completeName, ')') - strpos($completeName, $mehtodName));
		$params = str_replace(array($mehtodName,'('), '', $params);
		return trim($params);
    }

    /**
	 * This function will return the return type of the function
	 * 
	 * @param  $completeName
	 * @return  $name of the return type
	 */
	function getReturnType ( $completeName ) {
		$arr = explode(")", $completeName );
		return $arr[ sizeof($arr) - 1 ];
	}


	/**
	 * This function will return the reslut with thread name added to each functions
	 * 
	 * @param  $allMethods ( array is passed by reference)
	 * @param  $fileLocation
	 */
	function addThreadNameToMethod( & $allMethods, $fileLocation ) {

		// converting file into array
		$fileAsArray = file( $fileLocation );

		$threadsIdAndName = array();
		// get threads id and name
		$threadsIdAndName = getThreads( $fileAsArray );

		// add thread name to each methods
		$methodsCount = count( $allMethods );
		for( $index = 0; $index < $methodsCount; $index++ ) {

			$name = trim( $allMethods[ $index ]['info']['name'] );
			$strWithThreadId = trim( getLineWithThreadId( $fileAsArray, $name ) );
			//echo "This is line with Thread Id : $strWithThreadId \n";
			if( $strWithThreadId != "") {
				//echo "I am superman \n";

				// get thread name
				$threadInfo = preg_split("/\s+/", trim( $strWithThreadId ), 2 );
				//echo "apple".$fileAsArray[ $index ]." \n";
				// get thread id
				$threadId = trim( $threadInfo[ 0 ] );
				//echo "It is thread Id: $threadId \n";
				// get thread name
				$threadName = trim( $threadsIdAndName[ $threadId ] );
				//echo "This is thread Name and Id: $threadName ==> $threadId\n";
				// add thread name to method
				$allMethods[ $index ][ 'info']['threadName'] = $threadName;
				//$allMethods[ $index ][ 'info']['threadName'] = "apple";
			}
		}

	}


	/**
	 * This function will return the all threads available in file
	 * 
	 * @param  $fileArray
	 * @return  $threadsIdAndName()
	 */
	function getThreads( & $fileArray ) {

		$threadsCount = 0;
		$noOfLines = sizeof( $fileArray );
		$lineNo = 0;

		for( ; $lineNo < $noOfLines; $lineNo++ ) {

			if( preg_match( "/Threads\s+\(\d+\):/", trim( $fileArray[ $lineNo ] ) ) ) {
				$threadsCount = getThreadsCount( trim( $fileArray[ $lineNo ] ) );
				$lineNo++;
				break;
			}

		}

		// array to store threads name along with its id
		$threadsIdAndName = array();
		$count = 1;
		while( $lineNo < $noOfLines && $count <= $threadsCount ) {

			$idAndName = preg_split( "/\s+/", trim( $fileArray[ $lineNo ] ), 2 );
			$id = trim( $idAndName[ 0 ] );
			$name = trim( $idAndName[ 1 ] );
			$threadsIdAndName[ $id ] = $name;
			$lineNo++;
			$count++;
		}
		//echo "got the threads \n";
		//print_r( $threadsIdAndName );
		return $threadsIdAndName;

	}

	/**
	 * This function will return the no of Threads available
	 * 
	 * @param  $string
	 * @return  no of threads if it has atleast one thread or else -1
	 */
	function getThreadsCount( $string ) {

		$matches = array();
		preg_match( "/\d+/", $string, $matches );
		print_r( $matches );
		return trim( $matches[ 0 ] );
	}

	/**
	 * This function will return line with thread id in it if $strToMatch is present in that line, otherwise returns empty string
	 * 
	 * @param  $fileAsArray()
	 * @param  $strToMatch
	 * @return  return the line that contains $strToMatch
	 */

	function getLineWithThreadId( & $fileAsArray, $strToMatch ) {

		$size = sizeof( $fileAsArray );
		for( $index = 0; $index < $size; $index++ ) {
			if( strpos( $fileAsArray[ $index ], $strToMatch ) != false ) {
				return trim( $fileAsArray[ $index ] );
			}
		}
		return "";
	}

?>
