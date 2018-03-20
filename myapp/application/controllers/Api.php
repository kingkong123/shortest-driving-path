<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Api extends CI_Controller {

	public function __construct() {
		parent::__construct();

		$this->load->model('routes_model');
	}

	public function index()
	{
		show_404();
	}

	public function route($token = false) {
		$result = [];

		if(!$token){
			$inputBody = file_get_contents('php://input');

			if($inputBody){

				$validate = $this->_validateInput($inputBody);

				if($validate['success']){
					$result = $this->_processRoute($validate['data']);
				}else{
					$result['error'] = $validate['error'];
				}
			}
		}else{
			$result = $this->_getRouteByToken($token);
		}

		header('Content-Type: application/json');
		echo json_encode( $result );
	}

	private function _validateInput($inputBody){
		$result = ['success' => false];

		$routes = json_decode($inputBody, true);

		if($routes === null && json_last_error() !== JSON_ERROR_NONE){
			$result['error'] = json_last_error();

			switch (json_last_error()) {
		        case JSON_ERROR_DEPTH:
		            $result['error'] = 'JSON_ERROR_DEPTH';
		        	break;

		        case JSON_ERROR_STATE_MISMATCH:
		            $result['error'] = 'JSON_ERROR_STATE_MISMATCH';
		        	break;

		        case JSON_ERROR_CTRL_CHAR:
		            $result['error'] = 'JSON_ERROR_CTRL_CHAR';
		        	break;

		        case JSON_ERROR_SYNTAX:
		            $result['error'] = 'JSON_ERROR_SYNTAX';
		        	break;

		        case JSON_ERROR_UTF8:
		            $result['error'] = 'JSON_ERROR_UTF8';
		        	break;

		        default:
		            $result['error'] = 'UNKNOWN_JSON_ERROR';
		        	break;
		    }
		}

		if($routes && !isset($result['error'])){
			if(sizeof($routes) > 1){
				$newRoutes = array_map(function($item){
					if(sizeof($item) == 2){
						$latlng = array_values($item);

						if(is_numeric($latlng[0]) && (((float) $latlng[0]) == $latlng[0])
							&& is_numeric($latlng[1]) && (((float) $latlng[1]) == $latlng[1])){
							return 1;
						}
					}

					return 0;
				}, $routes);

				if(array_sum($newRoutes) == sizeof($newRoutes)){
					$result['success'] = true;
					$result['data'] = $routes;
				}else{
					$result['error'] = 'INVALID_INPUT';
				}
			}else{
				$result['error'] = 'MISSING_DESTINATION';
			}
		}

		return $result;
	}

	private function _processRoute($routes){
		$result = [];

		$token = md5(json_encode($routes));

		$tokenExists = $this->routes_model->tokenExists($token);

		if(!$tokenExists['exists'] || trim($tokenExists['error']) !== ''){
			$routeResult = $this->_getRoutes($routes);

			if($routeResult['error'] != ''){
				$result['error'] = $routeResult['error'];

				$routeResult['status'] = 'failure';
			}else{
				$routeResult['status'] = 'success';
			}

			$routeResult['token'] = $token;
			$routeResult['latlngs'] = json_encode($routes);

			$this->routes_model->insertRoute($routeResult);
		}

		if(!isset($result['error'])){
			$result['token'] = $token;
		}

		return $result;
	}

	private function _getRoutes($routes){
		$result = [
			'distance' => 0,
			'time' => 0,
			'error' => ''
		];

		$origins = $destinations = '';

		foreach($routes as $key => $route){
			if(is_array($route) && sizeof($route) == 2){
				if($key === 0){
					$origins = ((float) $route[0]) . ',' . ((float) $route[1]);
				}else{
					if($destinations != ''){
						$destinations .= '|';
					}

					$destinations .= ((float) $route[0]) . ',' . ((float) $route[1]);
				}
			}
		}

		if($origins && $destinations){
			$distance = $duration = 0;

			$url = 'https://maps.googleapis.com/maps/api/distancematrix/json?';

			$url .= http_build_query([
				'units' => 'metric',
				'origins' => $origins,
				'destinations' => $destinations,
				'key' => getenv('GOOGLE_MAPS_API')
			]);

			$resource = @file_get_contents($url);

			if($resource && $json = @json_decode($resource, true)){
				if($json['status'] == 'OK'){
					foreach($json['rows'] as $row){
						foreach($row['elements'] as $element){
							if($element['status'] == 'OK'){
								$distance += (int) $element['distance']['value'];
								$duration += (int) $element['duration']['value'];
							}else{
								if(strpos($result['error'], $element['status']) === false){
									$result['error'] .= $element['status'] . ' ';
								}
							}
						}
					}
				}else{
					if(isset($json['error_message'])){
						$result['error'] = $json['error_message'];
					}else{
						$result['error'] = $json['status'];
					}
				}
			}

			$result['error'] = trim($result['error']);

			$result['distance'] = $distance;
			$result['time'] = $duration;
		}

		return $result;
	}

	private function _getRouteByToken($token = false){
		$result = [];

		$tokenExists = $this->routes_model->tokenExists($token);

		if($tokenExists['exists'] && $tokenExists['error'] == ''){
			$dbResult = $this->routes_model->getByToken($token);

			if($dbResult['status'] == 'success'){
				$result['status'] = $dbResult['status'];
				$result['path'] = json_decode($dbResult['latlngs']);
				$result['total_distance'] = $dbResult['distance'];
				$result['total_time'] = $dbResult['time'];
			}
		}else if($tokenExists['error']){
			$result['status'] = 'failure';
			$result['error'] = $tokenExists['error'];
		}else{
			$result['status'] = 'failure';
			$result['error'] = 'TOKEN_NOT_EXISTS';
		}

		return $result;
	}
}
