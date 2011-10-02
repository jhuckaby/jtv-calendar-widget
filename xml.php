<?php

// Justin.TV Calendar Widget
// Copyright (c) 2011 Joseph Huckaby
// Released under the MIT License
// http://www.opensource.org/licenses/mit-license.php

// TO-DO:
//	-- support compsing to files
//  -- handle mixed content (text and child elements in the same node)

// store last xml parser error
$last_xml_error = null;

class XML {
	public $parser;              // PHP XML parser object
	public $tree;                // XML hash tree
	public $name;                // Root document node name
	public $error;               // Last error encountered and line #
	public $collapseAttribs = 0; // Set to 1 to collapse attributes into parent node
	public $indentString = "\t"; // Pretty-print indent string for composing
	public $output;              // Holds XML output while composing
	public $dtdNode;             // DTD node to be composed under PI node
	public $compress = 0;        // Whether to compress output (no whitespace)

	function XML($xmlstring="") {
		// Class constructor
		// If string is passed in, parse immediately
		$this->dtdNode = "";

		if ($xmlstring) return( $this->parse($xmlstring) );
		return true;
	}

	function parse($xmlstring="") {
		// Parse text into XML hash tree
		// Returns root node
		$this->parser = xml_parser_create();
		$this->tree = array();
		$this->name = "";
		$this->clearError();
		
		// Setup PHP XML parser
		xml_set_object($this->parser, $this);
		xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);
		xml_set_element_handler($this->parser, "startElement", "endElement");
		xml_set_character_data_handler($this->parser, "characterData");
		
		// If argument was file instead of string, load it now
		if (!preg_match("/<.+?>/", $xmlstring)) $xmlstring = file_get_contents($xmlstring);
		if (!$xmlstring || !strlen($xmlstring)) {
			$this->throwError("File not found");
			xml_parser_free($this->parser);
			return false;
		} // file not found
		
		// Issue command to parse
		$result = xml_parse($this->parser, $xmlstring);
		if (!$result) {
			// error occured -- get string, line number and return false
			$code = xml_get_error_code( $this->parser );
			$this->throwError( xml_error_string( $code ) . " on line " . xml_get_current_line_number( $this->parser ) );
			xml_parser_free($this->parser);
			return false;
		}
		
