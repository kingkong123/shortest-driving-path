<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Add_routes_table extends CI_Migration {

    public function __construct() {
        parent::__construct();
        $this->load->dbforge();
    }

    public function up()
    {
        $this->dbforge->add_field(array(
            'id' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
                'auto_increment' => TRUE
            ),
            'token' => array(
                'type' => 'VARCHAR',
                'constraint' => '32',
            ),
            'status' => array(
                'type' => 'ENUM("success", "in progress", "failure")',
                'default' => 'in progress'
            ),
            'latlngs' => array(
                'type' => 'TEXT',
            ),
            'distance' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
            ),
            'time' => array(
                'type' => 'INT',
                'constraint' => 11,
                'unsigned' => TRUE,
            ),
            'error' => array(
                'type' => 'TEXT',
            ),
        ));

        $this->dbforge->add_key('id', TRUE);
        $this->dbforge->add_key('token');
        $this->dbforge->create_table('routes');
    }

    public function down()
    {
        $this->dbforge->drop_table('routes');
    }
}