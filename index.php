<?php

	$DATAPORTEN_CONFIG_PATH    = '/var/www/etc/ac-fusjonator/dataporten_config.js';
	$ADOBE_CONNECT_CONFIG_PATH = '/var/www/etc/ac-fusjonator/adobe_config.js';
	$API_BASE_PATH             = '/api/ac-fusjonator'; // Remember to update .htacces as well. Same with a '/' at the end...


	//
	$BASE = dirname(__FILE__);

	// Result or error responses
	require_once($BASE . '/lib/response.class.php');
	// Checks CORS and pulls Dataporten info from headers
	require_once($BASE . '/lib/dataporten.class.php');
	$dataporten_config = json_decode(file_get_contents($DATAPORTEN_CONFIG_PATH), true);
	$feide             = new Dataporten($dataporten_config);
	//  http://altorouter.com
	require_once($BASE . '/lib/router.class.php');
	$router = new Router();
	// $router->addMatchTypes(array('userlist' => '[0-9A-Za-z\[\]@.,%]++'));
	$router->setBasePath($API_BASE_PATH);
	// Proxy API to Adobe Connect
	require_once($BASE . '/lib/adobeconnect.class.php');
	$adobe_config = json_decode(file_get_contents($ADOBE_CONNECT_CONFIG_PATH), true);
	$connect      = new AdobeConnect($adobe_config);

// ---------------------- DEFINE ROUTES ----------------------


	/**
	 * GET all REST routes
	 */
	$router->map('GET', '/', function () {
		global $router;
		Response::result($router->getRoutes());
	}, 'Routes listing');


	/**
	 * GET Adobe Connect version
	 */
	$router->map('GET', '/version/', function () {
		global $connect;
		Response::result($connect->getConnectVersion());
	}, 'Adobe Connect version');

	/**
	 * GET Template
	 *
	$router->map('GET', '/PATH/[i:iD]/status/', function ($iD) {
		global $connect;
		Response::result(array('status' => true, 'data' => $connect->SOME_FUNCTION($iD)));
	}, 'DESCRIPTION OF ROUTE');
	*/

	/**
	 * Run account checkups with Adobe Connect
	 */
	$router->map('POST', '/users/verify/', function () {
		global $connect;
		Response::result($connect->verifyAccountList($_POST));
	}, 'Verify array of usernames [[oldLogin, newLogin], [...,...], ...] ');

	/**
	 * Migrate user accounts (old login -> new login)
	 */
	$router->map('POST', '/users/migrate/', function () {
		global $connect;
		Response::result($connect->migrateUserAccounts($_POST));
	}, 'Migrate supplied user accounts from current_login to new_login.');




	// -------------------- UTILS -------------------- //

	// Restrict access to specified org
	function verifyOrgAccess() {
		global $feide;

		if(!$feide->isUserSuperAdmin()) {
			Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (USER is missing required access rights). ');
		}

		if(!$feide->hasAdminScope()){
			Response::error(401, $_SERVER["SERVER_PROTOCOL"] . ' 401 Unauthorized (CLIENT is missing required scope). ');
		}
	}

	/**
	 * http://stackoverflow.com/questions/4861053/php-sanitize-values-of-a-array/4861211#4861211
	 */
	function sanitizeInput() {
		$_GET  = filter_input_array(INPUT_GET, FILTER_SANITIZE_STRING);
		$_POST = filter_input_array(INPUT_POST, FILTER_SANITIZE_STRING);
	}

	// -------------------- ./UTILS -------------------- //


// ---------------------- MATCH AND EXECUTE REQUESTED ROUTE ----------------------
	$match = $router->match();

	if($match && is_callable($match['target'])) {
		verifyOrgAccess();
		sanitizeInput();
		call_user_func_array($match['target'], $match['params']);
	} else {
		Response::error(404, $_SERVER["SERVER_PROTOCOL"] . " The requested resource could not be found.");
	}
	// ---------------------- /.MATCH AND EXECUTE REQUESTED ROUTE ----------------------