		// Success -- free parser memory and return hash tree
		xml_parser_free($this->parser);
		return $this->tree;
	}
	
	function clearError() {
		// clear error state
		global $last_xml_error;
		$last_xml_error = null;
		$this->error = null;
	}
	
	function throwError($msg) {
		// set error state
		global $last_xml_error;
		$last_xml_error = $msg;
		$this->error = $msg;
	}

	function startElement($parser, $name, $attribs) {
		// Callback function for PHP XML parser
		// called when new elements are opened

		$node = array();
		$node["_Name"] = $name;

		if (count($attribs)) {
			// Element has attributes -- gather them first
			if ($this->collapseAttribs) {
				foreach ($attribs as $key => $value) {
					$node[$key] = $value;
				}
			}
			else {
				$node["_Attribs"] = array();
				foreach ($attribs as $key => $value) {
					$node["_Attribs"][$key] = $value;
				}
			}
		}
		
		// Add new node onto stack
		array_push( $this->tree, $node );
	}

	function endElement($parser, $name) {
		// Callback function for PHP XML parser
		// called when elements are closed
		
		// Pop most recent node off stack
		$node = array_pop( $this->tree );

		// Trim whitespace from text data and delete
		// if it is empty
		if (isset($node["_Data"])) {
			$node["_Data"] = trim($node["_Data"]);
			if (!preg_match("/\S/", $node["_Data"])) unset($node["_Data"]);
		}
		
		// Extract name of node
		$name = $node["_Name"];
		unset($node["_Name"]);

		// $payload is a reference to the node object, or just
		// the text data if there are no attribs or child elements
		$payload = &$node;
		
		// Count number of nodes left in stack
		$lastnode = count($this->tree);
		if (!$lastnode) {
			// This the root node being closed, so we are done
			// Set $tree to this node and return immediately.
			$this->name = $name;
			$this->tree = $node;
			return;
		}
		else if ((isset($node["_Data"]) && count($node) == 1) || !count($node)) {
			// simple text node or empty node -- collapse so that
			// payload points to text data only
			if (!isset($node["_Data"])) $node["_Data"] = "";
			$payload = &$node["_Data"];
		}
		
		// Now we add this node as a child of our parent node
		if (!isset($this->tree[$lastnode-1][$name]) || !$this->tree[$lastnode-1][$name]) {
			// first time for this node name in parent node
			// point directly to us (don't use array yet)
			$this->tree[$lastnode-1][$name] = $payload;
		}
		else if (is_array($this->tree[$lastnode-1][$name]) && isset($this->tree[$lastnode-1][$name][0])) {
			// node name already exists in parent node, and is real array
			// so just push our node on the end
			// print_r( $this->tree[$lastnode-1][$name] );

			array_push( $this->tree[$lastnode-1][$name], $payload );
		}
		else {
			// node name exists in parent node, but need to convert to true array
			// and push our node on the end
			$temp = $this->tree[$lastnode-1][$name];
			$this->tree[$lastnode-1][$name] = array();
			array_push( $this->tree[$lastnode-1][$name], $temp );
			array_push( $this->tree[$lastnode-1][$name], $payload );
		}
	}

	function characterData($parser, $data) {
		// Callback function for PHP XML parser
		// called when text data is encountered

		$lastnode = count($this->tree);
		if (!isset($this->tree[$lastnode-1]["_Data"]) || !$this->tree[$lastnode-1]["_Data"]) 
			$this->tree[$lastnode-1]["_Data"] = "";
		$this->tree[$lastnode-1]["_Data"] .= $data;
	}

	function getLastError() {
		// Return last error encountered as string
		// Also contains line number in source XML file
		return $this->error;
	}

	function getTree() {
		// Return hash tree
		return $this->tree;
	}

	function composeNode($name, $node, $indent) {
		// Compose single XML node and attributes
		// Recurse for child nodes

		// Given indent amount ($indent), compose indent text
		$indentText = "";
		if (!$this->compress) for ($k = 0; $k < $indent; $k++) $indentText .= $this->indentString;
		
		// See if node is an object, or a simple text node
		if (is_array($node)) {
			// Node is object, so now check if node is a true array,
			// or a single node
			if (!isset($node[0])) {
				// node is singular, so compose node immediately
				$this->output .= $indentText . "<$name";

				$numKeys = count($node);
				$hasAttribs = 0;

				if (isset($node["_Attribs"])) {
					// Node has attributes, so compose them now
					$hasAttribs = 1;
					foreach ($node["_Attribs"] as $key => $value) {
						$this->output .= " $key=\"" . htmlspecialchars($value) . "\"";
					}
				} // has attribs

				if ($numKeys > $hasAttribs) {
					// Node has child elements, so close node and recurse
					$this->output .= ">";

					if (isset($node["_Data"])) {
						// simple text child node
						$this->output .= htmlspecialchars($node["_Data"], ENT_NOQUOTES) . "</$name>";
						if (!$this->compress) $this->output .= "\n";
					}
					else {
						// no text data, only child elements
						if (!$this->compress) $this->output .= "\n";

						foreach ($node as $key => $value) {
							if ($key != "_Attribs") {
								$this->composeNode( $key, $value, $indent + 1 );
							} // not _Attribs key
						} // foreach key

						$this->output .= $indentText . "</$name>";
						if (!$this->compress) $this->output .= "\n";
					} // has non-text child elements
				} // has child elements
				else {
					// Node has no child elements, so self-close it
					$this->output .= "/>";
					if (!$this->compress) $this->output .= "\n";
				}
			} // standard node
			else {
				// node is a true array (multiple nodes with same name)
				// so step through array recursing for each node at same indent level
				$count = count($node);
				for ($k = 0; $k < $count; $k++) {
					$this->composeNode( $name, $node[$k], $indent );
				}
			} // array node
		}
		else {
			// node is a simple text node (non-object string)
			$this->output .= $indentText . "<$name>" . htmlspecialchars($node, ENT_NOQUOTES) . "</$name>";
			if (!$this->compress) $this->output .= "\n";
		}

		return $this->output;
	}

	function setDTDNode($text) {
		// set pre-composed DTD node for composing
		$this->dtdNode = $text;
	}

	function compose($name="", $tree="") {
		// Compose XML from hash tree
		if (!$name) $name = $this->name;
		if (!$tree) $tree = $this->tree;

		$this->output = '<?xml version="1.0"?>';
		if (!$this->compress) $this->output .= "\n";
		if ($this->dtdNode) {
			$this->output .= $this->dtdNode;
			if (!$this->compress) $this->output .= "\n";
		}
		$this->composeNode( $name, $tree, 0 );

		return $this->output;
	}

	function composeJS($tree="", $indent=1) {
		// Compose JavaScript from hash tree
		if (!$tree) $tree = $this->tree;
		if ($indent == 1) $this->output = "";

		// Given indent amount ($indent), compose indent text
		$indentText = "";
		if (!$this->compress) for ($k = 0; $k < $indent; $k++) $indentText .= $this->indentString;

		$parentIndentText = "";
		if (($indent > 1) && !$this->compress) for ($k = 1; $k < $indent; $k++) 
			$parentIndentText .= $this->indentString;

		if (!isset($tree[0])) {
			// standard node
			$this->output .= "{";
			if (!$this->compress) $this->output .= "\n";

			foreach ($tree as $key => $value) {
				$key = '"' . $key . '"'; // wrap in quotes

				if (is_array($value)) {
					$this->output .= $indentText . $key . ": ";
					$this->composeJS( $value, $indent + 1 );
				}
				else {
					$value = escapeJS($value);
					$this->output .= $indentText . $key . ": " . $value . ",";
					if (!$this->compress) $this->output .= "\n";
				}
			} // foreach $key
			
			if (!$this->compress) {
				$this->output = preg_replace("/\,\n$/", "\n", $this->output);
				$this->output .= $parentIndentText . "},\n";
			}
			else {
				$this->output = preg_replace("/\,$/", "", $this->output);
				$this->output .= $parentIndentText . "},";
			}
		}
		else {
			// array node
			$this->output .= "[";
			if (!$this->compress) $this->output .= "\n";

			$count = count($tree);
			for ($k = 0; $k < $count; $k++) {
				if (is_array($tree[$k])) {
					$this->output .= $indentText;
					$this->composeJS( $tree[$k], $indent + 1 );
				}
				else {
					$value = escapeJS($tree[$k]);
					$this->output .= $indentText . $value . ",";
					if (!$this->compress) $this->output .= "\n";
				}
			} // foreach element
			
			if (!$this->compress) {
				$this->output = preg_replace("/\,\n$/", "\n", $this->output);
				$this->output .= $parentIndentText . "],\n";
			}
			else {
				$this->output = preg_replace("/\,$/", "", $this->output);
				$this->output .= $parentIndentText . "],";
			}
		}

		if ($indent == 1) {
			if (!$this->compress) $this->output = preg_replace("/\,\n$/", ";\n", $this->output);
			else $this->output = preg_replace("/\,$/", ";", $this->output);
		}

		return $this->output;
	}
	
	function lookup($xpath, $tree = null) {
		// run simple XPath query, supporting things like:
		//		/Simple/Path/Here
		//		/ServiceList/Service[2]/@Type
		//		/Parameter[@Name='UsePU2']/@Value
		if (!$tree) $tree = $this->tree;
		$orig_xpath = $xpath;
		$node_match = "/^\/?([^\/]+)/";
		$this->clearError();

		while (preg_match($node_match, $xpath, $matches)) {
			if (preg_match("/^([\w\-\:]+)\[([^\]]+)\]$/", $matches[1], $arr_matches)) {
				// array index lookup, possibly complex attribute match
				if (isset($tree[$arr_matches[1]])) {
					$tree = $tree[$arr_matches[1]];
					$elements = alwaysArray($tree);

					if (preg_match("/^\d+$/", $arr_matches[2])) {
						// simple array index lookup, i.e. /Parameter[2]
						if (isset($elements[$arr_matches[2]])) {
							$tree = $elements[$arr_matches[2]];
							$xpath = preg_replace($node_match, "", $xpath);
						}
						else {
							$this->throwError( "Could not locate XPath: $orig_xpath" );
							return null;
						}
					}
					else if (preg_match("/^\@([\w\-\:]+)\=\'([^\']*)\'$/", $arr_matches[2], $sub_matches)) {
						// complex attrib search query, i.e. /Parameter[@Name='UsePU2']
						$count = count($elements);
						$found = 0;

						for ($k = 0; $k < $count; $k++) {
							$elem = $elements[$k];
							if (isset($elem[$sub_matches[1]]) && ($elem[$sub_matches[1]] == $sub_matches[2])) {
								$found = 1;
								$tree = $elem;
								$k = $count;
							}
							else if (isset($elem['_Attribs']) && 
									isset($elem['_Attribs'][$sub_matches[1]]) && 
									($elem['_Attribs'][$sub_matches[1]] == $sub_matches[2])) {
								$found = 1;
								$tree = $elem;
								$k = $count;
							}
						} // foreach element

						if ($found) $xpath = preg_replace($node_match, "", $xpath);
						else {
							$this->throwError( "Could not locate XPath: $orig_xpath" );
							return null;
						}
					} // attrib search
				} // found basic element name
				else {
					$this->throwError( "Could not locate XPath: $orig_xpath" );
					return null;
				}
			} // array index lookup
			else if (preg_match("/^\@([\w\-\:]+)$/", $matches[1], $sub_matches)) {
				// attrib lookup
				if (isset($tree['_Attribs'])) $tree = $tree['_Attribs'];
				if (isset($tree[$sub_matches[1]])) {
					$tree = $tree[$sub_matches[1]];
					$xpath = preg_replace($node_match, "", $xpath);
				}
				else {
					$this->throwError( "Could not locate XPath: $orig_xpath" );
					return null;
				}
			} // attrib lookup
			else if (isset($tree[$matches[1]])) {
				$tree = $tree[$matches[1]];
				$xpath = preg_replace($node_match, "", $xpath);
			} // simple element lookup
			else {
				$this->throwError( "Could not locate XPath: $orig_xpath" );
				return null;
			} // bad xpath
		} // foreach xpath node

		return $tree;
	}
} // class XML

