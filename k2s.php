<?php
	// NutTom 2021-03-24
	// Very dirty but fonctionnal
	
	define('DEBUG_TO_FILE', true);
	define("DEBUG_FILE", "/tmp/k2scc.log");
	
	class SynoFileHostingK2s
	{
		private $Url;
		private $Username;
		private $Password;
		private $HostInfo;
		protected $_auth_token;

		private $_version = 'beta';
		
		public $baseUrl = 'https://keep2share.cc/api/v2/';
		private $_tokenPath = '/tmp/k2scc.apikey';
		private $api;

		public function __construct($Url, $Username, $Password, $HostInfo)
		{
			$pattern = "/file\/(.*?)(?:\z|\/)/";

			$this->debug('Url : ', $Url);
			preg_match($pattern, $Url, $matche);
			$this->file_id = $matche[1];
			$this->debug('file_id : ', $this->file_id);
			$this->Username = $Username;
			$this->Password = $Password;
			$this->HostInfo = $HostInfo;
			$this->_auth_token = $this->getAuthToken();
			
			$this->_ch = curl_init();
			curl_setopt($this->_ch, CURLOPT_POST, true);
			curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($this->_ch, CURLOPT_FOLLOWLOCATION, 2);
		}
		
		// This function check if the account is valid
		public function Verify($ClearCookie)
		{	
			curl_setopt($this->_ch, CURLOPT_URL, $this->baseUrl . 'login');

			$params = [
				'username' => $this->Username,
				'password' => $this->Password,
			];
		
			curl_setopt($this->_ch, CURLOPT_POSTFIELDS, json_encode($params));
			$response = curl_exec($this->_ch);
			
			$data = json_decode($response, true);

			if (!$data || !isset($data['status']))
			{
				$this->debug('Authentication failed : ', $data);
				return LOGIN_FAIL;
			}

			if ($data['status'] == 'success') {
				$this->setAuthToken($data['auth_token']);
				$this->_auth_token = $data['auth_token'];
				$this->debug('Authentication success', '');
				return USER_IS_PREMIUM;
			}
			else
			{
				$this->debug('Authentication failed', $data['message']);
				return LOGIN_FAIL;
			}
		}
		
		//This function returns download url.
		public function GetDownloadInfo()
		{	
			$downloadInfo = array();
			
			if($this->Verify(false) != USER_IS_PREMIUM)
			{
				$downloadInfo[DOWNLOAD_ERROR] = ERR_REQUIRED_PREMIUM;
				return $downloadInfo;
			}
			
			curl_setopt($this->_ch, CURLOPT_URL, $this->baseUrl . 'getUrl');

			$params = [
				'auth_token' => $this->_auth_token,
				'file_id' => $this->file_id,
			];
		
			curl_setopt($this->_ch, CURLOPT_POSTFIELDS, json_encode($params));
			$response = curl_exec($this->_ch);
			$this->debug('$response : ', $response);

			$data = json_decode($response, true);

			if (!$data || !isset($data['status']))
			{
				$this->debug('getUrl failed : ', $data);
				$downloadInfo[DOWNLOAD_ERROR] = ERR_UNKNOWN;
				return $downloadInfo;
			}

			if ($data['status'] == 'success')
			{
				$downloadInfo[DOWNLOAD_ISPARALLELDOWNLOAD] = TRUE;
				$DownloadInfo[DOWNLOAD_URL] = $data['url'];
				return $DownloadInfo;
			}
			else if ($data['status'] == 'error')
			{
				if ($data['errorCode'] == '20')
				{
					$downloadInfo[DOWNLOAD_ERROR] = ERR_FILE_NO_EXIST;
					return $downloadInfo;
				}
				else if ($data['errorCode'] == '21')
				{
					$downloadInfo[DOWNLOAD_ERROR] = ERR_TRY_IT_LATER;
					return $downloadInfo;
				}
				else if ($data['errorCode'] == '42')
				{
					$downloadInfo[DOWNLOAD_ERROR] = ERR_TRY_IT_LATER;
					return $downloadInfo;
				}
				else if ($data['errorCode'] == '43')
				{
					$downloadInfo[DOWNLOAD_ERROR] = ERR_REQUIRED_PREMIUM;
					return $downloadInfo;
				}
				else
				{
					$this->debug('Unknown getUrl error : ', $data);
					$downloadInfo[DOWNLOAD_ERROR] = ERR_UNKNOWN;
					return $downloadInfo;
				}
				
			}
			else
			{
				$this->debug('Unknown getUrl error : ', $data);
				$downloadInfo[DOWNLOAD_ERROR] = ERR_UNKNOWN;
				return $downloadInfo;
			}
		}
		
		private function setAuthToken($key)
		{
			file_put_contents($this->_tokenPath, $key);
		}
		
		private function getAuthToken()
		{
			return is_file($this->_tokenPath) ? file_get_contents($this->_tokenPath) : false;
		}
		
		private function debug($Header, $Value)
		{
			$msg = $Header . print_r($Value, TRUE) . "\n";   
			if (DEBUG_TO_FILE)
			{
				$msg = date("n-d H:i:s") . " " . $msg;
				file_put_contents(DEBUG_FILE, $msg, FILE_APPEND);   
			}
		}
	}
?>