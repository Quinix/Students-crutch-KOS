<?php

/**
 * Test class for Application.
 * Generated by PHPUnit on 2010-12-14 at 22:56:29.
 */
class ApplicationTest extends PHPUnit_Framework_TestCase {

    /**
     * @var Application
     */
    protected $object;
    private $id;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {

    }
    /**
     */
    public function testCreateAndSave() {
        $this->object=Application::create(array(
            'name'=>'test',
            'password'=>'test',
            'login'=>'test',
            'admin_email'=>'test'
        ));
        $this->assertInstanceOf('Application', $this->object, 'Test creation');
        $this->object->save();
        $this->assertGreaterThan(0,$this->object->app_id,'Test saving');
    }



    /**
     * @todo Implement testFind().
     */
    public function testFind() {
        $this->object=@reset(Application::find(array(
            'name'=>'test',
            'login'=>'test',
            'admin_email'=>'test'
        )));
        $this->assertInstanceOf('Application',$this->object,'Test application find');
    }

    public function testUpdate() {
        $this->object=@reset(Application::find(array(
            'name'=>'test',
            'login'=>'test',
            'admin_email'=>'test'
        )));
        $this->object->name='testt';
        $this->object->save();
        $object=@reset(Application::find(array(
            'name'=>'testt',
            'login'=>'test',
            'admin_email'=>'test'
        )));
        $this->assertInstanceOf('Application',$object,'Test application update');
    }

    

    /**
     * @todo Implement testDelete().
     */
    public function testDelete() {
        $data=(Application::find(array(
            'name'=>'testt',
            'login'=>'test',
            'admin_email'=>'test'
        )));
        $this->assertEquals(1,count($data));
        $data=@reset($data);
        $data->delete();
        $this->assertEquals(0,count(Application::find(array(
            'name'=>'testt',
            'login'=>'test',
            'admin_email'=>'test'
        ))));
    }

    /**
     * @todo Implement testHashPassword().
     */
    public function testHashPassword() {
        $this->assertEquals(hash("sha256", 'test'),Application::hashPassword('test'));
    }

}

?>
