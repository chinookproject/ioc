<?php
namespace Chinook\Ioc;

class IocContainer
{
    protected $services = array ( );
    protected $singletons = array ( );

    public function __construct ( )
    {
        $this->singletonInstance ( 'Chinook\Ioc\IocContainer', $this );
    }

    public function bind ( $type, $mixed = null )
    {
        if ( $mixed === null )
            $this->services[$type] = $type;
        else
            $this->services[$type] = $mixed;
    }

    /*
     * @var string $type Namespace and class name
     * @var object $obj An instance of a class
     */
    public function instance ( $type, $obj )
    {
        $this->services[$type] = function() use ($obj) {
            return $obj;
        };
    }

    /*
    * @var string $type Namespace and class name
    * @var object $obj An instance of a class
    */
    public function singletonInstance ( $type, $obj )
    {
        $this->singletons[$type] = $obj;
        $this->instance ( $type, $obj );
    }

    public function singleton ( $type, $mixed = null )
    {
        if ( !isset ( $this->singletons[$type] ) )
        {
            $this->singletons[$type] = null;
            $this->bind ( $type, $mixed );
        }
    }

    public function remove ( $type )
    {
        unset ( $this->services[$type] );
        unset ( $this->singletons[$type] );
    }

    private function resolveCallableInstance ( $name )
    {
        if ( array_key_exists ( $name, $this->singletons ) )
        {
            if ( $this->singletons[$name] === null )
            {
                if ( is_callable ( $this->services[$name] ) )
                {
                    $this->singletons[$name] = $this->services[$name]();
                    return $this->singletons[$name];
                }
            }
            else
            {
                return $this->singletons[$name];
            }
        }
        if ( is_callable ( $this->services[$name] ) )
        {
            return $this->services[$name]();
        }

        return null;
    }

    public function create ( $type )
    {
        $className = $type;

        if ( isset ( $this->services[$type] ) )
        {
            $result = $this->resolveCallableInstance ( $type );
            if ( $result !== null ) {
                return $result;
            }

            $className = $this->services[$type];
        }

        // If the requested service isn't registered, then try to resolve it.
        try
        {
            $classInfo = new \ReflectionClass ( $className );
            $data = $this->resolveParams ( $classInfo );
        }
        catch ( \Exception $ex )
        {
            throw new \Exception ( $ex->getMessage() );
        }

        if ( array_key_exists ( $type, $this->singletons ) )
        {
            $this->singletons[$type] = $data;
            return $this->singletons[$type];
        }

        return $data;
    }

    protected function resolveParams ( $class )
    {
        $classInfo = new \ReflectionClass ( $class->getName() );

        if ( $classInfo->isInterface() )
        {
            require_once ( $classInfo->getFileName() );

            if ( !isset ( $this->services[$classInfo->getName()] ) )
            {
                throw new \Exception ( "Trying to find a binding for the interface '".$classInfo->getName()."', but cannot find any." );
            }
        }

        if ( isset ( $this->services[$class->getName()] ) )
        {
            $result = $this->resolveCallableInstance ( $class->getName() );
            if ( $result !== null ) {
                return $result;
            }

            $name = $this->services[$class->getName()];
            $classInfo = new \ReflectionClass ( $name );
        }

        $ctor = $classInfo->getConstructor();

        $args = array ( );
        if ( $ctor !== null )
        {
            $params = $ctor->getParameters();

            foreach ( $params as $param )
            {
                try
                {
                    $paramClassInfo = $param->getClass();
                }
                catch(\Exception $e)
                {
                    throw new \Exception("Failed to get class info for parameter name '".$param->getName()."' in class: '".$classInfo->getName()."'. The file probably isn\'t included yet in your code or through your autoloader.");
                }

                if ( $paramClassInfo === null )
                {
                    if ( $param->isOptional() )
                        continue;

                    throw new \Exception ( "Failed to resolve an instance or value for the parameter '$".$param->getName()."'
					in class '".$class->getName()."'. Try to create a binding for the class '".$class->getName()."' instead." );
                }

                $args[] = $this->resolveParams ( $paramClassInfo );
            }
        }

        require_once ( $classInfo->getFileName() );
        $instance = $classInfo->newInstanceArgs ( $args );

        return $instance;
    }
}


?>