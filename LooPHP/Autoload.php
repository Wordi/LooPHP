<?php

class LooPHP_Autoload
{

    static public function register()
    {
        spl_autoload_register( array( 'LooPHP_Autoload', 'autoload' ) );
    }

    static public function autoload($class)
    {
        if( 0 !== strpos($class, 'LooPHP_' ) )
            return false;

        require dirname(__FILE__).'/../'.str_replace('_', '/', $class).'.php';

        return true;
    }
}

?>