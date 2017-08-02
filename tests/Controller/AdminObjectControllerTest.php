<?php
/**
 * AdminObjectControllerTest.php
 *
 * The AdminObjectControllerTest unit test class file.
 *
 * PHP versions 5
 *
 * @author    Alexander Schneider <alexanderschneider85@gmail.com>
 * @copyright 2008-2017 Alexander Schneider
 * @license   http://www.gnu.org/licenses/gpl-2.0.html  GNU General Public License, version 2
 * @version   SVN: $id$
 * @link      http://wordpress.org/extend/plugins/user-access-manager/
 */
namespace UserAccessManager\Tests\Controller;

use UserAccessManager\AccessHandler\AccessHandler;
use UserAccessManager\Controller\AdminObjectController;
use UserAccessManager\ObjectHandler\ObjectHandler;
use UserAccessManager\ObjectHandler\PluggableObject;
use UserAccessManager\Tests\UserAccessManagerTestCase;
use UserAccessManager\UserGroup\DynamicUserGroup;
use Vfs\FileSystem;
use Vfs\Node\Directory;
use Vfs\Node\File;

/**
 * Class AdminObjectControllerTest
 *
 * @package UserAccessManager\Controller
 * @coversDefaultClass \UserAccessManager\Controller\AdminObjectController
 */
class AdminObjectControllerTest extends UserAccessManagerTestCase
{
    /**
     * @var FileSystem
     */
    private $root;

    /**
     * Setup virtual file system.
     */
    public function setUp()
    {
        $this->root = FileSystem::factory('vfs://');
        $this->root->mount();
    }

    /**
     * Tear down virtual file system.
     */
    public function tearDown()
    {
        $this->root->unmount();
    }
    /**
     * @param int   $id
     * @param array $withAdd
     * @param array $withRemove
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|\UserAccessManager\UserGroup\UserGroup
     */
    protected function getUserGroupWithAddDelete($id, array $withAdd = [], array $withRemove = [])
    {
        $userGroup = $this->getUserGroup($id);

        if (count($withAdd) > 0) {
            $userGroup->expects($this->exactly(count($withAdd)))
                ->method('addObject')
                ->withConsecutive(...$withAdd);
        }

        if (count($withRemove) > 0) {
            $userGroup->expects($this->exactly(count($withRemove)))
                ->method('removeObject')
                ->withConsecutive(...$withRemove);
        }

        return $userGroup;
    }

    /**
     * @param array $addIds
     * @param array $removeIds
     * @param array $with
     * @param array $additional
     *
     * @return array
     */
    private function getUserGroupArray(array $addIds, array $removeIds = [], array $with = [], array $additional = [])
    {
        $groups = [];

        $both = array_intersect($addIds, $removeIds);
        $withRemove = array_map(
            function ($element) {
                return array_slice($element, 0, 2);
            },
            $with
        );

        foreach ($both as $id) {
            $groups[$id] = $this->getUserGroupWithAddDelete($id, $with, $withRemove);
        }

        $add = array_diff($addIds, $both);

        foreach ($add as $id) {
            $groups[$id] = $this->getUserGroupWithAddDelete($id, $with, []);
        }

        $remove = array_diff($removeIds, $both);

        foreach ($remove as $id) {
            $groups[$id] = $this->getUserGroupWithAddDelete($id, [], $withRemove);
        }

        foreach ($additional as $id) {
            $group = $this->getUserGroup($id);
            $group->expects($this->never())
                ->method('addObject');

            $groups[$id] = $group;
        }

        return $groups;
    }

    /**
     * @param string $type
     * @param string $id
     * @param array  $with
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|\UserAccessManager\UserGroup\UserGroup
     */
    protected function getDynamicUserGroupWithAdd(
        $type,
        $id,
        array $with
    ) {
        $dynamicUserGroup = parent::getDynamicUserGroup(
            $type,
            $id
        );

        $dynamicUserGroup->expects($this->once())
            ->method('addObject')
            ->with(...$with);

        return $dynamicUserGroup;
    }

    /**
     * @group  unit
     * @covers ::__construct()
     */
    public function testCanCreateInstance()
    {
        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getObjectHandler(),
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        self::assertInstanceOf(AdminObjectController::class, $adminObjectController);
    }

    /**
     * @group  unit
     * @covers ::setObjectInformation()
     *
     * @return AdminObjectController
     */
    public function testSetObjectInformation()
    {
        $fullGroups = [
            1 => $this->getUserGroup(1),
            2 => $this->getUserGroup(2)
        ];

        $filteredGroups = [
            1 => $this->getUserGroup(1)
        ];

        $accessHandler = $this->getAccessHandler();

        $accessHandler->expects($this->once())
            ->method('getUserGroupsForObject')
            ->with('objectType', 'objectId', true)
            ->will($this->returnValue($fullGroups));

        $accessHandler->expects($this->once())
            ->method('getFilteredUserGroupsForObject')
            ->with('objectType', 'objectId', true)
            ->will($this->returnValue($filteredGroups));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getObjectHandler(),
            $accessHandler,
            $this->getUserGroupFactory()
        );

        $userGroups = [
            3 => $this->getUserGroup(3),
            4 => $this->getUserGroup(4)
        ];

        self::callMethod($adminObjectController, 'setObjectInformation', ['objectType', 'objectId', $userGroups]);

        self::assertAttributeEquals('objectType', 'objectType', $adminObjectController);
        self::assertAttributeEquals('objectId', 'objectId', $adminObjectController);
        self::assertAttributeEquals($userGroups, 'objectUserGroups', $adminObjectController);
        self::assertAttributeEquals(0, 'userGroupDiff', $adminObjectController);

        self::callMethod($adminObjectController, 'setObjectInformation', ['objectType', 'objectId']);

        self::assertAttributeEquals('objectType', 'objectType', $adminObjectController);
        self::assertAttributeEquals('objectId', 'objectId', $adminObjectController);
        self::assertAttributeEquals($filteredGroups, 'objectUserGroups', $adminObjectController);
        self::assertAttributeEquals(1, 'userGroupDiff', $adminObjectController);