/**
 * Static utility functions
 **/

function escapeJS($value, $force_strings = false) {
	// escape value for JavaScript eval
	if ($force_strings || (!preg_match("/^\-?\d{1,15}(\.\d{1,15})?$/", $value) || preg_match("/^0[^\.]/", $value))) { // if not a number
		$value = preg_replace("/\r\n/", "\n", $value); // dos2unix
		$value = preg_replace("/\r/", "\n", $value); // mac2unix
		$value = preg_replace("/\\\\/", "\\\\\\\\", $value); // escape backslashes
		$value = preg_replace("/\"/", "\\\"", $value); // escape quotes
		$value = preg_replace("/\n/", "\\n", $value); // escape EOLs
		$value = preg_replace("/<\/(scr)(ipt)>/i", "</scr\" + \"ipt>", $value); // escape closing script tags
		$value = '"' . $value . '"'; // and wrap in quotes
	}
	return $value;
}

function alwaysArray($obj) {
	// detect if $obj is an associative array or a true array
	// if associative, put inside a new true array as element 0
	if (!is_array($obj) || (is_array($obj) && !isset($obj[0]))) return array($obj);
	else return $obj;
}

function XMLtoJavaScript($tree, $compress=0) {
	// convert XML object tree to JavaScript
	$parser = new XML();
	$parser->compress = $compress;
	return $parser->composeJS($tree);
}

