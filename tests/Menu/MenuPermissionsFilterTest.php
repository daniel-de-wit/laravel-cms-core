<?php
namespace Czim\CmsCore\Test\Menu;

use Czim\CmsCore\Contracts\Auth\UserInterface;
use Czim\CmsCore\Contracts\Support\Data\MenuLayoutDataInterface;
use Czim\CmsCore\Contracts\Support\Data\MenuPermissionsIndexDataInterface;
use Czim\CmsCore\Menu\MenuPermissionsFilter;
use Czim\CmsCore\Support\Data\Menu\LayoutData;
use Czim\CmsCore\Support\Data\Menu\PermissionsIndexData;
use Czim\CmsCore\Support\Data\MenuPresence;
use Czim\CmsCore\Support\Enums\MenuPresenceType;
use Czim\CmsCore\Test\CmsBootTestCase;

class MenuPermissionsFilterTest extends CmsBootTestCase
{

    // ------------------------------------------------------------------------------
    //      Index Building
    // ------------------------------------------------------------------------------

    /**
     * @test
     */
    function it_builds_a_permissions_index_for_groupless_layout()
    {
        $layout = new LayoutData([
            'layout' => [
                new MenuPresence([
                    'id'    => 'test-a',
                    'type'  => MenuPresenceType::ACTION,
                    'label' => 'something',
                    'permissions' => 'permission-a',
                ]),
                new MenuPresence([
                    'id'    => 'test-c',
                    'type'  => MenuPresenceType::ACTION,
                    'label' => 'something',
                ]),
                new MenuPresence([
                    'id'    => 'test-b',
                    'type'  => MenuPresenceType::ACTION,
                    'label' => 'something',
                    'permissions' => 'permission-b',
                ]),
            ]
        ]);

        $filter = new MenuPermissionsFilter();
        $index = $filter->buildPermissionsIndex($layout);

        static::assertInstanceOf(MenuPermissionsIndexDataInterface::class, $index);
        static::assertEmpty($index->index());
        static::assertEquals(['permission-a', 'permission-b'], $index->permissions());
    }

    /**
     * @test
     */
    function it_builds_a_permissions_index_for_single_layer_group_layout()
    {
        $layout = new LayoutData([
            'layout' => [
                // Group with unconditional presences
                'group-a' => new MenuPresence([
                    'id'    => 'group-a',
                    'type'  => MenuPresenceType::GROUP,
                    'label' => 'something',
                    'children' => [
                        new MenuPresence([
                            'id'    => 'test-a',
                            'type'  => MenuPresenceType::ACTION,
                            'label' => 'something',
                        ]),
                    ]
                ]),
                // Group with no unconditional presences
                'group-b' => new MenuPresence([
                    'id'    => 'group-b',
                    'type'  => MenuPresenceType::GROUP,
                    'label' => 'something',
                    'children' => [
                        new MenuPresence([
                            'id'    => 'test-b',
                            'type'  => MenuPresenceType::ACTION,
                            'label' => 'something',
                            'permissions' => 'permission-a',
                        ]),
                        new MenuPresence([
                            'id'    => 'test-c',
                            'type'  => MenuPresenceType::ACTION,
                            'label' => 'something',
                            'permissions' => 'permission-b',
                        ]),
                    ]
                ]),
                // Group with mixed presences
                'group-c' => new MenuPresence([
                    'id'    => 'group-c',
                    'type'  => MenuPresenceType::GROUP,
                    'label' => 'something',
                    'children' => [
                        new MenuPresence([
                            'id'    => 'test-e',
                            'type'  => MenuPresenceType::ACTION,
                            'label' => 'something',
                            'permissions' => 'permission-x',
                        ]),
                        new MenuPresence([
                            'id'    => 'test-f',
                            'type'  => MenuPresenceType::ACTION,
                            'label' => 'something',
                        ]),
                        new MenuPresence([
                            'id'          => 'test-g',
                            'type'        => MenuPresenceType::ACTION,
                            'label'       => 'something',
                            'permissions' => 'permission-y',
                        ]),
                    ]
                ]),
            ]
        ]);

        $filter = new MenuPermissionsFilter();
        $index = $filter->buildPermissionsIndex($layout);

        static::assertInstanceOf(MenuPermissionsIndexDataInterface::class, $index);
        static::assertEquals([
            'Z3JvdXAtYQ==' => [],
            'Z3JvdXAtYg==' => [ 'permission-a', 'permission-b' ]
        ], $index->index());
        static::assertEquals([
            'permission-a',
            'permission-b',
            'permission-x',
            'permission-y',
        ], $index->permissions());
    }

