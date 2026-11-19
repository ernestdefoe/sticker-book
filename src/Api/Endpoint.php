<?php

namespace ErnestDefoe\StickerBook\Api;

use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Our custom endpoints: actions (open a pack, react, gift, add the starter
 * sets) and read models shaped per viewer (an album with silhouettes for what
 * they don't have, the board, who has spares, the pack status). None of them
 * is a record to create, list or change, so none is a JSON:API resource; the
 * sets, stickers and trades are (Api\Resource). A refusal (StickerException)
 * reaches the browser through StickerErrorHandler, like any API error.
 */
abstract class Endpoint implements RequestHandlerInterface
{
    /** @return array<string, mixed> */
    abstract protected function run(ServerRequestInterface $request, User $actor): array;

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse($this->run($request, RequestUtil::getActor($request)));
    }

    /** @return array<string, mixed> */
    protected function body(ServerRequestInterface $request): array
    {
        $body = $request->getParsedBody();

        return is_array($body) ? $body : [];
    }
}
