<?php

namespace Virtue\Api\Routing;

use Psr\Container\ContainerInterface as Locator;
use Psr\Http\Message\ResponseFactoryInterface as ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as ServerRequest;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Handlers\Strategies\RequestHandler;
use Slim\Interfaces\AdvancedCallableResolverInterface;
use Slim\Interfaces\InvocationStrategyInterface;
use Slim\Interfaces\RequestHandlerInvocationStrategyInterface;
use Slim\MiddlewareDispatcher;
use Virtue\Api\Middleware\MiddlewareStack;
use function class_implements;
use function in_array;
use function is_array;

class Route implements RequestHandlerInterface
{
    /** @var string[] */
    protected $methods = [];
    /** @var string */
    protected $identifier;
    /** @var RouteGroup[] */
    protected $groups;
    /** @var MiddlewareDispatcher */
    protected $middlewareStack;
    /** @var callable|string */
    protected $handler;
    /** @var Locator */
    protected $kernel;
    /** @var string */
    protected $pattern;

    public function __construct(
        array $methods,
        string $pattern,
        $handler,
        Locator $kernel,
        array $groups = [],
        int $identifier = 0
    ) {
        $this->methods = $methods;
        $this->pattern = $pattern;
        $this->handler = $handler;
        $this->kernel = $kernel;
        $this->groups = $groups;
        $this->identifier = "route::{$identifier}";
        $this->middlewareStack = new MiddlewareStack($this);
    }

    public function getMethods(): array
    {
        return $this->methods;
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function add(string $middleware): self
    {
        $this->middlewareStack->append($this->kernel->get($middleware));

        return $this;
    }

    public function run(ServerRequest $request): Response
    {
        return $this->buildStack()->handle($request);
    }

    protected function buildStack(): MiddlewareStack
    {
        $stack = new MiddlewareStack($this->middlewareStack);

        /** @var RouteGroup $group */
        foreach ($this->groups as $group) {
            $group->appendTo($stack);
        }

        return $stack;
    }

    public function handle(ServerRequest $request): Response
    {
        $callableResolver = $this->kernel->get(AdvancedCallableResolverInterface::class);
        $handler = $callableResolver->resolveRoute($this->handler);
        $strategy = $this->kernel->get(InvocationStrategyInterface::class);

        if (
            is_array($handler)
            && $handler[0] instanceof RequestHandlerInterface
            && !in_array(RequestHandlerInvocationStrategyInterface::class, class_implements($strategy))
        ) {
            $strategy = new RequestHandler();
        }

        $response = $this->kernel->get(ResponseFactory::class)->createResponse();
        return $strategy($handler, $request, $response, RoutingResults::fromRequest($request)->getRouteArgs());
    }
}