    /**
     * @test
     */
    function it_builds_a_permissions_index_for_a_deep_nested_group_layout()
    {
        $layout = new LayoutData([
            'layout' => $this->getComplexLayoutArray(),
        ]);

        $filter = new MenuPermissionsFilter();
        $index = $filter->buildPermissionsIndex($layout);

        static::assertInstanceOf(MenuPermissionsIndexDataInterface::class, $index);
        static::assertEquals([
            base64_encode('group-a') .'.' . base64_encode('group-b') => ['permission-a', 'permission-b'],
            base64_encode('group-a') .'.' . base64_encode('group-c') => [],
        ], $index->index());
        static::assertEquals([
            'permission-a',
            'permission-b',
            'permission-z',
            'permission-x',
            'permission-y',
        ], $index->permissions());
    }


    // ------------------------------------------------------------------------------
    //      Filtering
    // ------------------------------------------------------------------------------

    /**
     * @test
     * @expectedException \Exception
     */
    function it_throws_an_exception_if_no_permissions_index_is_set()
    {
        /** @var MenuLayoutDataInterface|\PHPUnit_Framework_MockObject_MockObject $layoutMock */
        $layoutMock = $this->getMockBuilder(MenuLayoutDataInterface::class)->getMock();

        $filter = new MenuPermissionsFilter();

        $filter->filterLayout($layoutMock, false);
    }

    /**
     * @test
     */
    function it_filters_a_layout_based_on_a_nested_filter_index()
    {
        $user = $this->getMockUser();

        $user->method('can')
            ->willReturnCallback(function ($permission) {
                switch ($permission) {
                    case 'permission-a':
                    case 'permission-z':
                    case 'permission-y':
                        return true;

                    default:
                        return false;
                }
            });

        $filter = new MenuPermissionsFilter();
        $index = new PermissionsIndexData([
            'index' => [
                base64_encode('group-a') .'.' . base64_encode('group-b') => ['permission-a', 'permission-b'],
                base64_encode('group-a') .'.' . base64_encode('group-c') => [],
            ],
            'permissions' => [
                'permission-a',
                'permission-b',
                'permission-z',
                'permission-x',
                'permission-y',
            ],
        ]);

        $layout = new LayoutData([
            'layout' => $this->getComplexLayoutArray(),
        ]);

        $layout = $filter->filterLayout($layout, $user, $index);

        static::assertInstanceOf(MenuLayoutDataInterface::class, $layout);
        $array = $layout->layout();
        static::assertCount(1, $array, 'Topmost layer should have 1 entry');
        static::assertArrayHasKey('group-a', $array, 'Topmost layer should have "group-a" key');

        static::assertCount(5, $array['group-a']->children(), 'Group-a layer should have 3 entries');
        static::assertArraySubset(
            [1, 2, 'group-b','group-c', 'group-d'], // 0 is filtered out
            array_keys($array['group-a']->children()),
            'Group-a layer should have keys: 1, 2, group-b, group-c, group-d'
        );

        // Group b
        static::assertCount(
            1,
            $array['group-a']->children()['group-b']->children(),
            'Group-b layer should have 1 entry'
        );
        static::assertEquals(
            'test-d',
            head($array['group-a']->children()['group-b']->children())['id'],
            'Group-b layer should have 1 entry'
        );

        // Group d
        static::assertCount(
            2,
            $array['group-a']->children()['group-d']->children(),
            'Group-d layer should have 2 entries'
        );
        static::assertEquals(
            'test-g',
            $array['group-a']->children()['group-d']->children()[0]['id'],
            'Group-d layer should have "test-g"'
        );
        static::assertEquals(
            'test-h',
            $array['group-a']->children()['group-d']->children()[1]['id'],
            'Group-d layer should have "test-h"'
        );
    }

    /**
     * @test
     */
    function it_removes_empty_groups_when_filtering()
    {
        $user = $this->getMockUser();

        $user->method('can')
            ->willReturnCallback(function ($permission) {
                switch ($permission) {
                    case 'permission-z':
                    case 'permission-y':
                        return true;

                    default:
                        return false;
                }
            });

        $filter = new MenuPermissionsFilter();
        $index = new PermissionsIndexData([
            'index' => [
                base64_encode('group-a') .'.' . base64_encode('group-b') => ['permission-a', 'permission-b'],
                base64_encode('group-a') .'.' . base64_encode('group-c') => [],
            ],
            'permissions' => [
                'permission-a',
                'permission-b',
                'permission-z',
                'permission-x',
                'permission-y',
            ],
        ]);

        $layout = new LayoutData([
            'layout' => $this->getComplexLayoutArray(),
        ]);

        $layout = $filter->filterLayout($layout, $user, $index);

        static::assertInstanceOf(MenuLayoutDataInterface::class, $layout);
        $array = $layout->layout();
        static::assertCount(1, $array, 'Topmost layer should have 1 entry');
        static::assertArrayHasKey('group-a', $array, 'Topmost layer should have "group-a" key');

        static::assertCount(4, $array['group-a']->children(), 'Group-a layer should have 4 entries');
        static::assertArrayNotHasKey(
            'group-b',
            $array['group-a']->children(),
            'Group-a should not have empty group "group-b"'
        );
    }

