<?php
/********************************************************************************************************************************
*
*
/*******************************************************************************************************************************/

// must be run within DokuWiki
if(!defined('DOKU_INC')) die();
 
if(!defined('DOKU_PLUGIN')) define('DOKU_PLUGIN',DOKU_INC.'lib/plugins/');
require_once DOKU_PLUGIN.'syntax.php';
//Using PEAR Templates
require_once "HTML/Template/IT.php";

date_default_timezone_set('America/Los_Angeles');

require('errors.php');
 
/********************************************************************************************************************************
* All DokuWiki plugins to extend the parser/rendering mechanism
* need to inherit from this class
********************************************************************************************************************************/
class syntax_plugin_digilenthackster extends DokuWiki_Syntax_Plugin 
{
	//Return Plugin Info
	function getInfo() 
	{
        return array('author' => 'Andrew Holzer',
                     'email'  => 'aholzer@digilentinc.com',
                     'date'   => '2017-05-30',
                     'name'   => 'Digilent Product Projects',
                     'desc'   => 'Display all projects that use a Digilent Projuct',
                     'url'    => 'www.reference.digilentinc.com/try-waveforms');
	}
	
	//Store user variables to parse in one pass
	protected $message = "";
	protected $product = "";
	protected $projects = array(); // stores the names of projects, for now
	protected $error = NULL; // given an (object) array(), which always has a type entry and then extras. I (Andrew) have no idea if this is okay to do

	protected $baseURL = "https://api.hackster.io/v2";
	
	function getType() { return 'protected'; }
	function getSort() { return 32; }

	function connectTo($mode) {
		$this->Lexer->addEntryPattern('{{Digilent Hackster.*?(?=.*?}})',$mode,'plugin_digilenthackster'); 
	
		//Add Internal Pattern Match For Product Page Elements	
		$this->Lexer->addPattern('\|.*?(?=.*?)\n','plugin_digilenthackster'); 
	}

	function postConnect() {
		$this->Lexer->addExitPattern('}}','plugin_digilenthackster');
	}

	/*
		Accepts a JSON representation of the response returned after GETting 
		from /products and the product name intended for display AS SHOWN ON
		HACKSTER as a string.

		Finds the id of a product by matching $product_name to $json->records[i]->name.

		returns the id of the found product or false if one wasn't found (meaning further fetches perhaps?)
	*/
	function findProductId($json, $product_name) {
		// if there is only 1 record, it's probably the one that is wanted. Else, find the right one from the responses
		if (count($json->records) == 1) {
			return $json->records[0]->id;
		}
		else {
			// iterate through the entries and match the name, so name should be exact to what Hackster knows it as
			foreach($json->records as $record) {
				$record_name = strtolower(str_replace(array(" ", "\""), array("_", ""), trim($record->name)));

				if ($record_name == $product_name) {
					return $record->id;
				}
			}
		}

		return false;
	}
	
	function handle($match, $state, $pos, &$handler) 
	{	
		include('config.php');

		switch ($state) 
		{		
			case DOKU_LEXER_ENTER :
				break;
			case DOKU_LEXER_MATCHED :					
				//Find The Token And Value (Before '=' remove white space, convert to lower case).
				$tokenDiv = strpos($match, '=');											//Find Token Value Divider ('=')
				$prettyToken = trim(substr($match, 1, ($tokenDiv - 1)));					//Everything Before '=', Remove White Space
				$token = strtolower($prettyToken);											//Convert To Lower Case
				$value = substr($match, ($tokenDiv + 1));									//Everything after '='
				switch($token)
				{
					case 'message':
						$this->message = $value;
						break;
					case 'product':
						// trim, replace spaces with underscores, remove any quotation marks and make lowercase
						$product_name = strtolower(str_replace(array(" ", "\""), array("_", ""), trim($value)));

						$request_headers = array();
						$request_headers[] = 'Authorization: Basic ' . $key;

						$page_num = 1;
						$ch = curl_init($this->baseURL . '/products?q=' . $product_name . "&page=" . $page_num);
						curl_setopt_array($ch, array(
							CURLOPT_HTTPHEADER => $request_headers,
							CURLOPT_RETURNTRANSFER => 1 
						));

						do {
							$result = curl_exec($ch);

							if ($result === false) {
								$this->error = new CurlExecError(curl_error($ch));
								break;
							}

							$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
							if ($http_code !== 200) {
								// there was some sort of error on our part or the server's. Include http code in error
								$this->error = new HTTPResponseError($http_code);
								break;
							}

							$result = json_decode($result);
							$product_id = $this->findProductId($result, $product_name);
							
							if ($product_id === false && $page_num >= $result->metadata->total_pages) {
								// reached the end of what Hackster has, so prep an error object for that
								$this->error = new ProductIDMissingError($value);
								break;
							}
							else { // no id in this request, but more can be made, so prep curl for the next go-around (or we found our product, in which case this is not necessary)
								curl_setopt($ch, CURLOPT_URL, $this->baseURL . '/products?q=' . $product_name . '&page=' . ++$pagenum);
							}								
						} while ($product_id === false);

						if ($this->error != NULL) { // if the do...while created an error, then break out of the case here. 
							break;
						}

						// now have the product id so fetch the list of projects
						curl_setopt($ch, CURLOPT_URL, $this->baseURL . '/products/' . $product_id . '/projects');
						$result = curl_exec($ch);

						if ($result === false) {
							// projects curl failed
							$this->error = new CurlExecError(curl_error($ch));
							break;
						}

						$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
						if ($http_code !== 200) {
							// projects request came back with an error
							$this->error = new HTTPResponseError($http_code);
							break;
						}

						$result = json_decode($result);

						if (count($result->records) > 0) { // we only fetch the first page, but could get more if wanted
							foreach($result->records as $project) {
								$this->projects[] = array('name' => $project->name,
									'url' => $project->url);
							}
						}
						else {
							$this->error = new NoProjectsFoundError($value);
							break;
						}

						curl_close($ch);
						
						break;					
					default:						
						break;
				}
				return array($state, $value);
				break;
			case DOKU_LEXER_UNMATCHED :
				break;
			case DOKU_LEXER_EXIT :					
								
				//----------Process User Parameters And Generate Output ----------

				if ($this->error != NULL) { // properly build an ouput object based on what the error is
					$output = $this->error->message; // $this->buildHTMLfromError($this->error);
				} else {
					// iterate through $projects list and assemble the entries as list items
					$output = "Projects<ul>";
					foreach($this->projects as $project) {
						$project['url'] = str_replace('www.hackster.io', 'projects.digilentinc.com', $project['url']);
						$output .= '<li><a href="' . $project['url'] . '">' . $project['name'] . '</a></li>';
					}
					$output .= "</ul>";
				}

				// $output = "The product id is: " . $this->product;
				
				return array($state, $output);

				break;
			case DOKU_LEXER_SPECIAL :
				break;
		}
		
		return array($state, $match);
	}

	function render($mode, &$renderer, $data) 
	{
    // $data is what the function handle return'ed.
        if($mode == 'xhtml')
		{
			switch ($data[0]) 
			{
			  case DOKU_LEXER_ENTER : 
				break;
			  case DOKU_LEXER_MATCHED :				
				break;
			  case DOKU_LEXER_UNMATCHED :
				break;
			  case DOKU_LEXER_EXIT :
			  	// should create the list of projects here.
			  
				// Extract cached render data and add to renderer
				$output = $data[1];	
				$renderer->doc .= $output;				
				break;
				
			  case DOKU_LEXER_SPECIAL :
				break;
			}			
            return true;
        }
        return false;
    }
}
