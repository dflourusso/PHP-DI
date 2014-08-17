<?php
/**
 * PHP-DI
 *
 * @link      http://mnapoli.github.com/PHP-DI/
 * @copyright Matthieu Napoli (http://mnapoli.fr/)
 * @license   http://www.opensource.org/licenses/mit-license.php MIT (see the LICENSE file)
 */

namespace DI\Definition\Resolver;

use DI\Definition\Definition;
use DI\Definition\Exception\DefinitionException;
use DI\Definition\FunctionCallDefinition;
use Interop\Container\ContainerInterface;

/**
 * Resolves a factory definition to a value.
 *
 * @since 4.2
 * @author Matthieu Napoli <matthieu@mnapoli.fr>
 */
class FunctionCallDefinitionResolver implements DefinitionResolver
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var ParameterResolver
     */
    private $parameterResolver;

    /**
     * The resolver needs a container. This container will be used to fetch dependencies.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->parameterResolver = new ParameterResolver($container);
    }

    /**
     * Resolve a function call definition to a value.
     *
     * This will call the function and return its result.
     *
     * {@inheritdoc}
     */
    public function resolve(Definition $definition, array $parameters = array())
    {
        if (! $definition instanceof FunctionCallDefinition) {
            throw new \InvalidArgumentException(
                sprintf(
                    'This definition resolver is only compatible with FunctionCallDefinition objects, %s given',
                    get_class($definition)
                )
            );
        }

        $callable = $definition->getCallable();

        if (is_array($callable)) {
            list($object, $method) = $callable;
            $functionReflection = new \ReflectionMethod($object, $method);
        } elseif ($callable instanceof \Closure) {
            $functionReflection = new \ReflectionFunction($callable);
        } elseif (is_object($callable) && method_exists($callable, '__invoke')) {
            $functionReflection = new \ReflectionMethod($callable, '__invoke');
        } else {
            $functionReflection = new \ReflectionFunction($callable);
        }

        try {
            $args = $this->parameterResolver->resolveParameters($definition, $functionReflection, $parameters);
        } catch (DefinitionException $e) {
            throw DefinitionException::create($definition, $e->getMessage());
        }

        if (is_array($callable)) {
            if ($functionReflection->isStatic()) {
                $object = null;
            } elseif (is_string($callable[0])) {
                $object = $this->container->get($callable[0]);
            } else {
                $object = $callable[0];
            }

            return $functionReflection->invokeArgs($object, $args);
        } elseif ($callable instanceof \Closure) {
            return $functionReflection->invokeArgs($args);
        } elseif (is_object($callable) && method_exists($callable, '__invoke')) {
            return $functionReflection->invokeArgs($callable, $args);
        } else {
            return $functionReflection->invokeArgs($args);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function isResolvable(Definition $definition, array $parameters = array())
    {
        if (! $definition instanceof FunctionCallDefinition) {
            throw new \InvalidArgumentException(sprintf(
                'This definition resolver is only compatible with FunctionCallDefinition objects, %s given',
                get_class($definition)
            ));
        }

        return true;
    }
}