function getLastXMLError() {
	// return last xml parser error encountered
	global $last_xml_error;
	return $last_xml_error;
}

function XML_unserialize($text) {
	// static wrapper around XML parser
	// includes document node in object
	$parser = new XML();
	$tree = $parser->parse( $text );
	if (!$tree) return null;
	else {
		// wrap document in root node
		$xml = array();
		$xml[ $parser->name ] = $tree;
		return $xml;
	}
}

function parse_xml($text) {
	// static wrapper around XML parser
	// includes document node in object
	$parser = new XML();
	$parser->collapseAttribs = 1;
	$tree = $parser->parse( $text );
	if (!$tree) return $parser->getLastError();
	else {
		return $tree;
	}
}

function compose_xml($tree, $doc_name) {
	// static wrapper around XML composer
	$parser = new XML();
	return $parser->compose( $doc_name, $tree );
}

function XML_serialize($tree, $compress=0) {
	// static wrapper around XML composer
	// document node should be inside object
	$parser = new XML();
	$parser->compress = $compress;
	
	$arr_keys = array_keys($tree);
	$doc_name = $arr_keys[0];
	return $parser->compose( $doc_name, $tree[$doc_name] );
}

function XML_lookup($xpath, $tree) {
	// static wrapper around XML XPath lookup routine
	$parser = new XML();
	return $parser->lookup($xpath, $tree);
}

