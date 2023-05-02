<?php
// Prevent caching of page
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past



// Include SPARQL library, framework for handling SPARQL queries in PHP
require_once("sparqllib.php");

// Include config file
require_once("config.php");


// Print error message if DB connection fails 
if(!$db) {
	echo sparql_errno() . ": " . sparql_error(). "\n";
	exit;
}

// Function to format resource names used in DBpedia URIs
function dbpedia_encode($resource) {

	// URL-encode using PHP
	$encoded_resource = urlencode($resource);

	// Character conversion map
	$decoded_characters = array("!",   "$",   "&",   "'",   "(",   ")",   "*",   "+",   ",",   ".",   "/",   ":",   ";",   "=",   "@",   "_",   "~",   "%",   "-");
	$encoded_characters = array("%21", "%24", "%26", "%27", "%28", "%29", "%2A", "%2B", "%2C", "%2E", "%2F", "%3A", "%3B", "%3D", "%40", "%5F", "%7E", "%25", "%2D");
	
	// Revert some of the URL-encoded characters (because they are saved like this in DBpedia)
	$encoded_resource = str_replace($encoded_characters, $decoded_characters, $encoded_resource);

	return $encoded_resource;
}

// Fetches an abstract text from a DBpedia resource
function get_abstract($resource) {
	
	$query = "
	PREFIX dbpedia-owl: <http://dbpedia.org/ontology/>
	SELECT ?abstract
	WHERE { 
	<http://dbpedia.org/resource/" . dbpedia_encode($resource) . "> dbpedia-owl:abstract ?abstract
	FILTER(langMatches(lang(?abstract),\"en\"))
	}";
				
	$result = sparql_query( $query );
	
	if(!$result) {
		echo sparql_errno() . ": " . sparql_error();
		exit;
	}

	$fields = sparql_field_array( $result );

	while( $row = sparql_fetch_array( $result ) )
	{
		foreach( $fields as $field )
		{
			return $row[$field];
		}
	}
}


// Fetches properties for a given DBpedia resource
function get_resource_properties($resource){
	
	global $minimum_property_length;
	
	$query = "
		select distinct ?property
		where {
			<http://dbpedia.org/resource/" . dbpedia_encode($resource) . "> ?property ?value
		}";
	
	$result = sparql_query($query);
	
	if(!$result) {
		echo sparql_errno() . ": " . sparql_error();
		exit;
	}
	
	$fields = sparql_field_array($result);

	$prefixed_resource_properties = array();
	$unused_resource_properties = array();
	
	$bad_properties = file("bad_properties.txt", FILE_IGNORE_NEW_LINES);
	
	while($row = $result->fetch_array()){

		foreach( $fields as $field )
		{		
			// Look for ontology properties
			if(stripos($row[$field], "http://dbpedia.org/ontology/") !== false) {

				$property_line = str_replace("http://dbpedia.org/ontology/", "", $row[$field]);
				
				if((strlen($property_line) >= $minimum_property_length) && (!in_array($property_line, $bad_properties))) {

					$property_line = "dbpedia-owl:" . $property_line;
					
					if(stripos($property_line, "/") !== false) {
						$property_line = "<" . $row[$field] . ">";
					}
					
					$prefixed_resource_properties[] = $property_line;
				}
					
				// Looks for DBpedia properties
			} elseif (stripos($row[$field], "http://dbpedia.org/property/") !== false) {
					
				$property_line = str_replace("http://dbpedia.org/property/", "", $row[$field]);

				if((strlen($property_line) >= $minimum_property_length) && (!in_array($property_line, $bad_properties))) {
				
					$property_line = "dbpprop:" . $property_line;
					
					if(stripos($property_line, "/") !== false) {
					$property_line = "<" . $row[$field] . ">";
					}
					
					$prefixed_resource_properties[] = $property_line;
				}
					
				// Everything else (not used, only for debugging)
			} else {
					
				$unused_resource_properties[] = $row[$field];
					
			}
		}
	}	
	

	// For debugging
	/*
	echo $query;
	
	echo "<pre>";
	var_export($prefixed_resource_properties);
	echo "</pre>";

	echo "<hr />";
	
	echo "<pre>";
	var_export($unused_resource_properties);
	echo "</pre>";
	*/
	
	return $prefixed_resource_properties;
	
}


// Fetches keywords from a DBpedia resource based on abstract text
function get_abstract_keywords($resource) {
		
	// Gets the abstract text from function get_abstract() and removes periods and commas
	$abstract_text = str_replace(array('.', ','), '' , get_abstract($resource));
	
	// Gets all keywords by splitting at spaces 
	$abstract_keywords = explode(" ", $abstract_text);
	
	/* //Debugging: Print list of keywords
	foreach($abstract_keywords as $keyword) {
		echo $keyword . "<br />";
	}
	*/
	
	return $abstract_keywords;
}


// Deletes all two letter values from an array
function remove_two_letter_values($array){
	
	global $minimum_property_length;

	foreach($array as $key => $value) {
		if(strlen($value) < $minimum_property_length) {
			unset($array[$key]);
		}
	}

	return $array;
}


// Decides the relevant keywords based on the abstract (which must be properties on DBpedia and not be on the stopword list)
function get_relevant_keywords($resource){

	// Fetch arrays of data needed
	$stopwords = file("default_english_stopwords.txt", FILE_IGNORE_NEW_LINES);
	$dbpedia_ontology_properties = file("dbpedia_ontology_properties.txt", FILE_IGNORE_NEW_LINES);
	$dbpedia_properties = file("dbpedia_properties.txt", FILE_IGNORE_NEW_LINES);
	$abstract_keywords = get_abstract_keywords($resource);

	// Sorting: Keeps keywords that are ontology properties on DBpedia, discards keywords that are on stopword list
	$relevant_ontology_keywords1 = array_intersect($abstract_keywords, $dbpedia_ontology_properties);
	$relevant_ontology_keywords2 = array_diff($relevant_ontology_keywords1, $stopwords);
	$relevant_ontology_keywords2 = remove_two_letter_values($relevant_ontology_keywords2);

	// Sorting: Keeps keywords that are properties on DBpedia, discards keywords that are on stopword list
	$relevant_keywords1 = array_intersect($abstract_keywords, $dbpedia_properties);
	$relevant_keywords2 = array_diff($relevant_keywords1, $stopwords);
	$relevant_keywords2 = remove_two_letter_values($relevant_keywords2);
	
	
	// Add prefixes to keywords and merge arrays
	array_walk($relevant_ontology_keywords2, function(&$item) { $item = 'dbpedia-owl:'.$item; });
	array_walk($relevant_keywords2, function(&$item) { $item = 'dbpprop:'.$item; });
	$prefixed_relevant_keywords = array_merge($relevant_ontology_keywords2, $relevant_keywords2);
	
	/*
	// Debugging: Print list of all relevant keywords
	echo "<h2>Relevant ontology-keywords (words that are found in resource abstract and is a dbpedia ontology property. There may be stopwords.)</h2>";
	foreach($relevant_ontology_keywords1 as $ontology_keywords1) {
	echo $ontology_keywords1 . "<br />";
	}

	 echo "<h2>Relevant ontology-keywords (words that are found in resource abstract and is a dbpedia ontology property, but not a stopword)</h2>";
	foreach($relevant_ontology_keywords2 as $ontology_keywords2) {
	echo $ontology_keywords2 . "<br />";
	}

	echo "<h2>Relevant keywords (words that are found in resource abstract and is a dbpedia property. There may be stopwords.)</h2>";
	foreach($relevant_keywords1 as $keyword1) {
		echo $keyword1 . "<br />";
	}

	echo "<h2>Relevant keywords (words that are found in resource abstract and is a dbpedia property, but not a stopword)</h2>";
	foreach($relevant_keywords2 as $keyword2) {
		echo $keyword2 . "<br />";
	}
	
	echo "<h2>Prefixed relevant keywords (as they would be used in POI query)</h2>";
	foreach($prefixed_relevant_keywords as $prefixed_keyword) {
		echo $prefixed_keyword . "<br />";
	}
	*/
	

	return $prefixed_relevant_keywords;
}


// Formats a comma-separated string of matching properties and prints it in an unordered list
function print_matching_properties($matching_properties){
	$matching_properties = explode(",", $matching_properties);
	
	echo "<ul>";
	foreach($matching_properties as $matching_property){
		echo "<li>" . $matching_property . "</li>";
	}
	echo "</ul>";
}

// Takes a list of properties, adds space before capital letters and looks for that property in an abstract text
// Eg: "averageDepth" -> "average Depth" will identify the property in abstract "the average depth of the lake is..."
// Returns the discovered property with prefix like dbpprop:averageDepth or dbpedia-owl:averageDepth
function calculate_multiword_properties($properties_array, $resource_abstract, $prefix){
	// print_r($properties_array);
	// die();

	global $minimum_property_length;
	$prefixed_multiword_array = array();
	
	foreach($properties_array as $property) {
	
		// Look for capital letter and properties longer than, or equal to, minimum property character length
		if((preg_match('#\p{Lu}#', $property)) && (strlen($property) >= $minimum_property_length)) {
				
			// Put a single space before capital letters
			$property = preg_replace('#(?<!\ )\p{Lu}#', ' $0', $property);
				
			// Search for matching phrases in the resource abstract and add matches to array with prefix and with the space removed again 
			if(stripos($resource_abstract, $property) !== false) {
				$prefixed_multiword_array[] = $prefix . str_replace(" ", "", $property);
				// echo "\n<br>  Found abstract text multiword property: " . $property;
			}
		}
	}
	
	return $prefixed_multiword_array;
}


// Gets all the multiword properties
function get_multiword_keywords($resource){
	
	// Import needed data
	$dbpedia_ontology_properties = file("dbpedia_ontology_properties.txt", FILE_IGNORE_NEW_LINES);
	$dbpedia_properties = file("dbpedia_properties.txt", FILE_IGNORE_NEW_LINES);
	$resource_abstract = get_abstract($resource);
	
	// Call functions to calculate ontology/dbpedia properties
	$prefixed_multiword_dbpedia_ontology_properties = calculate_multiword_properties($dbpedia_ontology_properties, $resource_abstract, "dbpedia-owl:");
	$prefixed_multiword_dbpedia_properties = calculate_multiword_properties($dbpedia_properties, $resource_abstract, "dbpprop:");
	
    // Debugging
	// echo "<pre>";
	// print_r($prefixed_multiword_dbpedia_properties);

	
	// echo "<pre>"; 
	// print_r($prefixed_multiword_dbpedia_ontology_properties);
	// die();

	// Combine to one array
	$prefixed_multiword_keywords = array_merge($prefixed_multiword_dbpedia_ontology_properties, $prefixed_multiword_dbpedia_properties);

	/*
	// Debugging
	echo "<h2>Prefixed multiword ontology properties</h2>";
	foreach($prefixed_multiword_dbpedia_ontology_properties as $prefixed_multiword_ontology_property) {
		echo $prefixed_multiword_ontology_property . "<br />";
	}
	
	echo "<h2>Prefixed multiword dbpedia properties</h2>";
	foreach($prefixed_multiword_dbpedia_properties as $prefixed_multiword_dbpedia_property) {
		echo $prefixed_multiword_dbpedia_property . "<br />";
	}
	*/
	
	return $prefixed_multiword_keywords;
}


// Calculates an percentile rating adjusted to $min = 0% and $max = 100% 
function calculate_adjusted_percentage($value, $min, $max){

	return ($value-$min)/(($max-$min)/100);
}


// Converts a km value to degree value (calulated at equator)
function km_to_degrees($km_distance){
	$degrees = ($km_distance / (ACOS(COS(deg2rad(90-0))*COS(deg2rad(90-0))+SIN(deg2rad(90-0))*SIN(deg2rad(90-0))*COS(deg2rad(1-0))) *6371));
	return $degrees;
}

// Formats negative numbers so they can be used in DBpedia queries
function format_negative_degrees($degree){
	if($degree < 0) {
		
		// Invert number from negative to positive
		$degree = abs($degree);
		
		return "($degree*-1)";
		
	} else {
		return $degree;
	}
}


// Fetches URI, title, abstract and coordinates of POIs related to given resource URI
function get_related_pois($resource, $resource_lat, $resource_long, $search_distance_km){
	
	// Import config variables
	global $use_abstract_properties, $use_resource_properties, $use_multiword_abstract_properties, $use_intersecting_properties, $max_search_results, $max_property_characters;
	
	// Time measuring
	$time = microtime();
	$time = explode(' ', $time);
	$time = $time[1] + $time[0];
	$start = $time;
	

	// Gets the relevant keywords (based on abstract) with correct prefix, puts them in string separated by spaces
	if($use_abstract_properties) {
		global $prefixed_relevant_keywords;
		$prefixed_relevant_keywords = implode(" ", get_relevant_keywords($resource));
		//echo "\r\n\r\nuse_abstract_properties: " . $prefixed_relevant_keywords;
	}
	
	// Gets the properties (based on subject/resource) with correct prefix, puts them in string separated by spaces
	if($use_resource_properties) {
		global $prefixed_resource_properties;
		$prefixed_resource_properties = implode(" ", get_resource_properties($resource));
		//echo "\r\n\r\nuse_resource_properties: " . $prefixed_resource_properties;
	}
	
	// Gets prefixed multiword properties based on capitalLetterKeywords which have been matched with resource abstract. Puts them in string separated by spaces
	if($use_multiword_abstract_properties){
		global $prefixed_multiword_keywords;
		$prefixed_multiword_keywords = implode(" ", get_multiword_keywords($resource));
		//echo "\r\n\r\nuse_multiword_abstract_keywords: " . $prefixed_multiword_keywords;
	}
	
	// Gets the relevant keywords (based on abstract) and properties (based on subject/resource) with correct prefix, extracts the intersecting keywords and puts them in a string separated by spaces
	$prefixed_intersecting_properties = array();
	global $prefixed_intersecting_properties;

	if($use_intersecting_properties){
		
		//$multiword_keywords = array();
		//$resource_properties = array();
		
		
		$resource_properties = get_resource_properties($resource);
		$relevant_keywords = get_relevant_keywords($resource);
		$multiword_keywords = get_multiword_keywords($resource);
		
		$intersecting_keywords = array_intersect((array)$resource_properties, (array)$relevant_keywords);
		$intersecting_multiword_keywords = array_intersect((array)$resource_properties, (array)$multiword_keywords);
		$intersecting_properties = array_merge($intersecting_keywords, $intersecting_multiword_keywords);
		
		// Debugging data
		$unused_properties = array_diff($resource_properties, $relevant_keywords);

		$prefixed_intersecting_properties = implode(" ", $intersecting_properties);

        
		//Debugging
		/*
		echo "\r\n\r\nmultiword_keywords:\r\n";
		echo var_export($multiword_keywords);
		
		echo "\r\n\r\nintersecting_keywords:\r\n";
		echo var_export($intersecting_keywords);
		
		echo "\r\n\r\nintersecting_multiword_keywords: \r\n";
		echo var_export($intersecting_multiword_keywords);
		
		echo "\r\n\r\nintersecting_properties: \r\n";
		echo var_export($intersecting_properties);

		echo "\r\n\r\nunused_properties: \r\n";
		echo var_export($unused_properties);
		
		die();
		*/
	}
	
	// Calculates min/max latitude/longitude
	$one_degree = (ACOS(COS(deg2rad(90-0))*COS(deg2rad(90-0))+SIN(deg2rad(90-0))*SIN(deg2rad(90-0))*COS(deg2rad(1-0))) *6371);
	
	$search_distance_degrees = $search_distance_km/$one_degree;
	
	$latitude_correction_factor = $one_degree/(ACOS(COS(deg2rad(90-$resource_lat))*COS(deg2rad(90-$resource_lat))+SIN(deg2rad(90-$resource_lat))*SIN(deg2rad(90-$resource_lat))*COS(deg2rad(1-0))) *6371);
	
	
	// These vars need to be global because they are used in both the query and for the bounding box 
	global $min_lat, $max_lat, $min_long, $max_long;
	
	$min_lat = $resource_lat - $search_distance_degrees;
	$max_lat = $resource_lat + $search_distance_degrees;
	
	$min_long = $resource_long - ($search_distance_degrees * $latitude_correction_factor);
	$max_long = $resource_long + ($search_distance_degrees * $latitude_correction_factor);
	
	$all_properties = $prefixed_resource_properties . " " . $prefixed_relevant_keywords . " " . $prefixed_multiword_keywords . " " . $prefixed_intersecting_properties;
	
	// Remove duplicate properties
	$all_properties = implode(' ', array_unique(explode(' ', $all_properties)));
		
	// Reduce length of property string
	if(strlen($all_properties) > $max_property_characters) {
		
		// Reduce length by character count 
		$all_properties = substr($all_properties, 0, $max_property_characters);
		
		// Trim end of string to remove property stub (Regex: one or more whitespace and zero or more non-whitespace letters at end of string) 
		$all_properties = preg_replace ("/\s+\S*$/", "", $all_properties);
	}

	

	$query = "
    PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
    PREFIX geo: <http://www.w3.org/2003/01/geo/wgs84_pos#>
    PREFIX dcterms: <http://purl.org/dc/terms/>
    PREFIX : <http://dbpedia.org/resource/>
    PREFIX dbpprop: <http://dbpedia.org/property/>
    PREFIX dbpedia: <http://dbpedia.org/>
    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
    PREFIX dbpedia-owl: <http://dbpedia.org/ontology/>
    PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
    select (SAMPLE(?subject) AS ?subject) (count ( distinct ?property) as ?numProperties)  ?label  ?abstract (SAMPLE(?lat) AS ?lat) (SAMPLE(?long) AS ?long)
    (group_concat(distinct ?property;separator=', ') as ?properties)
    where {
    values ?property { " . $all_properties . " }
    ?subject ?property ?object .
    ?subject rdfs:label ?label .
    ?subject geo:lat ?lat .
    ?subject geo:long ?long .
    ?subject dbpedia-owl:abstract ?abstract .
    FILTER (?long > " . format_negative_degrees($min_long) . " && ?long <  " . format_negative_degrees($max_long) . " && ?lat >  " . format_negative_degrees($min_lat) . "  && ?lat <  " . format_negative_degrees($max_lat) . "  ) .
    FILTER(langMatches(lang(?label),\"EN\")) .
    FILTER(langMatches(lang(?abstract),\"EN\")) .
    } 
    group by ?subject ?label ?abstract
    order by desc(?numProperties) 
    limit " . $max_search_results;

	// echo $query;
	// die();
	
	$result = sparql_get("https://dbpedia.org/sparql", $query);
	
	if(!$result) {
		echo sparql_errno() . ": " . sparql_error();
		exit;
	}
	
	return $result;
}

function get_nearby_pois($resource_lat, $resource_long, $search_distance_km) {

	// Calculates min/max latitude/longitude
	$one_degree = (ACOS(COS(deg2rad(90-0))*COS(deg2rad(90-0))+SIN(deg2rad(90-0))*SIN(deg2rad(90-0))*COS(deg2rad(1-0))) *6371);

	$search_distance_degrees = $search_distance_km/$one_degree;
	

	//die("one degree: " . $search_distance_degrees);
	
	$latitude_correction_factor = $one_degree/(ACOS(COS(deg2rad(90-$resource_lat))*COS(deg2rad(90-$resource_lat))+SIN(deg2rad(90-$resource_lat))*SIN(deg2rad(90-$resource_lat))*COS(deg2rad(1-0))) *6371);
	
	// These vars need to be global because they are used in both the query and for the bounding box 
	global $min_lat2, $max_lat2, $min_long2, $max_long2;
	
	$min_lat2 = $resource_lat - $search_distance_degrees;
	$max_lat2 = $resource_lat + $search_distance_degrees;
	
	$min_long2 = $resource_long - ($search_distance_degrees * $latitude_correction_factor);
	$max_long2 = $resource_long + ($search_distance_degrees * $latitude_correction_factor);
	
	//echo $query;
	//die();
	
	$query = "
    PREFIX skos: <http://www.w3.org/2004/02/skos/core#>
    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
    PREFIX geo: <http://www.w3.org/2003/01/geo/wgs84_pos#>
    PREFIX dcterms: <http://purl.org/dc/terms/>
    PREFIX : <http://dbpedia.org/resource/>
    PREFIX dbpprop: <http://dbpedia.org/property/>
    PREFIX dbpedia: <http://dbpedia.org/>
    PREFIX rdf: <http://www.w3.org/1999/02/22-rdf-syntax-ns#>
    PREFIX dbpedia-owl: <http://dbpedia.org/ontology/>
    PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
    select (SAMPLE(?subject) AS ?subject) ?label ?lat ?long ?abstract
    where {
    ?subject rdfs:label ?label .
    ?subject geo:lat ?lat .
    ?subject geo:long ?long .
    ?subject dbpedia-owl:abstract ?abstract .
    FILTER (?long > " . format_negative_degrees($min_long2) . " && ?long <  " . format_negative_degrees($max_long2) . " && ?lat >  " . format_negative_degrees($min_lat2) . "  && ?lat <  " . format_negative_degrees($max_lat2) . "  ) .
    FILTER(langMatches(lang(?label),\"EN\")) .
    FILTER(langMatches(lang(?abstract),\"EN\")) .
    }
    group by ?label ?lat ?long ?abstract
    order by ?label
    limit 80";
	
	//die("Query:" . $query);
	
	$result = sparql_get("https://dbpedia.org/sparql", $query);
	
	if(!$result) {
		echo sparql_errno() . ": " . sparql_error();
		exit;
	}
	
	return $result;
}
 
?>
