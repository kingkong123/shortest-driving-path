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
			$result['error'] = $this->_getJsonError(json_last_error());
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

			if(isset($tokenExists['id'])){
				$routeResult['id'] = $tokenExists['id'];
			}

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

		$origins = '';
		$destinations = $waypoints = [];

		if(sizeof($routes) == 2){
			$origins = ((float) $routes[0][0]) . ',' . ((float) $routes[0][1]);
			$destinations[] = ((float) $routes[1][0]) . ',' . ((float) $routes[1][1]);
		}else{
			foreach($routes as $key => $route){
				if($key === 0){
					$origins = ((float) $route[0]) . ',' . ((float) $route[1]);
				}else{
					$destinations[] = ((float) $route[0]) . ',' . ((float) $route[1]);

					$waypoints[] = $this->_buildWaypoint($key, $routes);
				}
			}
		}

		if(empty($waypoints)){
			$distance = $duration = 0;

			$url = 'https://maps.googleapis.com/maps/api/distancematrix/json?';

			$url .= http_build_query([
				'units' => 'metric',
				'origins' => $origins,
				'destinations' => $destinations[0],
				'key' => getenv('GOOGLE_MAPS_API')
			]);

			$resource = file_get_contents($url);

			if($resource === false){
				$result['error'] = 'CONNECTION_ERROR';
			}else{
				$json = json_decode($resource, true);

				if($json === null && json_last_error() !== JSON_ERROR_NONE){
					$result['error'] = $this->_getJsonError(json_last_error());
				}else{
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
			}

			$result['error'] = trim($result['error']);

			$result['distance'] = $distance;
			$result['time'] = $duration;
		}else{
			$distance = $duration = false;

			$routeResults = [];

			$url = 'https://maps.googleapis.com/maps/api/directions/json?';

			foreach($destinations as $key => $dest){
				$query =  http_build_query([
					'units' => 'metric',
					'origin' => $origins,
					'destination' => $dest,
					'waypoints' => 'optimize:true|' . $waypoints[$key],
					'key' => getenv('GOOGLE_MAPS_API')
				]);

				$resource = file_get_contents($url . $query);

				if($resource === false){
					$routeResults['error'][$key] = 'CONNECTION_ERROR';
				}else{
					$json = json_decode($resource, true);

					if($json === null && json_last_error() !== JSON_ERROR_NONE){
						$routeResults['error'][$key] = $this->_getJsonError(json_last_error());
					}else{
						if($json['status'] == 'OK'){
							$legs = $json['routes'][0]['legs'];

							$routeResults[$key]['distance'] = $this->_calcLegsParam($legs, 'distance');

							$routeResults[$key]['duration'] = $this->_calcLegsParam($legs, 'duration');
						}else{
							if(isset($json['error_message'])){
								$routeResults[$key]['error'] = $json['error_message'];
							}else{
								$routeResults[$key]['error'] = $json['status'];
							}
						}
					}
				}
			}

			foreach($routeResults as $key => $routeResult){
				if(isset($routeResult['distance'])){
					if($distance === false || $distance > $routeResult['distance']){
						$distance = $routeResult['distance'];
						$duration = $routeResult['duration'];
					}
				}
			}

			if($distance && $duration){
				$result['distance'] = $distance;
				$result['time'] = $duration;
			}else{
				$errors = array_unique(array_map(function($item){
					return (int) $item['error'];
				}, $routeResults));

				$result['errors'] = implode(' ', $errors);
			}
		}

		return $result;
	}

	private function _buildWaypoint($index, $routes){
		$waypoints = '';

		foreach($routes as $key => $route){
			if($key == 0 || $key == $index){
				continue;
			}

			if($waypoints != ''){
				$waypoints .= '|';
			}

			$waypoints .= ((float) $route[0]) . ',' . ((float) $route[1]);
		}

		return $waypoints;
	}

	private function _calcLegsParam($legs, $key){
		if($key == 'duration'){
			return array_sum(array_map(function($item){
				return (int) $item['duration']['value'];
			}, $legs));
		}else if($key == 'distance'){
			return array_sum(array_map(function($item){
				return (int) $item['distance']['value'];
			}, $legs));
		}
		
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

	private function _getJsonError($last_error){
		switch ($last_error) {
	        case JSON_ERROR_DEPTH:
	            return 'JSON_ERROR_DEPTH';
	        	break;

	        case JSON_ERROR_STATE_MISMATCH:
	            return 'JSON_ERROR_STATE_MISMATCH';
	        	break;

	        case JSON_ERROR_CTRL_CHAR:
	            return 'JSON_ERROR_CTRL_CHAR';
	        	break;

	        case JSON_ERROR_SYNTAX:
	            return 'JSON_ERROR_SYNTAX';
	        	break;

	        case JSON_ERROR_UTF8:
	            return 'JSON_ERROR_UTF8';
	        	break;

	        default:
	            return 'UNKNOWN_JSON_ERROR';
	        	break;
	    }
	}
}