function XML_collapse_attribs(&$tree) {
	// collapse _Attribs contents into parent nodes
	if (isa_hash($tree)) {
		if (isset($tree['_Attribs'])) {
			foreach ($tree['_Attribs'] as $key => $value) {
				$tree[$key] = $tree['_Attribs'][$key];
			}
			unset($tree['_Attribs']);
		}
		
		// recurse for child nodes
		foreach ($tree as $key => $value) {
			if (is_array($tree[$key])) XML_collapse_attribs($tree[$key]);
		}
	}
	else if (isa_array($tree)) {
		foreach ($tree as &$elem) {
			if (is_array($elem)) XML_collapse_attribs($elem);
		}
	}
}

function isa_hash($arg) {
	// determine if arg is a hash (NOT an array)
	return( is_array($arg) && !isset($arg[0]) );
}

function isa_array($arg) {
	// determine if arg is a true array (NOT a hash)
	return ( is_array($arg) && isset($arg[0]) );
}

function first_key($hash) {
	// return first key from hash (unordered)
	foreach ($hash as $key => $value) return $key;
}

function merge_objects($a, $b) {
	// merge keys from a and b into c and return c
	// b has precedence over a
	if (!$a) $a = array();
	if (!$b) $b = array();
	$c = array();
	
	foreach ($a as $key => $value) $c[$key] = $value;
	foreach ($b as $key => $value) $c[$key] = $value;

	return $c;
}

function deep_copy_object_lc_keys($a) {
	// make recursive copy of object, lower-casing all keys
	if (isa_hash($a)) {
		$b = array();
		foreach($a as $key => $value) {
			$b[strtolower($key)] = deep_copy_object_lc_keys( $a[$key] );
		}
		return $b;
	}
	else if (isa_array($a)) {
		$b = array();
		foreach($a as $elem) {
			array_push( $b, deep_copy_object_lc_keys($elem) );
		}
		return $b;
	}
	else return $a;
}

function XML_index_by( $xml = null, $element = "", $key = "", $recursive = false, $compress = false ) {
	// index arrays by named keys
	if (!$xml || !$key || !$element) return 0;
	
	if (isa_hash($xml) && isa_hash($xml[$element]) && isset($xml[$element][$key]))
		$xml[$element] = alwaysArray( $xml[$element] );
	
	if (isa_hash($xml) && isa_array($xml[$element])) {
		$reindex = 0;

		for ($idx = count($xml[$element]) - 1; $idx >= 0; $idx--) {
			$elem = $xml[$element][$idx];
			if (isset($elem[$key])) {
				$reindex = 1;
				$new_name = $elem[$key];
				unset( $elem[$key] );

				if ($compress && (count($elem) == 1) && (!is_array($elem[first_key($elem)]))) {
					$elem = $elem[ first_key($elem) ];
				} // compress

				if (isset($xml[$new_name])) {
					// element already exists at new location
					// convert or append to array
					$xml[$new_name] = alwaysArray( $xml[$new_name] );
					array_unshift( $xml[$new_name], $elem );
				}
				else {
					// first use of new_name
					$xml[$new_name] = $elem;
				}
			} // elem has key
		} // idx loop

		if ($reindex) {
			// delete entire array after reindexing is complete
			unset( $xml[$element] );
		}
	} // xml is hash and contains element array

	if ($recursive && $xml) {
		if (isa_hash($xml)) {
			foreach ($xml as $idx => $value) {
				XML_index_by( $value, $element, $key, $recursive, $compress );
			} // foreach key
		}
		else if (isa_array($xml)) {
			foreach ($xml as $elem) {
				XML_index_by( $elem, $element, $key, $recursive, $compress );
			} // foreach key/element
		}
	} // recurse
}