    /**
     * @test
     * @expectedException \UnexpectedValueException
     */
    function it_throws_an_exception_if_incorrect_value_is_given_for_user_parameter()
    {
        /** @var MenuLayoutDataInterface|\PHPUnit_Framework_MockObject_MockObject $layoutMock */
        $layoutMock = $this->getMockBuilder(MenuLayoutDataInterface::class)->getMock();

        $filter = new MenuPermissionsFilter();

        $filter->filterLayout($layoutMock, 'not a user');
    }

    /**
     * @test
     */
    function it_does_not_attempt_to_filter_if_user_is_admin()
    {
        $user = $this->getMockUser();
        $user->expects(static::atLeastOnce())->method('isAdmin')->willReturn(true);

        /** @var MenuLayoutDataInterface|\PHPUnit_Framework_MockObject_MockObject $layoutMock */
        $layoutMock = $this->getMockBuilder(MenuLayoutDataInterface::class)->getMock();
        $layoutMock->method('setLayout')
            ->willThrowException(new \RuntimeException("setLayout should not be called"));

        $indexMock = $this->getMockBuilder(MenuPermissionsIndexDataInterface::class)->getMock();
        $indexMock->method('index')
            ->willThrowException(new \RuntimeException("index should not be called"));
        $indexMock->method('permissions')
            ->willThrowException(new \RuntimeException("permissions should not be called"));

        $filter = new MenuPermissionsFilter();

        $filter->filterLayout($layoutMock, $user, $indexMock);
    }

    /**
     * @test
     */
    function it_does_not_attempt_to_filter_if_user_has_all_indexed_permissions()
    {
        $user = $this->getMockUser();
        $user->method('isAdmin')->willReturn(false);
        $user->method('can')->willReturn(true);
        $user->method('canAnyOf')->willReturn(true);

        /** @var MenuLayoutDataInterface|\PHPUnit_Framework_MockObject_MockObject $layoutMock */
        $layoutMock = $this->getMockBuilder(MenuLayoutDataInterface::class)->getMock();
        $layoutMock->method('setLayout')
            ->willThrowException(new \RuntimeException("setLayout should not be called"));

        $indexMock = $this->getMockBuilder(MenuPermissionsIndexDataInterface::class)->getMock();
        $indexMock->method('index')
            ->willThrowException(new \RuntimeException("index should not be called"));
        $indexMock->expects(static::atLeastOnce())->method('permissions')->willReturn([]);

        $filter = new MenuPermissionsFilter();

        $filter->filterLayout($layoutMock, $user, $indexMock);
    }

    /**
     * @test
     */
    function it_filters_out_anything_conditional_if_no_user_is_provided()
    {
        $layout = new LayoutData([
            'layout' => $this->getComplexLayoutArray(),
        ]);

        $index = new PermissionsIndexData([
            'index' => [
                base64_encode('group-a') .'.' . base64_encode('group-b') => ['permission-a', 'permission-b'],
                base64_encode('group-a') .'.' . base64_encode('group-c') => [],
            ],
            'permissions' => [
                'permission-a',
                'permission-b',
                'permission-z',
                'permission-x',
                'permission-y',
            ],
        ]);

        $filter = new MenuPermissionsFilter();

        $layout = $filter->filterLayout($layout, false, $index);

        $array = $layout->layout();
        static::assertCount(1, $array, 'Topmost layer should have 1 entry');
        static::assertArrayHasKey('group-a', $array, 'Topmost layer should have "group-a" key');

        static::assertCount(3, $array['group-a']->children(), 'Group-a layer should have 3 entries');
        static::assertArraySubset(['test-c','group-c', 'group-d'], array_pluck($array['group-a']->children(), 'id'));
        
        static::assertCount(1, $array['group-a']->children()['group-c']->children(), 'Group-a layer should have 1 entry');
        static::assertArraySubset(['test-f'], array_pluck($array['group-a']->children()['group-c']->children(), 'id'));
    }

