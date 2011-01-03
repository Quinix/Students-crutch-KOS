<?php

/**
 * Test class for OperationPresenter.
 * Generated by PHPUnit on 2011-01-03 at 15:40:00.
 */
class OperationPresenterTest extends PHPUnit_Framework_TestCase {

    /**
     * @var OperationPresenter
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $this->object = new OperationPresenter;
        $this->object->autoCanonicalize=FALSE;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {

    }

    /**
     * @todo Implement testActionDefault().
     */
    public function testActionDefault() {

        $request=new \Nette\Application\PresenterRequest("Operation", "HEAD", array("app_id"=>'0'));
        $response= $this->object->run($request);
        $this->assertInstanceOf('Nette\\Application\\RenderResponse',$response);
        
    }

    /**
     * @todo Implement testActionAddOperation().
     */
    public function testActionAddOperation() {

        $request=new \Nette\Application\PresenterRequest("Operation", "HEAD", array('action'=>'addOperation'));
        $response= $this->object->run($request);
        $this->assertInstanceOf('Nette\\Application\\RenderResponse',$response);
    }

    /**
     * @todo Implement testActionEditOperation().
     */
    public function testActionEditOperation() {

        $request=new \Nette\Application\PresenterRequest("Operation", "HEAD", array('action'=>'editOperation'));
        $response= $this->object->run($request);
        $this->assertInstanceOf('Nette\\Application\\RenderResponse',$response);
    }

    /**
     * @todo Implement testActionDeleteOperation().
     */
    public function testActionDeleteOperation() {

        $request=new \Nette\Application\PresenterRequest("Operation", "HEAD", array('action'=>'deleteOperation'));
        $response= $this->object->run($request);
        $this->assertInstanceOf('Nette\\Application\\RedirectingResponse',$response);
    }

    
    /**
     * @todo Implement testActionEditSql().
     */
    public function testActionEditSql() {
        $request=new \Nette\Application\PresenterRequest("Operation", "HEAD", array('action'=>'editSql'));
        $response= $this->object->run($request);
        $this->assertInstanceOf('Nette\\Application\\RenderResponse',$response);
    }

    /**
     * @todo Implement testActionDefineSql().
     */
    public function testActionDefineSql() {
                $request=new \Nette\Application\PresenterRequest("Operation", "HEAD", array('action'=>'defineSql'));
        $response= $this->object->run($request);
        $this->assertInstanceOf('Nette\\Application\\RenderResponse',$response);
    }


}

?>