<?php

declare(strict_types=1);

namespace NEOSidekick\MarkdownForAgents\Tests\Unit\Http;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class UnitCapturingRequestHandler implements RequestHandlerInterface
{
    public ServerRequestInterface $request;

    private ResponseInterface $response;

    public function __construct(?ResponseInterface $response = null)
    {
        $this->response = $response ?? new Response(204);
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;
        return $this->response;
    }
}