        return $adminObjectController;
    }

    /**
     * @group   unit
     * @covers  ::getObjectType()
     * @depends testSetObjectInformation
     *
     * @param AdminObjectController $adminObjectController
     */
    public function testGetObjectType(AdminObjectController $adminObjectController)
    {
        self::assertEquals('objectType', $adminObjectController->getObjectType());
    }

    /**
     * @group   unit
     * @covers  ::getObjectId()
     * @depends testSetObjectInformation
     *
     * @param AdminObjectController $adminObjectController
     */
    public function testGetObjectId(AdminObjectController $adminObjectController)
    {
        self::assertEquals('objectId', $adminObjectController->getObjectId());
    }

    /**
     * @group   unit
     * @covers  ::getObjectUserGroups()
     * @depends testSetObjectInformation
     *
     * @param AdminObjectController $adminObjectController
     */
    public function testGetObjectUserGroups(AdminObjectController $adminObjectController)
    {
        self::assertEquals([1 => $this->getUserGroup(1)], $adminObjectController->getObjectUserGroups());
    }

    /**
     * @group   unit
     * @covers  ::getUserGroupDiff()
     * @depends testSetObjectInformation
     *
     * @param AdminObjectController $adminObjectController
     */
    public function testGetUserGroupDiff(AdminObjectController $adminObjectController)
    {
        self::assertEquals(1, $adminObjectController->getUserGroupDiff());
    }

    /**
     * @group  unit
     * @covers ::getUserGroups()
     */
    public function testGetUserGroups()
    {
        $userGroups = [
            1 => $this->getUserGroup(1),
            2 => $this->getUserGroup(2),
            3 => $this->getUserGroup(3)
        ];

        $accessHandler = $this->getAccessHandler();
        $accessHandler->expects($this->once())
            ->method('getFullUserGroups')
            ->will($this->returnValue($userGroups));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getObjectHandler(),
            $accessHandler,
            $this->getUserGroupFactory()
        );

        self::assertEquals($userGroups, $adminObjectController->getUserGroups());
    }

    /**
     * @group  unit
     * @covers ::getFilteredUserGroups()
     */
    public function testGetFilteredUserGroups()
    {
        $userGroups = [
            1 => $this->getUserGroup(1),
            2 => $this->getUserGroup(2)
        ];

        $accessHandler = $this->getAccessHandler();
        $accessHandler->expects($this->once())
            ->method('getFilteredUserGroups')
            ->will($this->returnValue($userGroups));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getObjectHandler(),
            $accessHandler,
            $this->getUserGroupFactory()
        );

        self::assertEquals($userGroups, $adminObjectController->getFilteredUserGroups());
    }

    /**
     * @group  unit
     * @covers ::isCurrentUserAdmin()
     */
    public function testIsCurrentUserAdmin()
    {
        $accessHandler = $this->getAccessHandler();

        $accessHandler->expects($this->exactly(2))
            ->method('userIsAdmin')
            ->with('objectId')
            ->will($this->onConsecutiveCalls(false, true));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getObjectHandler(),
            $accessHandler,
            $this->getUserGroupFactory()
        );

        self::assertFalse($adminObjectController->isCurrentUserAdmin());

        self::setValue($adminObjectController, 'objectType', ObjectHandler::GENERAL_USER_OBJECT_TYPE);
        self::assertFalse($adminObjectController->isCurrentUserAdmin());

        self::setValue($adminObjectController, 'objectId', 'objectId');
        self::assertFalse($adminObjectController->isCurrentUserAdmin());
        self::assertTrue($adminObjectController->isCurrentUserAdmin());
    }

    /**
     * @group  unit
     * @covers ::getRoleNames()
     */
    public function testGetRoleNames()
    {
        $roles = new \stdClass();
        $roles->role_names = 'roleNames';

        $wordpress = $this->getWordpress();
        $wordpress->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue($roles));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $wordpress,
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getObjectHandler(),
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        self::assertEquals('roleNames', $adminObjectController->getRoleNames());
    }

    /**
     * @group  unit
     * @covers ::getAllObjectTypes()
     */
    public function testGetAllObjectTypes()
    {
        $objectHandler = $this->getObjectHandler();

        $objectHandler->expects($this->once())
            ->method('getAllObjectTypes')
            ->will($this->returnValue([1 => 1, 2 => 2]));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $objectHandler,
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        self:self::assertEquals([1 => 1, 2 => 2], $adminObjectController->getAllObjectTypes());
    }

    /**
     * @group  unit
     * @covers ::checkUserAccess()
     */
    public function testCheckUserAccess()
    {
        $accessHandler = $this->getAccessHandler();

        $accessHandler->expects($this->exactly(2))
            ->method('checkUserAccess')
            ->will($this->onConsecutiveCalls(false, true));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getObjectHandler(),
            $accessHandler,
            $this->getUserGroupFactory()
        );

        self::assertFalse($adminObjectController->checkUserAccess());
        self::assertTrue($adminObjectController->checkUserAccess());
    }

    /**
     * @group  unit
     * @covers ::formatDate()
     */
    public function testFormatDate()
    {
        $wordpress = $this->getWordpress();

        $wordpress->expects($this->once())
            ->method('formatDate')
            ->with('date')
            ->will($this->returnValue('formattedDate'));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $wordpress,
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getObjectHandler(),
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        self::assertEquals('formattedDate', $adminObjectController->formatDate('date'));
    }

    /**
     * @group  unit
     * @covers ::formatDateForDatetimeInput()
     */
    public function testFormatDateForDatetimeInput()
    {
        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getObjectHandler(),
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        self::assertEquals(null, $adminObjectController->formatDateForDatetimeInput(null));
        self::assertEquals('1970-01-01T00:00:00', $adminObjectController->formatDateForDatetimeInput(0));
    }

    /**
     * @group  unit
     * @covers ::getDateFromTime()
     */
    public function testGetDateFromTime()
    {
        $wordpress = $this->getWordpress();
        $wordpress->expects($this->once())
            ->method('currentTime')
            ->with('timestamp')
            ->will($this->returnValue(100));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $wordpress,
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getObjectHandler(),
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        self::assertEquals(null, $adminObjectController->getDateFromTime(null));
        self::assertEquals(null, $adminObjectController->getDateFromTime(0));
        self::assertEquals('1970-01-01 00:01:41', $adminObjectController->getDateFromTime(1));
    }

    /**
     * @group  unit
     * @covers ::getRecursiveMembership()
     */
    public function testGetRecursiveMembership()
    {
        $roles = new \stdClass();
        $roles->role_names = [1 => 'roleOne'];

        $wordpress = $this->getWordpress();
        $wordpress->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue($roles));

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $taxonomy
         */
        $taxonomy = $this->getMockBuilder('\WP_Taxonomy')->getMock();
        $taxonomy->labels = new \stdClass();
        $taxonomy->labels->name = 'category';

        $wordpress->expects($this->exactly(2))
            ->method('getTaxonomy')
            ->with('termTaxonomy')
            ->will($this->onConsecutiveCalls(false, $taxonomy));

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $postType
         */
        $postType = $this->getMockBuilder('\WP_Post_Type')->getMock();
        $postType->labels = new \stdClass();
        $postType->labels->name = 'post';

        $wordpress->expects($this->once())
            ->method('getPostTypeObject')
            ->with('postType')
            ->will($this->returnValue($postType));


        $objectHandler = $this->getObjectHandler();
        $objectHandler->expects($this->exactly(10))
            ->method('getGeneralObjectType')
            ->withConsecutive(
                ['role'],
                ['user'],
                ['user'],
                ['term'],
                ['term'],
                ['term'],
                ['post'],
                ['post'],
                ['pluggableObject']
            )
            ->will($this->onConsecutiveCalls(
                ObjectHandler::GENERAL_ROLE_OBJECT_TYPE,
                ObjectHandler::GENERAL_USER_OBJECT_TYPE,
                ObjectHandler::GENERAL_USER_OBJECT_TYPE,
                ObjectHandler::GENERAL_TERM_OBJECT_TYPE,
                ObjectHandler::GENERAL_TERM_OBJECT_TYPE,
                ObjectHandler::GENERAL_TERM_OBJECT_TYPE,
                ObjectHandler::GENERAL_POST_OBJECT_TYPE,
                ObjectHandler::GENERAL_POST_OBJECT_TYPE,
                null,
                null
            ));

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $user
         */
        $user = $this->getMockBuilder('\WP_User')->getMock();
        $user->display_name = 'userTwo';

        $objectHandler->expects($this->exactly(2))
            ->method('getUser')
            ->withConsecutive([-1], [2])
            ->will($this->onConsecutiveCalls(false, $user));

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $term
         */
        $term = $this->getMockBuilder('\WP_Term')->getMock();
        $term->name = 'categoryThree';
        $term->taxonomy = 'termTaxonomy';

        $objectHandler->expects($this->exactly(3))
            ->method('getTerm')
            ->withConsecutive([-1], [1], [3])
            ->will($this->onConsecutiveCalls(false, $term, $term));

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $post
         */
        $post = $this->getMockBuilder('\WP_Post')->getMock();
        $post->post_title = 'postFour';
        $post->post_type = 'postType';

        $objectHandler->expects($this->exactly(2))
            ->method('getPost')
            ->withConsecutive([-1], [4])
            ->will($this->onConsecutiveCalls(false, $post));

        $objectHandler->expects($this->exactly(2))
            ->method('isPluggableObject')
            ->withConsecutive(['pluggableObject'], ['invalid'])
            ->will($this->onConsecutiveCalls(true, false));


        $pluggableObject = $this->createMock(PluggableObject::class);
        $pluggableObject->expects($this->once())
            ->method('getObjectType')
            ->will($this->returnValue('pluggableObjectTypeName'));
        $pluggableObject->expects($this->once())
            ->method('getObjectName')
            ->with(5)
            ->will($this->returnValue('pluggableObjectFive'));

        $objectHandler->expects($this->once())
            ->method('getPluggableObject')
            ->with('pluggableObject')
            ->will($this->returnValue($pluggableObject));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $wordpress,
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $objectHandler,
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        self::setValue($adminObjectController, 'objectId', 'objectId');
        self::setValue($adminObjectController, 'objectType', 'objectType');

        $userGroup = $this->getUserGroup(1);
        $userGroup->expects($this->once())
            ->method('getRecursiveMembershipForObject')
            ->with('objectType', 'objectId')
            ->willReturn([
                'role' => [1 => $this->getAssignmentInformation('role')],
                'user' => [
                    -1 => $this->getAssignmentInformation('user'),
                    2 => $this->getAssignmentInformation('user')
                ],
                'term' => [
                    -1 => $this->getAssignmentInformation('term'),
                    1 => $this->getAssignmentInformation('term'),
                    3 => $this->getAssignmentInformation('term')
                ],
                'post' => [
                    -1 => $this->getAssignmentInformation('post'),
                    4 => $this->getAssignmentInformation('post')
                ],
                'pluggableObject' => [5 => $this->getAssignmentInformation('pluggableObject')],
                'invalid' => [-1 => $this->getAssignmentInformation('invalid')]
            ]);

        $expected = [
            ObjectHandler::GENERAL_ROLE_OBJECT_TYPE => [1 => 'roleOne'],
            ObjectHandler::GENERAL_USER_OBJECT_TYPE => [-1 => -1, 2 => 'userTwo'],
            ObjectHandler::GENERAL_TERM_OBJECT_TYPE => [-1 => -1, 1 => 'categoryThree'],
            'category' => [3 => 'categoryThree'],
            ObjectHandler::GENERAL_POST_OBJECT_TYPE => [-1 => -1],
            'post' => [4 => 'postFour'],
            'pluggableObjectTypeName' => [5 => 'pluggableObjectFive']
        ];

        self::assertEquals($expected, $adminObjectController->getRecursiveMembership($userGroup));
    }

    /**
     * @group  unit
     * @covers ::checkRightsToEditContent()
     */
    public function testCheckRightsToEditContent()
    {
        $wordpress = $this->getWordpress();
        $wordpress->expects($this->exactly(3))
            ->method('wpDie')
            ->with(TXT_UAM_NO_RIGHTS_MESSAGE, TXT_UAM_NO_RIGHTS_TITLE, ['response' => 403]);

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $post
         */
        $post = $this->getMockBuilder('\WP_Post')->getMock();
        $post->ID = 1;
        $post->post_type = 'post';

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $noAccessPost
         */
        $noAccessPost = $this->getMockBuilder('\WP_Post')->getMock();
        $noAccessPost->ID = 2;
        $noAccessPost->post_type = 'post';

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $attachment
         */
        $attachment = $this->getMockBuilder('\WP_Post')->getMock();
        $attachment->ID = 3;
        $attachment->post_type = 'attachment';

        $objectHandler = $this->getObjectHandler();
        $objectHandler->expects($this->exactly(4))
            ->method('getPost')
            ->withConsecutive([-1], [1], [2], [3])
            ->will($this->onConsecutiveCalls(false, $post, $noAccessPost, $attachment));

        $accessHandler = $this->getAccessHandler();
        $accessHandler->expects($this->exactly(5))
            ->method('checkObjectAccess')
            ->withConsecutive(
                ['post', 1],
                ['post', 2],
                ['attachment', 3],
                [ObjectHandler::GENERAL_TERM_OBJECT_TYPE, 4],
                [ObjectHandler::GENERAL_TERM_OBJECT_TYPE, 5]
            )
            ->will($this->onConsecutiveCalls(true, false, true, false));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $wordpress,
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $objectHandler,
            $accessHandler,
            $this->getUserGroupFactory()
        );

        $adminObjectController->checkRightsToEditContent();

        $_GET['post'] = -1;
        $adminObjectController->checkRightsToEditContent();

        $_GET['post'] = 1;
        $adminObjectController->checkRightsToEditContent();

        $_GET['post'] = 2;
        $adminObjectController->checkRightsToEditContent();

        unset($_GET['post']);
        $_GET['attachment_id'] = 3;
        $adminObjectController->checkRightsToEditContent();

        unset($_GET['attachment_id']);
        $_GET['tag_ID'] = 4;
        $adminObjectController->checkRightsToEditContent();

        $_GET['tag_ID'] = 5;
        $adminObjectController->checkRightsToEditContent();
    }

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|ObjectHandler
     */
    private function getExtendedObjectHandler()
    {
        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $post
         */
        $post = $this->getMockBuilder('\WP_Post')->getMock();
        $post->ID = 1;
        $post->post_type = 'post';

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $revisionPost
         */
        $revisionPost = $this->getMockBuilder('\WP_Post')->getMock();
        $revisionPost->ID = 2;
        $revisionPost->post_type = 'revision';
        $revisionPost->post_parent = 1;

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $attachment
         */
        $attachment = $this->getMockBuilder('\WP_Post')->getMock();
        $attachment->ID = 3;
        $attachment->post_type = 'attachment';

        $objectHandler = $this->getObjectHandler();
        $objectHandler->expects($this->any())
            ->method('getPost')
            ->will($this->returnCallback(function ($postId) use ($post, $revisionPost, $attachment) {
                if ($postId === 1) {
                    return $post;
                } elseif ($postId === 2) {
                    return $revisionPost;
                } elseif ($postId === 3) {
                    return $attachment;
                }

                return false;
            }));

        $objectHandler->expects($this->any())
            ->method('getTerm')
            ->will($this->returnCallback(function ($termId) {
                if ($termId === 0) {
                    return false;
                }

                /**
                 * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $term
                 */
                $term = $this->getMockBuilder('\WP_Term')->getMock();
                $term->term_id = $termId;
                $term->taxonomy = 'taxonomy_'.$termId;

                return $term;
            }));

        return $objectHandler;
    }

    /**
     * @group  unit
     * @covers ::saveObjectData()
     * @covers ::getAddRemoveGroups()
     * @covers ::setUserGroups()
     * @covers ::setDynamicGroups()
     * @covers ::setDefaultGroups()
     * @covers ::savePostData()
     * @covers ::saveAttachmentData()
     * @covers ::saveAjaxAttachmentData()
     * @covers ::saveUserData()
     * @covers ::saveTermData()
     * @covers ::savePluggableObjectData()
     * @covers ::getDateParameter()
     */
    public function testSaveObjectData()
    {
        $wordpress = $this->getWordpress();
        $wordpress->expects($this->exactly(2))
            ->method('currentTime')
            ->with('timestamp')
            ->will($this->returnValue(100));

        $config = $this->getMainConfig();
        $config->expects($this->exactly(3))
            ->method('authorsCanAddPostsToGroups')
            ->will($this->onConsecutiveCalls(false, true, true));

        $objectHandler = $this->getExtendedObjectHandler();

        $accessHandler = $this->getAccessHandler();
        $accessHandler->expects($this->exactly(22))
            ->method('checkUserAccess')
            ->with('manage_user_groups')
            ->will($this->onConsecutiveCalls(
                false,
                false,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                true,
                false,
                false
            ));

        $accessHandler->expects($this->exactly(10))
            ->method('getFilteredUserGroupsForObject')
            ->withConsecutive(
                ['post', 1],
                ['post', 1],
                ['post', 1],
                ['attachment', 3],
                ['attachment', 3],
                [ObjectHandler::GENERAL_POST_OBJECT_TYPE, 3],
                [ObjectHandler::GENERAL_USER_OBJECT_TYPE, 1],
                [ObjectHandler::GENERAL_TERM_OBJECT_TYPE, 0],
                ['taxonomy_1', 1],
                ['objectType', 'objectId']
            )
            ->will($this->onConsecutiveCalls(
                $this->getUserGroupArray([1, 2, 3]),
                $this->getUserGroupArray([1, 2, 4]),
                $this->getUserGroupArray([2, 3, 4]),
                $this->getUserGroupArray([1, 3, 4]),
                $this->getUserGroupArray([1, 2]),
                $this->getUserGroupArray([1, 3, 4]),
                $this->getUserGroupArray([3, 4]),
                $this->getUserGroupArray([1, 4]),
                $this->getUserGroupArray([1, 4]),
                $this->getUserGroupArray([2, 3])
            ));

        $fullGroupOne = $this->getUserGroupWithAddDelete(
            1000,
            [['objectType', 'objectId', '1970-01-01 00:01:41', '1970-01-01 00:01:42']]
        );

        $fullGroupOne->expects($this->once())
            ->method('isDefaultGroupForObjectType')
            ->with('objectType', null, null)
            ->will($this->returnCallback(function ($objectType, &$fromTime, &$toTime) {
                $fromTime = 1;
                $toTime = 2;
                return true;
            }));

        $fullGroupTwo = $this->getUserGroupWithAddDelete(1001);

        $fullGroupTwo->expects($this->once())
            ->method('isDefaultGroupForObjectType')
            ->with('objectType', 1, 2)
            ->will($this->returnValue(false));

        $accessHandler->expects($this->once())
            ->method('getFullUserGroups')
            ->will($this->returnValue([$fullGroupOne, $fullGroupTwo]));

        $accessHandler->expects($this->exactly(10))
            ->method('getFilteredUserGroups')
            ->will($this->onConsecutiveCalls(
                $this->getUserGroupArray([1, 3], [1, 2, 3], [['post', 1, '1', 'toDate']], [100, 101]),
                $this->getUserGroupArray([2, 4], [1, 2, 4], [['post', 1, null, null]]),
                $this->getUserGroupArray([1, 2], [2, 3, 4], [['post', 1, null, '234']]),
                $this->getUserGroupArray([3, 4], [1, 3, 4], [['attachment', 3, null, null]]),
                $this->getUserGroupArray([], [2, 3], [['attachment', 3, null, null]]),
                $this->getUserGroupArray([3, 4], [1, 3, 4], [[ObjectHandler::GENERAL_POST_OBJECT_TYPE, 3, null, null]]),
                $this->getUserGroupArray([2], [3, 4], [[ObjectHandler::GENERAL_USER_OBJECT_TYPE, 1, null, null]]),
                $this->getUserGroupArray([3], [1, 4], [[ObjectHandler::GENERAL_TERM_OBJECT_TYPE, 0, null, null]]),
                $this->getUserGroupArray([3], [1, 4], [['taxonomy_1', 1, null, null]]),
                $this->getUserGroupArray([4], [2, 3], [['objectType', 'objectId', null, null]])
            ));

        $accessHandler->expects($this->exactly(10))
            ->method('unsetUserGroupsForObject');

        $userGroupFactory = $this->getUserGroupFactory();

        $userGroupFactory->expects($this->exactly(2))
            ->method('createDynamicUserGroup')
            ->withConsecutive(
                [DynamicUserGroup::USER_TYPE, '1'],
                [DynamicUserGroup::ROLE_TYPE, 'admin']
            )->will($this->onConsecutiveCalls(
                $this->getDynamicUserGroupWithAdd(DynamicUserGroup::USER_TYPE, '1', ['post', 1, 'fromDate', 'toDate']),
                $this->getDynamicUserGroupWithAdd(DynamicUserGroup::ROLE_TYPE, 'admin', ['post', 1, null, null])
            ));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $wordpress,
            $config,
            $this->getDatabase(),
            $this->getCache(),
            $objectHandler,
            $accessHandler,
            $userGroupFactory
        );

        $_POST[AdminObjectController::UPDATE_GROUPS_FORM_NAME] = 1;
        $adminObjectController->savePostData(['ID' => 1]);

        $_POST[AdminObjectController::DEFAULT_DYNAMIC_GROUPS_FORM_NAME] = [
            DynamicUserGroup::USER_TYPE.'|1' => [
                'id' => DynamicUserGroup::USER_TYPE.'|1',
                'fromDate' => 'fromDate',
                'toDate' => 'toDate'
            ],
            DynamicUserGroup::ROLE_TYPE.'|admin' => ['id' => DynamicUserGroup::ROLE_TYPE.'|admin'],
            'A|B' => ['id' => 'B|A'],
        ];
        $_POST[AdminObjectController::DEFAULT_GROUPS_FORM_NAME] = [
            1 => ['id' => 1, 'fromDate' => 1, 'toDate' => 'toDate'],
            3 => ['id' => 3, 'fromDate' => 1, 'toDate' => 'toDate'],
            100 => [],
            101 => ['id' => 100]
        ];
        $adminObjectController->savePostData(['ID' => 1]);

        unset($_POST[AdminObjectController::DEFAULT_DYNAMIC_GROUPS_FORM_NAME]);
        $_POST[AdminObjectController::DEFAULT_GROUPS_FORM_NAME] = [
            2 => ['id' => 2],
            4 => ['id' => 4]
        ];
        $adminObjectController->savePostData(['ID' => 2]);

        $_POST[AdminObjectController::DEFAULT_GROUPS_FORM_NAME] = [
            1 => ['id' => 1, 'formDate' => '', 'toDate' => 234],
            2 => ['id' => 2, 'formDate' => '', 'toDate' => 234]
        ];
        $adminObjectController->savePostData(2);

        $_POST[AdminObjectController::DEFAULT_GROUPS_FORM_NAME] = [
            3 => ['id' => 3],
            4 => ['id' => 4]
        ];
        $adminObjectController->saveAttachmentData(['ID' => 3]);

        $_POST['uam_bulk_type'] = AdminObjectController::BULK_REMOVE;
        $_POST[AdminObjectController::DEFAULT_GROUPS_FORM_NAME] = [
            2 => ['id' => 2],
            3 => ['id' => 3]
        ];
        $adminObjectController->saveAttachmentData(['ID' => 3]);

        $_POST = [
            AdminObjectController::UPDATE_GROUPS_FORM_NAME => 1,
            'id' => 3,
            AdminObjectController::DEFAULT_GROUPS_FORM_NAME => [
                3 => ['id' => 3],
                4 => ['id' => 4]
            ]
        ];
        $adminObjectController->saveAjaxAttachmentData();

        $_POST[AdminObjectController::DEFAULT_GROUPS_FORM_NAME] = [
            2 => ['id' => 2]
        ];
        $adminObjectController->saveUserData(1);

        $_POST[AdminObjectController::DEFAULT_GROUPS_FORM_NAME] = [
            3 => ['id' => 3]
        ];
        $adminObjectController->saveTermData(0);
        $adminObjectController->saveTermData(1);

        $_POST[AdminObjectController::DEFAULT_GROUPS_FORM_NAME] = [
            4 => ['id' => 4]
        ];
        $adminObjectController->savePluggableObjectData('objectType', 'objectId');

        $_POST = [];
        $adminObjectController->savePluggableObjectData('objectType', 'objectId');
    }

    /**
     * @group  unit
     * @covers ::removeObjectData()
     * @covers ::removePostData()
     * @covers ::removeUserData()
     * @covers ::removeTermData()
     * @covers ::removePluggableObjectData()
     */
    public function testRemoveObjectData()
    {
        $database = $this->getDatabase();
        $database->expects($this->exactly(4))
            ->method('getUserGroupToObjectTable')
            ->will($this->returnValue('userGroupToObjectTable'));

        $database->expects($this->exactly(4))
            ->method('delete')
            ->withConsecutive(
                [
                    'userGroupToObjectTable',
                    ['object_id' => 1, 'object_type' => 'post'],
                    ['%d', '%s']
                ],
                [
                    'userGroupToObjectTable',
                    ['object_id' => 2, 'object_type' => ObjectHandler::GENERAL_USER_OBJECT_TYPE],
                    ['%d', '%s']
                ],
                [
                    'userGroupToObjectTable',
                    ['object_id' => 3, 'object_type' => ObjectHandler::GENERAL_TERM_OBJECT_TYPE],
                    ['%d', '%s']
                ],
                [
                    'userGroupToObjectTable',
                    ['object_id' => 'objectId', 'object_type' => 'objectType'],
                    ['%d', '%s']
                ]
            );

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $database,
            $this->getCache(),
            $this->getExtendedObjectHandler(),
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        $adminObjectController->removePostData(1);
        $adminObjectController->removeUserData(2);
        $adminObjectController->removeTermData(3);
        $adminObjectController->removePluggableObjectData('objectType', 'objectId');
    }

    /**
     * @group  unit
     * @covers ::addPostColumnsHeader()
     * @covers ::addUserColumnsHeader()
     * @covers ::addTermColumnsHeader()
     */
    public function testAddColumnsHeader()
    {
        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getObjectHandler(),
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        self::assertEquals(
            ['a' => 'a', AdminObjectController::COLUMN_NAME => TXT_UAM_COLUMN_ACCESS],
            $adminObjectController->addPostColumnsHeader(['a' => 'a'])
        );
        self::assertEquals(
            ['b' => 'b', AdminObjectController::COLUMN_NAME => TXT_UAM_COLUMN_USER_GROUPS],
            $adminObjectController->addUserColumnsHeader(['b' => 'b'])
        );
        self::assertEquals(
            ['c' => 'c', AdminObjectController::COLUMN_NAME => TXT_UAM_COLUMN_ACCESS],
            $adminObjectController->addTermColumnsHeader(['c' => 'c'])
        );
    }

    /**
     * @group  unit
     * @covers ::addPostColumn()
     * @covers ::addUserColumn()
     * @covers ::addTermColumn()
     * @covers ::getPluggableColumn()
     */
    public function testAddColumn()
    {
        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getExtendedObjectHandler(),
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        $adminObjectController->addPostColumn(AdminObjectController::COLUMN_NAME, 1);
        $adminObjectController->addUserColumn('return', AdminObjectController::COLUMN_NAME, 1);
        $adminObjectController->addTermColumn('content', AdminObjectController::COLUMN_NAME, 1);
        $adminObjectController->getPluggableColumn('objectType', 'objectId');
    }

    /**
     * @group  unit
     * @covers ::addPostColumn()
     * @covers ::addUserColumn()
     * @covers ::addTermColumn()
     * @covers ::getPluggableColumn()
     * @covers ::editPostContent()
     * @covers ::addBulkAction()
     * @covers ::showMediaFile()
     * @covers ::showUserProfile()
     * @covers ::showTermEditForm()
     * @covers ::showPluggableGroupSelectionForm()
     * @covers ::getGroupsFormName()
     */
    public function testEditForm()
    {
        /**
         * @var Directory $rootDir
         */
        $rootDir = $this->root->get('/');
        $rootDir->add('src', new Directory([
            'View'  => new Directory([
                'ObjectColumn.php' => new File('<?php echo \'ObjectColumn\';'),
                'UserColumn.php' => new File('<?php echo \'UserColumn\';'),
                'PostEditForm.php' => new File('<?php echo \'PostEditForm\';'),
                'BulkEditForm.php' => new File('<?php echo \'BulkEditForm\';'),
                'MediaAjaxEditForm.php' => new File('<?php echo \'MediaAjaxEditForm\';'),
                'UserProfileEditForm.php' => new File('<?php echo \'UserProfileEditForm\';'),
                'TermEditForm.php' => new File('<?php echo \'TermEditForm\';'),
                'GroupSelectionForm.php' => new File('<?php echo \'GroupSelectionForm\';')
            ])
        ]));

        $php = $this->getPhp();

        $config = $this->getMainConfig();
        $config->expects($this->exactly(17))
            ->method('getRealPath')
            ->will($this->returnValue('vfs:/'));

        $accessHandler = $this->getAccessHandler();

        $accessHandler->expects($this->exactly(11))
            ->method('getUserGroupsForObject')
            ->withConsecutive(
                ['post', 1],
                [ObjectHandler::GENERAL_USER_OBJECT_TYPE, 1],
                [ObjectHandler::GENERAL_TERM_OBJECT_TYPE, 0],
                ['taxonomy_1', 1],
                ['objectType', 'objectId'],
                ['post', 1],
                ['post', 1],
                ['attachment', 3],
                [ObjectHandler::GENERAL_USER_OBJECT_TYPE, 4],
                ['category', 5],
                ['objectType', 'objectId']
            )
            ->will($this->returnValue([]));

        $accessHandler->expects($this->exactly(11))
            ->method('getFilteredUserGroupsForObject')
            ->withConsecutive(
                ['post', 1],
                [ObjectHandler::GENERAL_USER_OBJECT_TYPE, 1],
                [ObjectHandler::GENERAL_TERM_OBJECT_TYPE, 0],
                ['taxonomy_1', 1],
                ['objectType', 'objectId'],
                ['post', 1],
                ['post', 1],
                ['attachment', 3],
                [ObjectHandler::GENERAL_USER_OBJECT_TYPE, 4],
                ['category', 5],
                ['objectType', 'objectId']
            )
            ->will($this->returnValue([]));

        $adminObjectController = new AdminObjectController(
            $php,
            $this->getWordpress(),
            $config,
            $this->getDatabase(),
            $this->getCache(),
            $this->getExtendedObjectHandler(),
            $accessHandler,
            $this->getUserGroupFactory()
        );

        $php->expects($this->exactly(17))
            ->method('includeFile')
            ->withConsecutive(
                [$adminObjectController, 'vfs://src/View/ObjectColumn.php'],
                [$adminObjectController, 'vfs://src/View/UserColumn.php'],
                [$adminObjectController, 'vfs://src/View/ObjectColumn.php'],
                [$adminObjectController, 'vfs://src/View/ObjectColumn.php'],
                [$adminObjectController, 'vfs://src/View/ObjectColumn.php'],
                [$adminObjectController, 'vfs://src/View/PostEditForm.php'],
                [$adminObjectController, 'vfs://src/View/PostEditForm.php'],
                [$adminObjectController, 'vfs://src/View/BulkEditForm.php'],
                [$adminObjectController, 'vfs://src/View/MediaAjaxEditForm.php'],
                [$adminObjectController, 'vfs://src/View/MediaAjaxEditForm.php'],
                [$adminObjectController, 'vfs://src/View/MediaAjaxEditForm.php'],
                [$adminObjectController, 'vfs://src/View/UserProfileEditForm.php'],
                [$adminObjectController, 'vfs://src/View/UserProfileEditForm.php'],
                [$adminObjectController, 'vfs://src/View/TermEditForm.php'],
                [$adminObjectController, 'vfs://src/View/TermEditForm.php'],
                [$adminObjectController, 'vfs://src/View/GroupSelectionForm.php'],
                [$adminObjectController, 'vfs://src/View/GroupSelectionForm.php']
            )
            ->will($this->returnCallback(function (AdminObjectController $controller, $file) {
                echo '!'.get_class($controller).'|'.$file.'|'.$controller->getGroupsFormName().'!';
            }));

        $adminObjectController->addPostColumn('invalid', 1);
        $adminObjectController->addPostColumn('invalid', 1);
        $adminObjectController->addPostColumn(AdminObjectController::COLUMN_NAME, 1);
        self::assertAttributeEquals('post', 'objectType', $adminObjectController);
        self::assertAttributeEquals(1, 'objectId', $adminObjectController);
        $expectedOutput = '!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/ObjectColumn.php|uam_user_groups!';

        self::assertEquals('return', $adminObjectController->addUserColumn('return', 'invalid', 1));
        self::assertEquals('return', $adminObjectController->addUserColumn('return', 'invalid', 1));

        $expected = 'return!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/UserColumn.php|uam_user_groups!';

        self::assertEquals(
            $expected,
            $adminObjectController->addUserColumn('return', AdminObjectController::COLUMN_NAME, 1)
        );
        self::assertAttributeEquals(ObjectHandler::GENERAL_USER_OBJECT_TYPE, 'objectType', $adminObjectController);
        self::assertAttributeEquals(1, 'objectId', $adminObjectController);

        self::assertEquals('content', $adminObjectController->addTermColumn('content', 'invalid', 1));
        self::assertEquals('content', $adminObjectController->addTermColumn('content', 'invalid', 1));

        $expected = 'content!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/ObjectColumn.php|uam_user_groups!';

        self::assertEquals(
            $expected,
            $adminObjectController->addTermColumn('content', AdminObjectController::COLUMN_NAME, 0)
        );
        self::assertAttributeEquals(ObjectHandler::GENERAL_TERM_OBJECT_TYPE, 'objectType', $adminObjectController);
        self::assertAttributeEquals(0, 'objectId', $adminObjectController);

        self::assertEquals(
            $expected,
            $adminObjectController->addTermColumn('content', AdminObjectController::COLUMN_NAME, 1)
        );
        self::assertAttributeEquals('taxonomy_1', 'objectType', $adminObjectController);
        self::assertAttributeEquals(1, 'objectId', $adminObjectController);

        $expected = '!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/ObjectColumn.php|uam_user_groups!';

        self::assertEquals(
            $expected,
            $adminObjectController->getPluggableColumn('objectType', 'objectId')
        );
        self::assertAttributeEquals('objectType', 'objectType', $adminObjectController);
        self::assertAttributeEquals('objectId', 'objectId', $adminObjectController);

        self::setValue($adminObjectController, 'objectType', null);
        self::setValue($adminObjectController, 'objectId', null);

        $adminObjectController->editPostContent(null);
        self::assertAttributeEquals(null, 'objectType', $adminObjectController);
        self::assertAttributeEquals(null, 'objectId', $adminObjectController);
        $expectedOutput .= '!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/PostEditForm.php|uam_user_groups!';

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $post
         */
        $post = $this->getMockBuilder('\WP_Post')->getMock();
        $post->ID = 1;
        $post->post_type = 'post';

        $adminObjectController->editPostContent($post);
        self::assertAttributeEquals('post', 'objectType', $adminObjectController);
        self::assertAttributeEquals(1, 'objectId', $adminObjectController);
        $expectedOutput .= '!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/PostEditForm.php|uam_user_groups!';
        self::setValue($adminObjectController, 'objectType', null);
        self::setValue($adminObjectController, 'objectId', null);

        $adminObjectController->addBulkAction('invalid');
        $expectedOutput .= '';

        $adminObjectController->addBulkAction('invalid');
        $expectedOutput .= '';

        $adminObjectController->addBulkAction(AdminObjectController::COLUMN_NAME);
        $expectedOutput .= '!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/BulkEditForm.php|uam_user_groups!';


        $return = $adminObjectController->showMediaFile(['a' => 'b']);
        self::assertAttributeEquals(null, 'objectType', $adminObjectController);
        self::assertAttributeEquals(null, 'objectId', $adminObjectController);
        self::assertEquals(
            [
                'a' => 'b',
                'uam_user_groups' => [
                    'label' => 'Set up user groups|user-access-manager',
                    'input' => 'editFrom',
                    'editFrom' => '!UserAccessManager\Controller\AdminObjectController|'
                        .'vfs://src/View/MediaAjaxEditForm.php|uam_user_groups!'
                ]
            ],
            $return
        );

        $return = $adminObjectController->showMediaFile(['a' => 'b'], $post);
        self::assertAttributeEquals('post', 'objectType', $adminObjectController);
        self::assertAttributeEquals(1, 'objectId', $adminObjectController);
        self::assertEquals(
            [
                'a' => 'b',
                'uam_user_groups' => [
                    'label' => 'Set up user groups|user-access-manager',
                    'input' => 'editFrom',
                    'editFrom' => '!UserAccessManager\Controller\AdminObjectController|'
                        .'vfs://src/View/MediaAjaxEditForm.php|uam_user_groups!'
                ]
            ],
            $return
        );

        self::setValue($adminObjectController, 'objectType', null);
        self::setValue($adminObjectController, 'objectId', null);

        $_GET['attachment_id'] = 3;
        $return = $adminObjectController->showMediaFile(['a' => 'b'], $post);
        self::assertAttributeEquals('attachment', 'objectType', $adminObjectController);
        self::assertAttributeEquals(3, 'objectId', $adminObjectController);
        self::assertEquals(
            [
                'a' => 'b',
                'uam_user_groups' => [
                    'label' => 'Set up user groups|user-access-manager',
                    'input' => 'editFrom',
                    'editFrom' => '!UserAccessManager\Controller\AdminObjectController|'
                        .'vfs://src/View/MediaAjaxEditForm.php|uam_user_groups!'
                ]
            ],
            $return
        );

        self::setValue($adminObjectController, 'objectType', null);
        self::setValue($adminObjectController, 'objectId', null);

        $adminObjectController->showUserProfile();
        self::assertAttributeEquals(ObjectHandler::GENERAL_USER_OBJECT_TYPE, 'objectType', $adminObjectController);
        self::assertAttributeEquals(null, 'objectId', $adminObjectController);
        $expectedOutput .= '!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/UserProfileEditForm.php|uam_user_groups!';

        $_GET['user_id'] = 4;
        $adminObjectController->showUserProfile();
        self::assertAttributeEquals(ObjectHandler::GENERAL_USER_OBJECT_TYPE, 'objectType', $adminObjectController);
        self::assertAttributeEquals(4, 'objectId', $adminObjectController);
        $expectedOutput .= '!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/UserProfileEditForm.php|uam_user_groups!';
        self::setValue($adminObjectController, 'objectType', null);
        self::setValue($adminObjectController, 'objectId', null);
        unset($_GET['user_id']);

        $adminObjectController->showTermEditForm('category');
        self::assertAttributeEquals('category', 'objectType', $adminObjectController);
        self::assertAttributeEquals(null, 'objectId', $adminObjectController);
        $expectedOutput .= '!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/TermEditForm.php|uam_user_groups!';

        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass|\WP_Term $term
         */
        $term = $this->getMockBuilder('\WP_Term')->getMock();
        $term->term_id = 5;
        $term->taxonomy = 'category';
        $adminObjectController->showTermEditForm($term);

        self::assertAttributeEquals('category', 'objectType', $adminObjectController);
        self::assertAttributeEquals(5, 'objectId', $adminObjectController);
        $expectedOutput .= '!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/TermEditForm.php|uam_user_groups!';
        self::setValue($adminObjectController, 'objectType', null);
        self::setValue($adminObjectController, 'objectId', null);

        $return = $adminObjectController->showPluggableGroupSelectionForm('objectType', 'objectId', 'otherForm');
        self::assertEquals(
            '!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/GroupSelectionForm.php|otherForm!',
            $return
        );
        self::assertAttributeEquals('objectType', 'objectType', $adminObjectController);
        self::assertAttributeEquals('objectId', 'objectId', $adminObjectController);
        self::assertEquals(
            AdminObjectController::DEFAULT_GROUPS_FORM_NAME,
            $adminObjectController->getGroupsFormName()
        );
        $expectedOutput .= '';

        $userGroups = [
            3 => $this->getUserGroup(3),
            4 => $this->getUserGroup(4)
        ];

        $return = $adminObjectController->showPluggableGroupSelectionForm('objectType', 'objectId', null, $userGroups);
        self::assertEquals(
            '!UserAccessManager\Controller\AdminObjectController|'
            .'vfs://src/View/GroupSelectionForm.php|uam_user_groups!',
            $return
        );
        self::assertAttributeEquals('objectType', 'objectType', $adminObjectController);
        self::assertAttributeEquals('objectId', 'objectId', $adminObjectController);
        self::assertAttributeEquals($userGroups, 'objectUserGroups', $adminObjectController);
        self::assertEquals(
            AdminObjectController::DEFAULT_GROUPS_FORM_NAME,
            $adminObjectController->getGroupsFormName()
        );
        $expectedOutput .= '';

        self::expectOutputString($expectedOutput);
    }

    /**
     * @group  unit
     * @covers ::invalidateTermCache()
     * @covers ::invalidatePostCache()
     */
    public function testInvalidateCache()
    {
        $cache = $this->getCache();
        $cache->expects($this->exactly(6))
            ->method('invalidate')
            ->withConsecutive(
                [ObjectHandler::POST_TERM_MAP_CACHE_KEY],
                [ObjectHandler::TERM_POST_MAP_CACHE_KEY],
                [ObjectHandler::TERM_TREE_MAP_CACHE_KEY],
                [ObjectHandler::TERM_POST_MAP_CACHE_KEY],
                [ObjectHandler::POST_TERM_MAP_CACHE_KEY],
                [ObjectHandler::POST_TREE_MAP_CACHE_KEY]
            );

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $cache,
            $this->getExtendedObjectHandler(),
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        $adminObjectController->invalidateTermCache();
        $adminObjectController->invalidatePostCache();
    }

    /**
     * @param int    $id
     * @param string $displayName
     * @param string $userLogin
     *
     * @return \PHPUnit_Framework_MockObject_MockObject|\stdClass
     */
    private function getUser($id, $displayName, $userLogin)
    {
        /**
         * @var \PHPUnit_Framework_MockObject_MockObject|\stdClass $user
         */
        $user = $this->getMockBuilder('\WP_User')->getMock();
        $user->ID = $id;
        $user->display_name = $displayName;
        $user->user_login = $userLogin;

        return $user;
    }

    /**
     * @group  unit
     * @covers ::isNewObject()
     */
    public function testIsNewObject()
    {
        $objectHandler = $this->getObjectHandler();
        $objectHandler->expects($this->exactly(4))
            ->method('getGeneralObjectType')
            ->withConsecutive(
                ['objectTypeValue'],
                ['objectTypeValue'],
                ['otherObjectTypeValue'],
                ['otherObjectTypeValue']
            )
            ->will($this->onConsecutiveCalls(
                'generalObjectType',
                'generalObjectType',
                ObjectHandler::GENERAL_POST_OBJECT_TYPE,
                ObjectHandler::GENERAL_POST_OBJECT_TYPE
            ));

        $adminObjectController = new AdminObjectController(
            $this->getPhp(),
            $this->getWordpress(),
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $objectHandler,
            $this->getAccessHandler(),
            $this->getUserGroupFactory()
        );

        self::setValue($adminObjectController, 'objectType', null);
        self::setValue($adminObjectController, 'objectId', null);
        self::assertFalse($adminObjectController->isNewObject());

        self::setValue($adminObjectController, 'objectType', 'objectTypeValue');
        self::setValue($adminObjectController, 'objectId', null);
        self::assertTrue($adminObjectController->isNewObject());

        self::setValue($adminObjectController, 'objectType', 'objectTypeValue');
        self::setValue($adminObjectController, 'objectId', 1);
        self::assertFalse($adminObjectController->isNewObject());

        $_GET['action'] = 'edit';
        self::setValue($adminObjectController, 'objectType', 'otherObjectTypeValue');
        self::assertFalse($adminObjectController->isNewObject());

        $_GET['action'] = 'new';
        self::setValue($adminObjectController, 'objectType', 'otherObjectTypeValue');
        self::assertTrue($adminObjectController->isNewObject());
    }

    /**
     * @group  unit
     * @covers ::getDynamicGroupsForAjax()
     */
    public function testGetDynamicGroupsForAjax()
    {
        $php = $this->getPhp();
        $php->expects($this->exactly(2))
            ->method('callExit');

        $wordpress = $this->getWordpress();

        $wordpress->expects($this->once())
            ->method('getUsers')
            ->with([
                'search' => '*sea*',
                'fields' => ['ID', 'display_name', 'user_login', 'user_email']
            ])
            ->will($this->returnValue([
                $this->getUser(1, 'firstUser', 'firstUserLogin'),
                $this->getUser(2, 'secondUser', 'secondUserLogin')
            ]));

        $roles = new \stdClass();
        $roles->roles = [
            'admin' => ['name' => 'Administrator'],
            'editor' => ['name' => 'Editor'],
            'search' => ['name' => 'Search']
        ];

        $wordpress->expects($this->once())
            ->method('getRoles')
            ->will($this->returnValue($roles));

        $_GET['q'] = 'firstSearch, sea';

        $accessHandler = $this->getAccessHandler();

        $accessHandler->expects($this->exactly(2))
            ->method('checkUserAccess')
            ->with(AccessHandler::MANAGE_USER_GROUPS_CAPABILITY)
            ->will($this->onConsecutiveCalls(true, false));

        $adminObjectController = new AdminObjectController(
            $php,
            $wordpress,
            $this->getMainConfig(),
            $this->getDatabase(),
            $this->getCache(),
            $this->getExtendedObjectHandler(),
            $accessHandler,
            $this->getUserGroupFactory()
        );

        $adminObjectController->getDynamicGroupsForAjax();
        $adminObjectController->getDynamicGroupsForAjax();

        self::expectOutputString(
            '['
            .'{"id":1,"name":"User|user-access-manager: firstUser (firstUserLogin)","type":"user"},'
            .'{"id":2,"name":"User|user-access-manager: secondUser (secondUserLogin)","type":"user"},'
            .'{"id":"search","name":"Role|user-access-manager: Search","type":"role"}'
            .'][]'
        );
    }
}