function expand_parameter_nodes($tree) {
	// expand <Parameter> and <ParameterGroup> nodes into
	// a standard hash tree
	XML_index_by( $tree, 'ParameterGroup', 'Name', true, false );
	XML_index_by( $tree, 'Parameter', 'Name', true, true );
}

function make_parameter_nodes($tree, $max_levels = -1) {
	// Given hash tree, convert to <Parameter> style for XML composing.
	// Do this safely, non-destructively, and support arrays
	$out = array();

	if (!$max_levels || !isa_hash($tree)) { return array(); } // out of levels
	
	foreach ($tree as $key => $value) {		
		if (isa_hash($value)) {
			$node_group = merge_objects(array( "_Attribs" => array('Name' => $key) ),
				make_parameter_nodes($value, $max_levels - 1) );
			
			if (isset($out['ParameterGroup'])) {
				if (isa_array($out['ParameterGroup'])) {
					array_push( $out['ParameterGroup'], $node_group );
				}
				else {
					$out['ParameterGroup'] = array( $out['ParameterGroup'], $node_group );
				}
			}
			else {
				$out['ParameterGroup'] = $node_group;
			}
		}
		else if (isa_array($value)) {
			for ($idx = 0; $idx < count($value); $idx++) {
				$elem = $value[$idx];
				if (isa_hash($elem)) {
					$node_group = merge_objects(array( "_Attribs" => array('Name' => $key) ),
						make_parameter_nodes($elem, $max_levels - 1) );
					
					if (isset($out['ParameterGroup'])) {
						if (isa_array($out['ParameterGroup'])) {
							array_push( $out['ParameterGroup'], $node_group );
						}
						else {
							$out['ParameterGroup'] = array( $out['ParameterGroup'], $node_group );
						}
					}
					else {
						$out['ParameterGroup'] = $node_group;
					}
				} // hash in array
				else {
					$node = array( "_Attribs" => array('Name' => $key) );
					
					if (preg_match("/[\n\"]/", $elem)) {
						$node['_Data'] = $elem;
					}
					else {
						$node['_Attribs']['Value'] = $elem;
					}

					if (isset($out['Parameter'])) {
						if (isa_array($out['Parameter'])) {
							array_push( $out['Parameter'], $node );
						}
						else {
							$out['Parameter'] = array( $out['Parameter'], $node );
						}
					}
					else {
						$out['Parameter'] = $node;
					}
				} // scalar in array
			} // foreach elem
		}
		else {
			$node = array( "_Attribs" => array('Name' => $key) );
			
			if (preg_match("/[\n\"]/", $value)) {
				$node['_Data'] = $value;
			}
			else {
				$node['_Attribs']['Value'] = $value;
			}

			if (isset($out['Parameter'])) {
				if (isa_array($out['Parameter'])) {
					array_push( $out['Parameter'], $node );
				}
				else {
					$out['Parameter'] = array( $out['Parameter'], $node );
				}
			}
			else {
				$out['Parameter'] = $node;
			}
		}
	}

	return $out;
}

function find_object($array, $criteria, $mode = "AND") {
	// search array of objects for keys/values matching criteria
	assert(isset($array));
	assert(isset($criteria));
	$min_matches = ($mode == 'AND') ? count($criteria) : 1;
	
	foreach (alwaysArray($array) as $element) {
		$matches = 0;
		foreach ($criteria as $key => $value) {
			if (isset($element[$key]) && ($element[$key] == $value)) $matches++;
			else if (isset($element['_Attribs']) && isset($element['_Attribs'][$key]) && ($element['_Attribs'][$key] == $value)) $matches++;
		}
		if ($matches >= $min_matches) return $element;
	}
	
	return null;
}

?>
