<?php
namespace Magic\Container;

use Psr\Container\ContainerInterface;
use Magic\Container\Exception\ContainerException;
use Magic\Container\Exception\ServiceNotFoundException;
use Magic\Container\Exception\ParameterNotFoundException;
use ReflectionClass;
use Magic\Container\Reference\ServiceReference;
use Magic\Container\Reference\ParameterReference;

/**
 * Psr container
 * 
 * @see https://www.sitepoint.com/how-to-build-your-own-dependency-injection-container/
 */
class Container implements ContainerInterface
{

    private $services;
    private $parameters;
    private $serviceStore;
    
    public function __construct(array $services = [], array $parameters = [])
    {
        $this->services     = $services;
        $this->parameters   = $parameters;
        $this->serviceStore = [];
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws ServiceNotFoundException  No entry was found for **this** identifier.
     *
     * @return object service.
     */
    public function get($id)
    {
        if (!$this->has($id)) {
            throw new ServiceNotFoundException('Service not found: '.$id);
        }

        if (!isset($this->serviceStore[$id])) {
            $this->serviceStore[$id] = $this->createService($id);           
        }

        return $this->serviceStore[$id];
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($id)
    {
        return isset($this->services[$id]);
    }

    /**
     * Create Service
     * 
     * @param  string $id 
     *
     * @throws ContainerException Service Definitions err
     *
     * @return object service.
     */
    private function createService($id)
    {
        $entry = &$this->services[$id];
        
        if (!is_array($entry) || !isset($entry['class'])) {
            throw new ContainerException($id.' service entry must be an array containing a \'class\' key');
        } elseif (!class_exists($entry['class'])) {
            throw new ContainerException($id.' service class does not exist: '.$entry['class']);
        } elseif (isset($entry['lock'])) {
            throw new ContainerException($id.' service contains a circular reference');
        }
        
        $entry['lock'] = true;
        
        $arguments = isset($entry['arguments']) ? $this->resolveArguments($id, $entry['arguments']) : [];
        
        $reflector = new ReflectionClass($entry['class']);
        
        // Constructor injection.
        // Creates a new class instance from given arguments
        $service = $reflector->newInstanceArgs($arguments);
        
        // Setter injection
        if (isset($entry['calls'])) {
            $this->initializeService($service, $id, $entry['calls']);
        }
        
        return $service;
    }

    /**
     * Resolve dependencies
     * 
     * @param  string  $id 
     * @param  array   $argumentDefinitions
     * @return array   The given arguments are passed to the class constructor
     */
    private function resolveArguments($id, array $argumentDefinitions)
    {
        $arguments = [];
        
        foreach ($argumentDefinitions as $argumentDefinition) {
            if ($argumentDefinition instanceof ServiceReference) {
                $argumentServiceName = $argumentDefinition->getName();
            
                // loop through service definitions, for autowiring
                $arguments[] = $this->get($argumentServiceName);

            } elseif ($argumentDefinition instanceof ParameterReference) {
                $argumentParameterName = $argumentDefinition->getName();
            
                $arguments[] = $this->getParameter($argumentParameterName);
            } else {
                $arguments[] = $argumentDefinition;
            }
        }
        
        return $arguments;
    }

    /**
     * Performs the setter injection
     *
     * @param object $service               a new class instance from given arguments
     * @param string $id                    service identifier
     * @param array  $callDefinitions       custom definitions
     *
     * @throws ContainerException
     *
     * @return 
     */
    private function initializeService($service, $id, array $callDefinitions)
    {
        foreach ($callDefinitions as $callDefinition) {
            if (!is_array($callDefinition) || !isset($callDefinition['method'])) {
                throw new ContainerException($id.' service calls must be arrays containing a \'method\' key');
            } elseif (!is_callable([$service, $callDefinition['method']])) {
                throw new ContainerException($id.' service asks for call to uncallable method: '.$callDefinition['method']);
            }

            $arguments = isset($callDefinition['arguments']) ? $this->resolveArguments($id, $callDefinition['arguments']) : [];

            call_user_func_array([$service, $callDefinition['method']], $arguments);
        }
    }

    public function getParameter($id)
    {
        $tokens = explode(".", $id);
        $context = $this->parameters;
        
        while (null !== ($token = array_shift($tokens))) {
            if (!isset($context[$token])) {
                throw new ParameterNotFoundException('Parameter not found: '.$id);
            }

            $context = $context[$token];    
        }

        return $context;
    }
    
    // todo ...
    // should be like Symfony $containerBuilder->enableCompilation(__DIR__ . '/var/cache');
    public function enableCompilation($stream)
    {
        
    }

    // todo ...
    // https://symfony.com/doc/current/components/dependency_injection/compilation.html
    public function compile()
    {

    }


}