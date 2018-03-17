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

			if($inputBody && $routes = @json_decode($inputBody)){
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
			}
		}else{
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
		}

		header('Content-Type: application/json');
		echo json_encode( $result );
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
							}
						}
					}
				}else{
					$result['error'] = $result['status'];
				}
			}

			$result['distance'] = $distance;
			$result['time'] = $duration;
		}

		return $result;
	}
}
