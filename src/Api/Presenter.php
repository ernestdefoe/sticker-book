<?php

namespace ErnestDefoe\StickerBook\Api;

use ErnestDefoe\StickerBook\Catalog;
use ErnestDefoe\StickerBook\Trade;
use Flarum\Http\SlugManager;
use Flarum\User\User;

/** Members as the browser's store wants them: one compact row each. */
class Presenter
{
    public function __construct(private readonly SlugManager $slugs, private readonly Catalog $catalog)
    {
    }

    /**
     * @param iterable<User> $users
     * @return list<array<string, mixed>>
     */
    public function users(iterable $users): array
    {
        $slugger = $this->slugs->forResource(User::class);
        $out = [];
        foreach ($users as $user) {
            $out[] = [
                'id' => (int) $user->id,
                'username' => $user->username,
                'displayName' => $user->display_name,
                'slug' => $slugger->toSlug($user),
                'avatarUrl' => $user->avatar_url,
            ];
        }

        return $out;
    }

    /**
     * Members the viewer may see, by id, in one query.
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function visible(User $actor, array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));

        return $ids ? $this->users(User::query()->whereVisibleTo($actor)->whereIn('id', $ids)->get()) : [];
    }

    /**
     * Trades with the stickers in them (their art: both members are looking
     * at what is on offer) and the members.
     *
     * @param list<Trade> $trades
     * @return array<string, mixed>
     */
    public function trades(User $actor, array $trades): array
    {
        $stickers = [];
        $users = [];
        $rows = [];
        foreach ($trades as $trade) {
            foreach ([...$trade->give, ...$trade->take] as [$id]) {
                $stickers[$id] ??= $this->catalog->card($id);
            }
            $users[] = $trade->from_user_id;
            $users[] = $trade->to_user_id;
            $rows[] = [
                'id' => $trade->id,
                'from' => $trade->from_user_id,
                'to' => $trade->to_user_id,
                'status' => $trade->effectiveStatus(),
                'give' => $trade->give,
                'take' => $trade->take,
                'message' => $trade->message,
                'createdAt' => $trade->created_at->toIso8601String(),
                'decidedAt' => $trade->decided_at?->toIso8601String(),
            ];
        }

        return ['trades' => $rows, 'stickers' => array_values(array_filter($stickers)), 'users' => $this->visible($actor, $users)];
    }
}
