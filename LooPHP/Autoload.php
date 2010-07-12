<?php

class LooPHP_Autoloader
{

    static public function register()
    {
        spl_autoload_register( array( 'LooPHP_Autoloader', 'autoload' ) );
    }

    static public function autoload($class)
    {
        if( 0 !== strpos($class, 'LooPHP' ) )
            return false;

        require dirname(__FILE__).'/../'.str_replace('_', '/', $class).'.php';

        return true;
    }
}

?>