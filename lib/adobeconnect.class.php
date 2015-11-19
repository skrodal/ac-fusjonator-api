<?php
	/**
	 *
	 *
	 * @author Simon Skrodal
	 * @since  November 2015
	 */


	// Some calls take a long while so increase timeout limit from def. 30
	set_time_limit(300);    // 5 mins
	// Have experienced fatal error - allowed memory size of 128M exhausted - thus increase
	ini_set('memory_limit', '350M');

	class AdobeConnect {
		private $DEBUG = false;

		protected $config, $apiurl, $sessioncookie;

		function __construct($config) {
			$this->config        = $config;
			$this->sessioncookie = NULL;
			$this->apiurl        = $this->config['connect-api-base'];
		}

		/** Routes implementation **/

		public function getConnectVersion() {
			$apiCommonInfo = $this->callConnectApi(array('action' => 'common-info'), false);

			return (string)$apiCommonInfo->common->version;
		}

		public function getAccountStatus($userList) {

			return $userList;

			$response = [];
			foreach($userList as $usernameOldAndNew) {
				$response[$usernameOldAndNew[0]][$usernameOldAndNew[0]] = $this->_checkUserExists($usernameOldAndNew[0]);
				$response[$usernameOldAndNew[0]][$usernameOldAndNew[1]] = $this->_checkUserExists($usernameOldAndNew[1]);
			}

			return ($response);
		}

		public function verifyAccountList($postData){
			// Get/set POST values
			$userList       = isset($postData['user_list']) ? $postData['user_list'] : false;
			// Not used (yet)
			$token          = isset($postData['token']) ? $postData['token'] : false;
			// Check that all required data is here
			if(!$userList) { Response::error(400, 'Missing one or more required data fields from POST. Cannot continue without required data...'); }
			// Use sessioncookie passed from client
			if($token !== false) { $this->sessioncookie = $token; }
			// To be sent back to client...
			$responseObj = array();
			// Loop all user pairs in the CSV
			foreach($userList as $userOldAndNew) {
				// Must be two columns only for each entry
				if(sizeof($userOldAndNew) !== 2) {
					Response::error(400, 'Malformed data structure. Cannot continue.');
				}

				$oldLoginInfo = $this->_checkUserExists($userOldAndNew[0]);
				$newLoginInfo = $this->_checkUserExists($userOldAndNew[1]);

				// Add response for old and new username
				$responseObj[$userOldAndNew[0]][$userOldAndNew[0]] = $oldLoginInfo;
				$responseObj[$userOldAndNew[0]][$userOldAndNew[1]] = $newLoginInfo;
			}
			// Done :-)
			return ($responseObj);
		}


		/**
		 * Check if a user exists. Returns false if not, otherwise user metadata.
		 *
		 * @param $userName
		 *
		 * @return bool|SimpleXMLElement[]
		 */
		private function _checkUserExists($userName) {
			$this->_logger('(BEFORE)', __LINE__, __FUNCTION__);
			// Lookup account info for requested user
			$apiUserInfoResponse = $this->callConnectApi(
				array(
					'action'       => 'principal-list',
					'filter-login' => $userName
				)
			);
			$this->_logger('(AFTER)', __LINE__, __FUNCTION__);
			// Exit on error
			if(strcasecmp((string)$apiUserInfoResponse->status['code'], "ok") !== 0) {
				Response::error(400, 'User lookup failed: ' . $userName . ': ' . (string)$apiUserInfoResponse->status['subcode']);
			}
			// Ok search, but user does not exist (judged by missing metadata)
			if(!isset($apiUserInfoResponse->{'principal-list'}->principal)) {
				return false;
			}

			// Done :-)
			return array(
				'id'          => (string)$apiUserInfoResponse->{'principal-list'}->principal['principal-id'],
				'username'    => (string)$apiUserInfoResponse->{'principal-list'}->principal->login
			);
		}


		// ---------------------------- UTILS ----------------------------


		/**
		 * Utility function for AC API calls.
		 */
		protected function callConnectApi($params = array(), $requireSession = true) {

			if($requireSession) {
				$params['session'] = $this->getSessionAuthCookie();
			}

			$url = $this->apiurl . http_build_query($params);
			$xml = false;
			try {
				$xml = simplexml_load_file($url);
			} catch(Exception $e) {
				$this->_logger('Failed to get XML', __LINE__, __FUNCTION__);
				$this->_logger(json_encode($e), __LINE__, __FUNCTION__);
				Response::error(400, 'API request failed. Could be that the service is unavailable (503)');
			}

			if(!$xml) {
				Response::error(400, 'API request failed. Could be that the service is unavailable (503)');
			}
			$this->_logger('Got XML response', __LINE__, __FUNCTION__);
			$this->_logger(json_encode($xml), __LINE__, __FUNCTION__);

			return $xml;
		}

		/**
		 * Authenticate API user on AC service and grab returned cookie. If auth already in place, return cookie.
		 *
		 * @throws Exception
		 * @return array
		 */
		protected function getSessionAuthCookie() {
			if($this->sessioncookie !== NULL) {
				$this->_logger('Have cookie, reusing', __LINE__, __FUNCTION__);

				return $this->sessioncookie;
			}

			$url  = $this->apiurl . 'action=login&login=' . $this->config['connect-api-userid'] . '&password=' . $this->config['connect-api-passwd'];
			$auth = get_headers($url, 1);

			if(!isset($auth['Set-Cookie'])) {
				$this->_logger('********** getSessionAuthCookie failed!', __LINE__, __FUNCTION__);
				Response::error(401, 'Error when authenticating to the Adobe Connect API using client API credentials. Set-Cookie not present in response.');
			}

			// Extract session cookie
			$acSessionCookie = substr($auth['Set-Cookie'], strpos($auth['Set-Cookie'], '=') + 1);
			$acSessionCookie = substr($acSessionCookie, 0, strpos($acSessionCookie, ';'));

			$this->sessioncookie = $acSessionCookie;
			$this->_logger('Returning new cookie', __LINE__, __FUNCTION__);

			return $this->sessioncookie;
		}

		private function _responseToArray($response) {
			$newArr = Array();
			foreach($response as $child) {
				$newArr[] = $child;
			}

			return $newArr;
		}


		private function _logger($text, $line, $function) {
			if($this->DEBUG) {
				error_log($function . '(' . $line . '): ' . $text);
			}
		}

		// ---------------------------- ./UTILS ----------------------------


	}



