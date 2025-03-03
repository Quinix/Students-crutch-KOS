<?php

use SAX\Entity\Entity;
use SAX\Entity\EntityDefinition;

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of MySQLTableCreator
 *
 * @author Honza
 */
class MySQLDatabaseManager extends \Nette\Object implements IDatabaseManager {

    private static $typeMap = array(
        "string" => "varchar(255)",
        "int" => "bigint",
        "text" => "text"
    );

    public function createTable(EntityDefinition $table) {
        if (!count($table->getColumns())) {
            return;
        }
        if($table->createDelayed) {
            return;
        }

        $columns = array();
        foreach ($table->getColumns() as $column) {
            $columns[] = "[" . $column->name . "] " . $this->getNativeType($column->getType()) . " NULL";
        }
        $columns = implode(", \n", $columns);

        $sql = 'CREATE TABLE [' . $table->getName() . '] (';
        $sql.=$columns;

        if (count($table->getPrimaryKeys())) {
            $sql.=", PRIMARY KEY(" . implode(", ", $table->getPrimaryKeys()) . ')';
        }

        $indexes = array();
        if (count($table->getIndexes())) {
            foreach ($table->getIndexes() as $key => $index) {
                $indexes[$key] = "INDEX([" . $index . "])";
            }
            $sql.=", " . implode(", ", $indexes);
        }
        $sql.=") ENGINE=InnoDB";

        dibi::query($sql);
        $table->tableCreated = TRUE;

        $this->createConstrains($table);
    }

    private function getNativeType($type) {
        return (isset(self::$typeMap[$type]) ? self::$typeMap[$type] : "varchar(255)");
    }

    private function createConstrains(EntityDefinition $table) {
        if (!count($table->getForeigns())) {
            return;
        }

        $ref = array();
        $sql = 'ALTER TABLE [' . $table->getName() . ']';
        
        foreach ($table->getForeigns() as $key) {
            if($this->columnExists($key['foreign'][0],$key['foreign'][1]))
                $ref[] = ' ADD FOREIGN KEY ([' . $key['column'] . ']) REFERENCES [' . $key['foreign'][0] . '] ([' . $key['foreign'][1] . '])';
        }
        if(count($ref)) {
            $sql.=implode(", \n", $ref);
            dibi::query($sql);
        }
    }

    private function columnExists($table, $column) {
        return count(dibi::fetchAll("SHOW TABLES LIKE '$table'")) && count(dibi::fetchAll("SHOW COLUMNS FROM [$table] WHERE [Field]=%s",$column));
    }

    public function fillTable(Entity $entity) {

        $data=$entity->getData();
        dibi::query("INSERT INTO [".$entity->getDefinition()->getName()."] %v",$data);
        return;
        
        foreach ($data as $table => $rows) {
            $maxRowsPerInsert = 500;
            $rows = array_chunk($rows, $maxRowsPerInsert);

            for ($i = 0; $i < count($rows); $i++) {
                $sql=array();
                foreach($rows[$i] as $row) {
                    $data="(";
                    array_walk($row, function (&$item, $key) {
                        if(!$item) $item="NULL";
                        else $item="'".mysql_real_escape_string($item)."'";
                    });
                    $data .= implode(", ",$row);
                    $sql[]=$data.")";
                }
                $keys=array_keys($row); //speedup
                array_walk($keys, function (&$item, $key) {
                        $item="`".mysql_real_escape_string($item)."`";
                    });
                dibi::getConnection()->nativeQuery("INSERT INTO `" . $table . "` (".implode(", ",$keys).") VALUES ".implode(", ",$sql)); //data
            }
        }
    }

    public function alterTable(EntityDefinition $entity) {
        $sql='ALTER TABLE ['.$entity->getName().']';
        $alters=array();
        if(isset($entity->alterTable['change']))
        foreach ($entity->alterTable['change'] as $key=>$value) {
            $alters[]=' MODIFY ['.$key.'] '.$this->getNativeType($entity->getColumn($key)->getType()).' NULL';
        }
        if(isset($entity->alterTable['add']))
        foreach ($entity->alterTable['add'] as $key=>$value) {
            $alters[]=' ADD ['.$key.'] '.$this->getNativeType($entity->getColumn($key)->getType()).' NULL';
        }
        $sql.=implode(", ",$alters);
        dibi::query($sql);
        
        $entity->alterTable=array();
    }

