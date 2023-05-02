<?php 

// ------------------
// DBpedia server URI
// ------------------
$dbpedia_server_uri = "https://dbpedia.org/sparql";
$db = sparql_connect($dbpedia_server_uri);
//$db = sparql_connect( "http://lod.openlinksw.com/sparql" );
//$db = sparql_connect( "http://live.dbpedia.org/sparql" );
//$db = sparql_connect( "http://dbpedia-live.openlinksw.com/sparql" );
//$db = sparql_connect("http://lexitags.dyndns.org:8890/sparql");


// --------------------------------------------------
// Parameters for properties/keywords used in queries
// --------------------------------------------------

// Use the resource's own properties?
$use_resource_properties = true;


// Use properties found in resource abstract?
$use_abstract_properties = true;


// Use multiword properties found in resource abstract?
$use_multiword_abstract_properties = true;


// Use only intersection of abstract keywords (both single- and multiword) and properties found in resource?
// (Note: This should not be used in conjunction with any of the other properties/keywords above
$use_intersecting_properties = false;


// The minimum character length of properties used
$minimum_property_length = 3;


// The number of results shown on POI search
$max_search_results = 40;


// The max number of characters for properties
$max_property_characters = 5900;
// 6174 was starting point
// 5482 is current for prague


// ----------------------------------------------------------
// Parameters concerning information displayed in infowindows
// ----------------------------------------------------------

// Print number of matching properties and adjusted matching percentage in infowindow popup?
$print_matching_statistics = true;


// Print abstract text in infowindow popups?
$print_abstract = true;


// Print list of matching properties in infowindow popups?
$print_matching_properties_list = true;


// Show a link to DBpedia in the infowindows?
$print_dbpedia_link = true;


// Prevent caching of page
header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past

?>