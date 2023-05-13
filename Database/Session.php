<?php 

namespace EvoPhp\Database;

/**
 * summary
 */
class Session
{
    use \EvoPhp\Api\Auth;
    const SESSION_STARTED = TRUE;
    const SESSION_NOT_STARTED = FALSE;
    private $destroyed = false;
   
    // The state of the session
    private $sessionState = self::SESSION_NOT_STARTED;
   
    // THE only instance of the class
    private static $instance;
   
   
    private function __construct() {}
   
   
    /**
    *    Returns THE instance of 'Session'.
    *    The session is automatically initialized if it wasn't.
    *   
    *    @return    object
    **/
   
    public static function getInstance()
    {
        if ( !isset(self::$instance))
        {
            self::$instance = new self;
        }
       
        self::$instance->startSession();
       
        return self::$instance;
    }
   
   
    /**
    *    (Re)starts the session.
    *   
    *    @return    bool    TRUE if the session has been initialized, else FALSE.
    **/
   
    public function startSession()
    {
        if ( $this->sessionState == self::SESSION_NOT_STARTED && !$this->destroyed)
        {
            if(!isset($_SESSION)) {
                $this->sessionState = \Delight\Cookie\Session::start();
            } else $this->sessionState = self::SESSION_STARTED;
        }
       
        return $this->sessionState;
    }

    public function getResourceOwner() {
        if(!isset($this->accesstoken)) {
            return false;
        }
        if ($tokenMeta = $this->getTokenObject($this->accesstoken)) {
            return $tokenMeta;
        }
        return false;
    }

    public function increment($property, $steps = 1) {
        if(!isset($this->$property)) {
            $this->$property = $steps;
            return $this;
        }
        if(gettype($this->$property) !== "integer") {
            return $this;
        }
        $this->$property += $steps;
        return $this;
    }
   
    public function decrement($property, $steps = 1) {
        if(!isset($this->$property)) {
            $this->$property = 0 - $steps;
            return $this;
        }
        if(gettype($this->$property) !== "integer") {
            return $this;
        }
        $this->$property -= $steps;
        return $this;
    }
    /**
    *    Stores datas in the session.
    *    Example: $instance->foo = 'bar';
    *   
    *    @param    name    Name of the datas.
    *    @param    value    Your datas.
    *    @return    void
    **/
   
    public function __set( $key , $value )
    {
        \Delight\Cookie\Session::set($key, $value);
    }
   
   
    /**
    *    Gets datas from the session.
    *    Example: echo $instance->foo;
    *   
    *    @param    name    Name of the datas to get.
    *    @return    mixed    Datas stored in session.
    **/
   
    public function __get( $key )
    {
        return \Delight\Cookie\Session::get($key);
    }
   
   
    public function __isset( $key )
    {
        return \Delight\Cookie\Session::has($key);
    }
   
   
    public function __unset( $key )
    {
        \Delight\Cookie\Session::delete($key);
    }
   
   
    /**
    *    Destroys the current session.
    *   
    *    @return    bool    TRUE is session has been deleted, else FALSE.
    **/
   
    public function destroy()
    {
        if ( $this->sessionState == self::SESSION_STARTED )
        {
            $this->sessionState = !session_destroy();
            $this->destroyed = true;
            unset( $_SESSION );
           
            return !$this->sessionState;
        }
       
        return FALSE;
    }
}