    public function createDatabase($name) {
        try {
            dibi::query("CREATE DATABASE [" . $name . "] COLLATE 'utf8_czech_ci'");
        } catch (DibiException $e) {
            throw new DatabaseManagerException('Unable to create database. Already exists?', 0, $e);
        }
    }

    public function dropDatabase($name) {
        dibi::query("DROP DATABASE IF EXISTS [" . $name . "]");
    }

    public function fillDatabase($tables) {
        //TODO razeni zavislosti
        foreach ($tables as $table) {
            $this->createTable($table);
        }

        dibi::query("SET foreign_key_checks = 0");
        foreach ($tables as $table) {
            $this->fillTable($table);
        }
        dibi::query("SET foreign_key_checks = 1");
    }

    public function setDefaultDatabase($name) {
        dibi::query("USE [$name]");
    }

    public function getTableInfo($db,  RevisionDefinition $def) {
        $structure = $this->getDatabaseStructure($db);
        $tables=array_fill_keys($def->getTables(), array());
            foreach (array_flip($def->getTables()) as $table => $items) {
                $tables[$table]['name']=$table;
                $tables[$table]['columns']=$def->getColumns($table);
            }
            foreach (array_flip($def->getTables()) as $table => $items) {
                if(isset($structure[$table]['primary'])) {
                    $tables[$table]['primary']=$structure[$table]['primary'];
                }
                if(isset($structure[$table]['foreign'])) {
                    foreach($structure[$table]['foreign'] as $column=>$reference) {
                        $col=explode(".",$reference);
                        if(isset($tables[$table]['columns'][$column]) && isset($tables[$col[0]]['columns'][$col[1]])) {
                            $tables[$table]['foreign'][$column]=$reference;
                        }
                    }
                }
            }
            return $tables;
    }

    public function createRevision($fromDb, $toDb, RevisionDefinition $def) {
        try {
            $this->createDatabase($toDb);
            $structure = $this->getDatabaseStructure($fromDb);
            $tables=array_fill_keys($def->getTables(), array());
            foreach (array_flip($def->getTables()) as $table => $items) {
                $tables[$table]['name']=$table;
                $tables[$table]['columns']=$def->getColumns($table);
            }
            foreach (array_flip($def->getTables()) as $table => $items) {
                if(isset($structure[$table]['primary'])) {
                    $tables[$table]['primary']=$structure[$table]['primary'];
                }
                if(isset($structure[$table]['foreign'])) {
                    foreach($structure[$table]['foreign'] as $column=>$reference) {
                        $col=explode(".",$reference);
                        if(isset($tables[$table]['columns'][$column]) && isset($tables[$col[0]]['columns'][$col[1]])) {
                            $tables[$table]['foreign'][$column]=$reference;
                        }
                    }
                }
            }

            while(TRUE) {
                foreach ($tables as $table=>$items) {
                    if(isset($items['foreign'])) {
                        foreach($items['foreign'] as $column=>$reference) {
                            $c=explode(".",$reference);
                            if(isset($tables[$c[0]])) { //after creation is table unset
                                continue 2;
                            }
                            else {
                                
                               // $def->setConstrain($table,$column,dibi::select($c[1])->from("[$toDb.".$c[0]."]")->fetchAssoc($c[1]));
                            }
                        }
                    }
                    //todo condions for subselecting
                    $this->copyTable($table, $items, $fromDb, $toDb, $def->getCondition($table));
                    unset($tables[$table]);
                }
                if(!count($tables)) {
                    break;
                }
            }
        } catch (DatabaseManagerException $e) {
            //rollback
            $this->dropDatabase($toDb);
            throw $e;
        }
    }

