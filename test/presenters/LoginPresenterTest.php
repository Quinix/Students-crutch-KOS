<?php

/**
 * Test class for LoginPresenter.
 * Generated by PHPUnit on 2011-01-03 at 15:39:56.
 */
class LoginPresenterTest extends PHPUnit_Framework_TestCase {

    /**
     * @var LoginPresenter
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $this->object = new LoginPresenter;
        $this->object->autoCanonicalize = FALSE;
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {

    }

    public function testActionDefault() {
        $request = new \Nette\Application\PresenterRequest("Login", "HEAD", array());
        $response = $this->object->run($request);
        $this->assertInstanceOf('Nette\\Application\\RenderResponse', $response);
    }

    public function testCreateComponentLoginForm() {
        $this->assertInstanceOf('Nette\\Application\\AppForm', $this->object['loginForm']);
    }



    /**
     * @todo Implement testActionLogout().
     */
    public function testActionLogout() {
        $request = new \Nette\Application\PresenterRequest("Login", "HEAD", array("action" => 'logout'));
        $response = $this->object->run($request);

        $this->assertFalse($this->object->getUser()->isLoggedIn());

        $this->assertInstanceOf('Nette\\Application\\RedirectingResponse', $response);

        //log back
        if (!Nette\Environment::isProduction()) {
            $user = Nette\Environment::getUser();
            if (!$user->isLoggedIn()) {
                $user->login('test', 'TEST');
            }
        }
    }

}

?>
