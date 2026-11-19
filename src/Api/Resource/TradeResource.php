<?php

namespace ErnestDefoe\StickerBook\Api\Resource;

use ErnestDefoe\StickerBook\Api\Throttle;
use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Settings;
use ErnestDefoe\StickerBook\Trade;
use ErnestDefoe\StickerBook\Trades;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Api\Sort\SortColumn;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as OriginalContext;

/**
 * Trades of spares between two members. 🚨 A trade is visible only to its
 * two members (scope): every endpoint, the actions included, finds trades
 * through it, so anyone else's trade is "not found" whatever its id, and a
 * guest sees none.
 *
 * Offering goes through Trades::offer (spares checked, limits, notification);
 * accepting through Trades::accept, which moves the stickers in one
 * transaction behind both members' locks. Accept, decline and cancel are
 * actions, not updates: no field of a trade is ever written by a member.
 *
 * @extends AbstractDatabaseResource<Trade>
 */
class TradeResource extends AbstractDatabaseResource
{
    public function __construct(
        private readonly Trades $trades,
        private readonly Catalog $catalog,
        private readonly Throttle $throttle,
    ) {
    }

    public function type(): string
    {
        return 'sticker-book-trades';
    }

    public function model(): string
    {
        return Trade::class;
    }

    public function scope(Builder $query, OriginalContext $context): void
    {
        $actor = $context->getActor();
        if ($actor->isGuest()) {
            $query->whereKey([]);

            return;
        }

        $query->where(fn (Builder $q) => $q->where('from_user_id', $actor->id)->orWhere('to_user_id', $actor->id));
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()
                ->authenticated()
                ->can(Settings::TRADE)
                ->defaultInclude(['from', 'to'])
                ->defaultSort('-id')
                ->paginate(50, 50),
            Endpoint\Show::make()
                ->authenticated()
                ->can(Settings::TRADE)
                ->defaultInclude(['from', 'to']),
            Endpoint\Create::make()
                ->authenticated()
                ->can(Settings::TRADE)
                ->defaultInclude(['from', 'to']),
            $this->action('accept', fn (Trade $trade, User $actor) => $this->trades->accept($trade, $actor)),
            $this->action('decline', fn (Trade $trade, User $actor) => $this->trades->decline($trade, $actor)),
            $this->action('cancel', fn (Trade $trade, User $actor) => $this->trades->cancel($trade, $actor)),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('status')
                ->get(fn (Trade $trade) => $trade->effectiveStatus()),
            // [[sticker id, copies], …]: what the sender gives and asks for. Trades::offer checks them.
            Schema\Arr::make('give')
                ->writableOnCreate()
                ->requiredOnCreate(),
            Schema\Arr::make('take')
                ->writableOnCreate(),
            Schema\Str::make('message')
                ->nullable()
                ->writableOnCreate()
                ->rule('max:200'),
            // The stickers in the offer, with their art: both members are looking at what is on offer.
            Schema\Arr::make('stickers')
                ->get(fn (Trade $trade) => array_values(array_filter(array_map(
                    fn (array $item) => $this->catalog->card((int) $item[0]),
                    [...$trade->give, ...$trade->take]
                )))),
            Schema\DateTime::make('createdAt'),
            Schema\DateTime::make('decidedAt')->nullable(),
            Schema\Relationship\ToOne::make('from')
                ->type('users')
                ->includable(),
            Schema\Relationship\ToOne::make('to')
                ->type('users')
                ->includable()
                ->writableOnCreate()
                ->requiredOnCreate(),
        ];
    }

    public function sorts(): array
    {
        return [SortColumn::make('id')];
    }

    /** The offer is made by Trades::offer, which checks it and tells the other member. */
    public function create(object $model, OriginalContext $context): object
    {
        /** @var Trade $model */
        $actor = $context->getActor();
        $this->throttle->spend($actor, 'offer');

        return $this->trades->offer($actor, $model->to, $model->give, $model->take ?? [], $model->message);
    }

    /** @param \Closure(Trade, User): Trade $run */
    private function action(string $name, \Closure $run): Endpoint\Endpoint
    {
        return Endpoint\Endpoint::make('sticker-book.trades.'.$name)
            ->route('POST', '/{id}/'.$name)
            ->authenticated()
            ->can(Settings::TRADE)
            ->defaultInclude(['from', 'to'])
            ->action(function (Context $context) use ($run) {
                $actor = $context->getActor();
                $this->throttle->spend($actor, 'trade-action');

                /** @var Trade $trade */
                $trade = $context->model;

                return $run($trade, $actor);
            });
    }
}
