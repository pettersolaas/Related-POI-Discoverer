<?php
// Prevent caching of page
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past


// Time measuring
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$start = $time;


require_once("sparqllib2.php");
 

//$db = sparql_connect("http://lexitags.dyndns.org:8890/sparql");
$db = sparql_connect( "http://dbpedia.org/sparql" );
//$db = sparql_connect( "http://lod.openlinksw.com/sparql" );
//$db = sparql_connect( "http://live.dbpedia.org/sparql" );
//$db = sparql_connect( "http://dbpedia-live.openlinksw.com/sparql" );

if(!$db) {
	echo sparql_errno() . ": " . sparql_error(). "\n";
	exit;
}

sparql_ns("rdf", "http://www.w3.org/1999/02/22-rdf-syntax-ns#");
/*
sparql_ns("skos", "http://www.w3.org/2004/02/skos/core#");
sparql_ns("rdfs", "http://www.w3.org/2000/01/rdf-schema#");
sparql_ns("dcterms", "http://purl.org/dc/terms/");
*/

$total_properties_counter = 1;


$dbpedia_properties_counter = 1;
$dbpedia_ontology_properties_counter = 1;
$discarded_properties_counter = 1;

$dbpedia_ontology_properties_file = fopen("dbpedia_ontology_properties.txt", "w") or die("Unable to open DBpedia ontology properties file for writing properties");
$dbpedia_properties_file = fopen("dbpedia_properties.txt", "w") or die("Unable to open DBpedia properties file for writing properties");
$discarded_properties_file = fopen("discarded_properties.txt", "w") or die("Unable to open discarded properties file for writing properties");

function print_query($offset) {
		
	if ($offset > 0) {
		$query = "
		SELECT distinct ?property {{
			select ?property {
				?property a rdf:Property
			}
			ORDER BY ?property
		}}
		OFFSET " . $offset . "
		LIMIT 10000";
	} else {
		$query = "
			SELECT distinct ?property
			WHERE {
			?property rdf:type rdf:Property
			}
			ORDER BY ?property
			LIMIT 10000";
	}
		
	global $total_properties_counter, $dbpedia_properties_counter, $dbpedia_ontology_properties_counter, $discarded_properties_counter, $dbpedia_ontology_properties_file, $dbpedia_properties_file, $discarded_properties_file;
	
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
			//$property_line = $total_properties_counter . " " . $row[$field] . "\r\n";

			// Look for ontology properties
			if(stripos($row[$field], "http://dbpedia.org/ontology/") !== false) {
			
				$property_line = str_replace("http://dbpedia.org/ontology/", "", $row[$field]);
			
				fwrite($dbpedia_ontology_properties_file, $property_line . "\n");
				
				echo "<span style=\"background-color:#95e795\">" . $dbpedia_ontology_properties_counter . " dbpedia-owl:" . $property_line . "</span><br />";
				$dbpedia_ontology_properties_counter++;
			
			// Looks for DBpedia properties
			} elseif (stripos($row[$field], "http://dbpedia.org/property/") !== false) {
			
				$property_line = str_replace("http://dbpedia.org/property/", "", $row[$field]);

				fwrite($dbpedia_properties_file, $property_line . "\n");
				echo "<span style=\"background-color:#9599e7\">" . $dbpedia_properties_counter . " dbpprop:" . $property_line . "</span><br />";
				$dbpedia_properties_counter++;
			
			// Everything else
			} else {
			
				
				fwrite($discarded_properties_file, $row[$field] . "\n");
				echo "<span style=\"background-color:#e79595\">" . $discarded_properties_counter . " " . $row[$field] . "</span><br />";
				$discarded_properties_counter++;
			
			}

			$total_properties_counter++;
		}

	}
}


echo "<h2>Downloading properties from DBpedia...</h2>";


print_query(NULL);
print_query(10000);
print_query(20000);
print_query(30000);
print_query(40000);
print_query(50000);
print_query(60000);
print_query(70000);
print_query(80000);
print_query(90000);

fclose($keyword_file);


echo "<h2>Download complete!</h2>";

// Time measuring
$time = microtime();
$time = explode(' ', $time);
$time = $time[1] + $time[0];
$finish = $time;
$total_time = round(($finish - $start), 4);

echo "<span style=\"background-color:#95e795\">Ontology properties: " . $dbpedia_ontology_properties_counter . "</span><br />";
echo "<span style=\"background-color:#9599e7\">Properties: " . $dbpedia_properties_counter . "</span><br />";
echo "<span style=\"background-color:#e79595\">Discarded properties: " . $discarded_properties_counter . "</span><br />";
echo "<span>Total number of properties: " . $total_properties_counter . "</span><br />";
echo "<span>Execution time: " . $total_time . " seconds.</span><br />";

?>