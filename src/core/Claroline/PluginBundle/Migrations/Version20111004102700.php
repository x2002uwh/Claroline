<?php

namespace Claroline\PluginBundle\Migrations;

use Claroline\InstallBundle\Library\Migration\BundleMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20111004102700 extends BundleMigration
{
    public function up(Schema $schema)
    {
        $this->createPluginTable($schema);
        $this->createToolTable($schema);
        $this->createApplicationTable($schema);
        $this->createApplicationLauncherTable($schema);
        $this->createLauncherRoleJoinTable($schema);
    }
    
    private function createPluginTable(Schema $schema)
    {
        $table = $schema->createTable('claro_plugin');
        
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('type', 'string', array('length' => 255));
        $table->addColumn('bundle_fqcn', 'string', array('length' => 255));
        $table->addColumn('vendor_name', 'string', array('length' => 50));
        $table->addColumn('short_name', 'string', array('length' => 50));
        $table->addColumn('name_translation_key', 'string', array('length' => 255));
        $table->addColumn('description', 'string', array('length' => 255));
        $table->addColumn('discr', 'string', array('length' => 255));       
        $table->setPrimaryKey(array('id'));
        
        $this->storeTable($table);
    }
    
    private function createApplicationTable(Schema $schema)
    {
        $table = $schema->createTable('claro_application');
        
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('index_route', 'string', array('length' => 255));
        $table->addColumn('is_eligible_for_platform_index', 'boolean');
        $table->addColumn('is_platform_index', 'boolean');
        $table->setPrimaryKey(array('id'));
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_plugin'), 
            array('id'), 
            array('id'),
            array("onDelete" => "CASCADE")
        );
        
        $this->storeTable($table);
    }
    
    private function createApplicationLauncherTable(Schema $schema)
    {
        $table = $schema->createTable('claro_application_launcher');
        
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->addColumn('application_id', 'integer', array('notnull' => true));
        $table->addColumn('route_id', 'string', array('length' => 255));
        $table->addColumn('translation_key', 'string', array('length' => 255));
        $table->setPrimaryKey(array('id'));
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_application'),
            array('application_id'), 
            array('id'),
            array("onDelete" => "CASCADE")
        );
    }
    
    private function createLauncherRoleJoinTable(Schema $schema)
    {
        $table = $schema->createTable('claro_launcher_role');
        
        // TODO : use real foreign keys instead of just relying on doctrine
        // (that will suppose to get a Table object for the role table via
        // the schema manager, but we must ensure the SecurityBundle migration
        // is executed *before* this migration -> dependency management...)
        $table->addColumn('launcher_id', 'integer', array('notnull' => true));
        $table->addColumn('role_id', 'integer', array('notnull' => true));
    }
    
    private function createToolTable(Schema $schema)
    {
        $table = $schema->createTable('claro_tool');
        
        $table->addColumn('id', 'integer', array('autoincrement' => true));
        $table->setPrimaryKey(array('id'));
        $table->addForeignKeyConstraint(
            $this->getStoredTable('claro_plugin'), 
            array('id'), 
            array('id'),
            array("onDelete" => "CASCADE")
        );
    }
    
    public function down(Schema $schema)
    {
        $schema->dropTable('claro_launcher_role');
        $schema->dropTable('claro_application_launcher');
        $schema->dropTable('claro_application');
        $schema->dropTable('claro_tool');
        $schema->dropTable('claro_plugin');
    }
}