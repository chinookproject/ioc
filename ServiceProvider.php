<?php
namespace Chinook\Ioc;

abstract class ServiceProvider
{
    protected  $ioc;

    public function __construct ( IocContainer $ioc )
    {
        $this->ioc = $ioc;
    }

    abstract function register ( );
}

?>