    /**
     * @test
     */
    function it_is_optimized_to_not_parse_nodes_while_filtering_unless_this_is_required()
    {
        // We can test this by providing index data that is inconsistent with the layout data.
        // If the index indicates that some nodes should be left as-is, while the layout has
        // data that it *should* leave out, the layout should be returned with 'invalid' data.
        $user = $this->getMockUser();

        $user->method('can')
            ->willReturnCallback(function ($permission) {
                switch ($permission) {
                    case 'permission-a':
                    case 'permission-z':
                    case 'permission-y':
                        return true;

                    default:
                        return false;
                }
            });

        $filter = new MenuPermissionsFilter();
        $index = new PermissionsIndexData([
            'index' => [
                // this group should have permission-a & permission-b indexed, but since it has only a,
                // the users' permission-a will make the optimization skip the group regardless of its content
                base64_encode('group-a') .'.' . base64_encode('group-b') => ['permission-a'],
                base64_encode('group-a') .'.' . base64_encode('group-c') => [],
            ],
            'permissions' => [
                'permission-a',
                'permission-b',
                'permission-z',
                'permission-x',
                'permission-y',
            ],
        ]);

        $layout = new LayoutData([
            'layout' => $this->getComplexLayoutArray(),
        ]);

        $layout = $filter->filterLayout($layout, $user, $index);

        static::assertInstanceOf(MenuLayoutDataInterface::class, $layout);
        $array = $layout->layout();
        static::assertCount(1, $array, 'Topmost layer should have 1 entry');
        static::assertArrayHasKey('group-a', $array, 'Topmost layer should have "group-a" key');

        static::assertCount(5, $array['group-a']->children(), 'Group-a layer should have 3 entries');
        static::assertArraySubset(
            [1, 2, 'group-b','group-c', 'group-d'], // 0 is filtered out
            array_keys($array['group-a']->children()),
            'Group-a layer should have keys: 1, 2, group-b, group-c, group-d'
        );

        // Group b
        static::assertCount(
            2,
            $array['group-a']->children()['group-b']->children(),
            'Group-b layer should have 2 entries (regardless of missing permission-b)'
        );
    }


    // ------------------------------------------------------------------------------
    //      Helpers
    // ------------------------------------------------------------------------------

    /**
     * @return \PHPUnit_Framework_MockObject_MockObject|UserInterface
     */
    protected function getMockUser()
    {
        return $this->getMockBuilder(UserInterface::class)->getMock();
    }

    /**
     * @return array
     */
    protected function getComplexLayoutArray()
    {
        return [
            // Group with mixed presences (should not be indexed itself)
            'group-a' => new MenuPresence([
                'id'    => 'group-a',
                'type'  => MenuPresenceType::GROUP,
                'label' => 'something',
                'children' => [
                    new MenuPresence([
                        'id'          => 'test-a',
                        'type'        => MenuPresenceType::ACTION,
                        'label'       => 'something',
                        'permissions' => 'permission-x',
                    ]),
                    new MenuPresence([
                        'id'    => 'test-c',
                        'type'  => MenuPresenceType::ACTION,
                        'label' => 'something',
                    ]),
                    new MenuPresence([
                        'id'          => 'test-b',
                        'type'        => MenuPresenceType::ACTION,
                        'label'       => 'something',
                        'permissions' => 'permission-y',
                    ]),
                    // Group with no unconditional (action) presences (should be indexed)
                    'group-b' => new MenuPresence([
                        'id'    => 'group-b',
                        'type'  => MenuPresenceType::GROUP,
                        'label' => 'something',
                        'children' => [
                            new MenuPresence([
                                'id'          => 'test-d',
                                'type'        => MenuPresenceType::ACTION,
                                'label'       => 'something',
                                'permissions' => 'permission-a',
                            ]),
                            new MenuPresence([
                                'id'          => 'test-e',
                                'type'        => MenuPresenceType::ACTION,
                                'label'       => 'something',
                                'permissions' => 'permission-b',
                            ]),
                        ]
                    ]),
                    // Group with unconditional (action) presences (should be indexed)
                    'group-c' => new MenuPresence([
                        'id'    => 'group-c',
                        'type'  => MenuPresenceType::GROUP,
                        'label' => 'something',
                        'children' => [
                            new MenuPresence([
                                'id'    => 'test-f',
                                'type'  => MenuPresenceType::ACTION,
                                'label' => 'something',
                            ]),
                        ]
                    ]),
                    // Group with mixed presences (should not be indexed)
                    'group-d' => new MenuPresence([
                        'id'    => 'group-d',
                        'type'  => MenuPresenceType::GROUP,
                        'label' => 'something',
                        'children' => [
                            new MenuPresence([
                                'id'    => 'test-g',
                                'type'  => MenuPresenceType::ACTION,
                                'label' => 'something',
                            ]),
                            new MenuPresence([
                                'id'          => 'test-h',
                                'type'        => MenuPresenceType::ACTION,
                                'label'       => 'something',
                                'permissions' => 'permission-z',
                            ]),
                        ]
                    ]),
                ]
            ]),
        ];
    }

}
