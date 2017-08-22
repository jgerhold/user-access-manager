<?php
/**
 * CacheController.php
 *
 * The ObjectController class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\Controller\Backend;

use UserAccessManager\Cache\Cache;
use UserAccessManager\ObjectHandler\ObjectHandler;

/**
 * Class CacheController
 *
 * @package UserAccessManager\Controller\Backend
 */
class CacheController
{
    /**
     * @var Cache
     */
    private $cache;

    /**
     * CacheController constructor.
     *
     * @param Cache $cache
     */
    public function __construct(Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Invalidates the term related cache objects.
     */
    public function invalidateTermCache()
    {
        $this->cache->invalidate(ObjectHandler::POST_TERM_MAP_CACHE_KEY);
        $this->cache->invalidate(ObjectHandler::TERM_POST_MAP_CACHE_KEY);
        $this->cache->invalidate(ObjectHandler::TERM_TREE_MAP_CACHE_KEY);
    }

    /**
     * Invalidates the post related cache objects.
     */
    public function invalidatePostCache()
    {
        $this->cache->invalidate(ObjectHandler::TERM_POST_MAP_CACHE_KEY);
        $this->cache->invalidate(ObjectHandler::POST_TERM_MAP_CACHE_KEY);
        $this->cache->invalidate(ObjectHandler::POST_TREE_MAP_CACHE_KEY);
    }
}
