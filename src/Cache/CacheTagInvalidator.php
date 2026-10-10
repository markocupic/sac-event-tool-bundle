<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Cache;

/**
 * Invalidates HTTP cache tags.
 *
 * Contao 5.3 ships the "contao.cache.entity_tags" service (EntityCacheTags),
 * Contao 6 replaced it with the "contao.cache.tag_manager" service
 * (CacheTagManager). Both provide invalidateTagsFor(). The matching service is
 * aliased to "sacevt.contao_cache_tag_manager" in the CacheTagManagerPass.
 */
class CacheTagInvalidator
{
    public function __construct(private readonly object|null $cacheTagManager = null)
    {
    }

    /**
     * @param array<string> $tags
     */
    public function invalidateTags(array $tags): void
    {
        if ([] === $tags || null === $this->cacheTagManager) {
            return;
        }

        $this->cacheTagManager->invalidateTagsFor($tags);
    }
}