    public function dropTable($table) {
        dibi::query("SET foreign_key_checks = 0;");
        dibi::query("DROP TABLE IF EXISTS [" . $table . "]");
        dibi::query("SET foreign_key_checks = 1;");
    }

    public function copyTable($table, $items, $fromDb, $toDb, $condition) {
        try {
            
            $columns=array_keys($items['columns']);
            
            $sql="CREATE TABLE [$toDb.$table]";
            $keys='';
            if(isset($items['primary'])) {
                $keys.="PRIMARY KEY(".implode(",",array_keys($items['primary'])).")";
            }
            if(isset($items['foreign'])) {

                foreach($items['foreign'] as $column=>$ref) {
                    $col=explode(".",$ref);
                    if($keys) {
                        $keys.=', ';
                    }
                    $keys.="FOREIGN KEY ([$column]) REFERENCES [$col[0]] ([$col[1]])";
                }
            }
            if($keys) {
                $sql.="($keys)";
            }

            if(isset($items['foreign'])) {
                foreach($items['foreign'] as $key => $ref) {
                    $col=explode(".",$ref);
                    if($condition!="") {
                        $condition.=' AND ';
                    }
                    $condition.="[$key] IN (SELECT [$col[1]] FROM [$toDb.$col[0]])";
                }
            }

            $sql.=" SELECT ".implode(",",$columns)." FROM [$fromDb.$table]".($condition!=NULL?' WHERE '.$condition:'');

            dibi::query($sql);
            
        } catch (DibiException $e) {
            throw new DatabaseManagerException("Unable to copy table. " . $e->getMessage(), NULL, $e);
        }
    }

    public function updateTable($table,$columns,$condition, $from, $to) {
        try{
            dibi::begin();
            dibi::query("SET foreign_key_checks = 0;");
            dibi::query('DELETE FROM '.$to.'.'.$table);
            dibi::query("INSERT INTO [$to.$table] (".implode(", ",$columns).")
                SELECT ".implode(",",$columns)." FROM [$from.$table]".($condition!=NULL?' WHERE '.$condition:''));
            dibi::query("SET foreign_key_checks = 1;");
            dibi::commit();
        } catch (DibiException $e) {
            dibi::rollback();
            throw new DatabaseManagerException($e->getMessage(), NULL, $e);
        }
    }

    public function getDatabaseSize($database) {
        try {
            return dibi::query("SELECT SUM([data_length] + [index_length]) AS [size] FROM [information_schema.tables] WHERE [table_schema]=%s", $database)->fetchSingle();
        } catch (DibiException $e) {
            throw new DatabaseManagerException($e->getMessage(), NULL, $e);
        }
    }

    public function getDatabaseStructure($database) {
        $tables = dibi::select("*")->from("information_schema.TABLES")->where(array("TABLE_SCHEMA"=>$database))->fetchAssoc('TABLE_NAME');
        $tables_name = array_keys($tables);
        $keys = dibi::select(array("TABLE_NAME","CONSTRAINT_NAME","COLUMN_NAME","REFERENCED_TABLE_NAME","REFERENCED_COLUMN_NAME"))
                ->from("information_schema.KEY_COLUMN_USAGE k")
                    ->where(array("k.TABLE_SCHEMA"=>$database))->fetchAll();
        $columns = dibi::select("*")->from("information_schema.COLUMNS")->where(array("TABLE_SCHEMA"=>$database))->fetchAll();
        $exit = array_fill_keys($tables_name, array());
        foreach ($columns as $col) {
            $exit[$col['TABLE_NAME']]['columns'][$col['COLUMN_NAME']]=TRUE;
        }
        
        
        foreach($keys as $key) {
            switch ($key['CONSTRAINT_NAME']) {
                case 'PRIMARY':
                        $exit[$key['TABLE_NAME']]['primary'][$key['COLUMN_NAME']]=TRUE;
                    break;

                default:
                    $exit[$key['TABLE_NAME']]['foreign'][$key['COLUMN_NAME']]=$key['REFERENCED_TABLE_NAME'].".".$key["REFERENCED_COLUMN_NAME"];
                    break;
            }
        }
        
        return $exit;
    }

}

?>
