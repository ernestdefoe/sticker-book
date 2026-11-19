<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Settings;
use ErnestDefoe\StickerBook\Trade;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Psr\Http\Message\ServerRequestInterface;

/**
 * GET /api/sticker-book/trades: the member's offers, waiting ones first,
 * then the 20 most recent closed ones. Only trades they are part of.
 */
class TradesController extends Endpoint
{
    public function __construct(private readonly Presenter $presenter)
    {
    }

    protected function run(ServerRequestInterface $request, User $actor): array
    {
        $actor->assertRegistered();
        $actor->assertCan(Settings::TRADE);

        $mine = fn (Builder $q) => $q->where('from_user_id', $actor->id)->orWhere('to_user_id', $actor->id);
        $pending = Trade::query()->where($mine)->where('status', Trade::PENDING)->orderByDesc('id')->limit(50)->get();
        $closed = Trade::query()->where($mine)->where('status', '<>', Trade::PENDING)->orderByDesc('id')->limit(20)->get();

        return $this->presenter->trades($actor, $pending->concat($closed)->all());
    }
}
