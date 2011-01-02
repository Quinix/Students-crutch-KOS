<?php

/**
 * Test class for Operation.
 * Generated by PHPUnit on 2011-01-02 at 19:48:55.
 */
class OperationTest extends PHPUnit_Framework_TestCase {

    /**
     * @var Operation
     */
    protected $object;

    

    public function testFind() {
        $this->object=@reset(Operation::find());
        $this->assertInstanceOf('Operation',$this->object,'Test operation find');
    }

    /**
     * @todo Implement testGetWithSQLs().
     */
    public function testGetWithSQLs() {
        $this->object=@reset(Operation::getWithSQLs());
        $this->assertArrayHasKey('sql',(array)$this->object);
    }

    /**
     * @todo Implement testGetSQL().
     */
    public function testGetSQL() {
        $this->assertInstanceOf('DibiRow',  Operation::getSQL(array()));
    }

    /**
     * @todo Implement testCreate().
     */
    public function testCRUD() {
        $object=Operation::create(array(
            "app_id"=>@reset(Application::find())->app_id,
            "name"=>'xxx',
            "dynamicContainer"=>array(),
            'return'=>'int',
            'fetchType'=>'single'
            ));
        $this->assertInstanceOf('Operation',$object);
        dump($object);
        $object->save();
        $this->assertEquals(1,count(Operation::find(array(
                "app_id"=>@reset(Application::find())->app_id,
                "name"=>'xxx'))
                ));
        $object->delete();
        $this->assertEquals(0,count(Operation::find(array(
                "app_id"=>@reset(Application::find())->app_id,
                "name"=>'xxx'))
                ));
    }

}

?>
