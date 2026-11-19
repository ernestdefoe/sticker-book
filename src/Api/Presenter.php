<?php

namespace ErnestDefoe\StickerBook\Api;

use Flarum\Http\SlugManager;
use Flarum\User\User;

/** Members as the browser's store wants them: one compact row each. */
class Presenter
{
    public function __construct(private readonly SlugManager $slugs)
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
}
