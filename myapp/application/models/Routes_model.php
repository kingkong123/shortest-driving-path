<?php

class Routes_model extends CI_Model {
	public function __construct(){
		if(!$this->db->table_exists('routes')){
			$this->load->library('migration');

			if ($this->migration->current() === FALSE){
				show_error($this->migration->error_string());
	        }
		}
	}

	public function insertRoute($data = []){
		if(isset($data['id'])){
			return $this->db->where('id', $data['id'])
				->replace('routes', $data);
		}

		return $this->db->where('token', $data['token'])
			->replace('routes', $data);
	}

	public function tokenExists($token = false){
		if($token){
			$result = $this->getByToken($token);

			if($result){
				return [
					'exists' => true,
					'id' => $result['id'],
					'error' => $result['error']
				];
			}

			return [
				'exists' => false,
				'error' => 'TOKEN_NOT_EXISTS'
			];
		}

		return [
			'exists' => false,
			'error' => 'EMPTY_TOKEN'
		];
	}

	public function getByToken($token = false){
		if($token){
			return $this->db->get_where('routes', [
				'token' => $token
			])->row_array();
		}

		return ;
	}
}