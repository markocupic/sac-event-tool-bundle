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

namespace Markocupic\SacEventToolBundle\Tests\Export;

use Contao\Controller;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Date;
use Contao\StringUtil;
use Contao\TestCase\ContaoTestCase;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Markocupic\SacEventToolBundle\Export\UserExportHelper;
use Markocupic\SacEventToolBundle\Model\SacSectionModel;
use Markocupic\SacEventToolBundle\Model\UserRoleModel;
use Symfony\Contracts\Translation\TranslatorInterface;

class UserExportHelperTest extends ContaoTestCase
{
    public function testGetAvailableUserRolesReturnsKeyValuePairsFromDatabase(): void
    {
        $expected = [1 => 'Tourenleiter', 2 => 'Kassier'];

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with($this->stringContains('tl_user_role'))
            ->willReturn($expected)
        ;

        $helper = $this->getHelper($connection);

        $this->assertSame($expected, $helper->getAvailableUserRoles());
    }

    public function testGetAvailableUserGroupsQueriesTheUserGroupTableWithTimeBounds(): void
    {
        $expected = [5 => 'Redaktoren'];

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturnCallback(
                function (string $sql, array $params, array $types) use ($expected): array {
                    $this->assertStringContainsString('tl_user_group', $sql);
                    $this->assertStringContainsString('disable = 0', $sql);
                    // Both placeholders are bound with the current time as integers.
                    $this->assertCount(2, $params);
                    $this->assertIsInt($params[0]);
                    $this->assertIsInt($params[1]);
                    // Both bounds are set to "now"; allow a 2s delta in case the
                    // two time() calls straddle a second boundary.
                    $this->assertEqualsWithDelta($params[0], $params[1], 2);
                    $this->assertSame([Types::INTEGER, Types::INTEGER], $types);

                    return $expected;
                },
            )
        ;

        $helper = $this->getHelper($connection);

        $this->assertSame($expected, $helper->getAvailableUserGroups());
    }

    public function testGetAvailableMemberGroupsQueriesTheMemberGroupTableWithTimeBounds(): void
    {
        $expected = [9 => 'Aktivmitglieder'];

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturnCallback(
                function (string $sql, array $params, array $types) use ($expected): array {
                    $this->assertStringContainsString('tl_member_group', $sql);
                    $this->assertStringContainsString('disable = 0', $sql);
                    $this->assertCount(2, $params);
                    $this->assertSame([Types::INTEGER, Types::INTEGER], $types);

                    return $expected;
                },
            )
        ;

        $helper = $this->getHelper($connection);

        $this->assertSame($expected, $helper->getAvailableMemberGroups());
    }

    public function testGetHeadlineMapsIdToUppercaseAndTranslatesOtherFields(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(
                static function (string $id): string {
                    // Return a "real" translation only for the firstname label.
                    return 'tl_user.firstname.0' === $id ? 'Vorname' : $id;
                },
            )
        ;

        $framework = $this->mockContaoFramework([
            Controller::class => $this->mockAdapter(['loadLanguageFile']),
        ]);

        $helper = $this->getHelper(null, $framework, $translator);

        $headline = $helper->getHeadline(['id', 'firstname'], 'tl_user');

        $this->assertSame(['ID', 'Vorname'], $headline);
    }

    public function testGetHeadlineFallsBackToUcfirstWhenTranslationIsMissing(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);

        // Translator returns the key unchanged -> considered "not translated".
        $translator
            ->method('trans')
            ->willReturnArgument(0)
        ;

        $framework = $this->mockContaoFramework([
            Controller::class => $this->mockAdapter(['loadLanguageFile']),
        ]);

        $helper = $this->getHelper(null, $framework, $translator);

        $this->assertSame(['Lastname'], $helper->getHeadline(['lastname'], 'tl_user'));
    }

    public function testGetFormattedFieldValueThrowsWhenFieldIsMissing(): void
    {
        $helper = $this->getHelper();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Field email does not exist in table tl_user');

        $helper->getFormattedFieldValue('email', 'tl_user', ['firstname' => 'John']);
    }

    public function testGetFormattedFieldValueMasksThePassword(): void
    {
        $helper = $this->getHelper();

        $this->assertSame(
            '#######',
            $helper->getFormattedFieldValue('password', 'tl_user', ['password' => 'super-secret-hash']),
        );
    }

    public function testGetFormattedFieldValueReturnsEmptyStringForEmptyLastLogin(): void
    {
        $helper = $this->getHelper();

        $this->assertSame(
            '',
            $helper->getFormattedFieldValue('lastLogin', 'tl_user', ['lastLogin' => '']),
        );
    }

    public function testGetFormattedFieldValueFormatsLastLoginTimestamp(): void
    {
        $dateAdapter = $this->mockAdapter(['parse']);
        $dateAdapter
            ->expects($this->once())
            ->method('parse')
            ->with('Y-m-d', 1700000000)
            ->willReturn('2023-11-14')
        ;

        $framework = $this->mockContaoFramework([Date::class => $dateAdapter]);

        $helper = $this->getHelper(null, $framework);

        $this->assertSame(
            '2023-11-14',
            $helper->getFormattedFieldValue('lastLogin', 'tl_user', ['lastLogin' => 1700000000]),
        );
    }

    public function testGetFormattedFieldValueTranslatesAndJoinsLeiterQualifikation(): void
    {
        $stringUtil = $this->mockAdapter(['deserialize']);
        $stringUtil
            ->method('deserialize')
            ->willReturn([3, 7])
        ;

        $framework = $this->mockContaoFramework([
            Controller::class => $this->mockAdapter(['loadLanguageFile']),
            StringUtil::class => $stringUtil,
        ]);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnCallback(
                static fn (string $id): string => [
                    'tl_user.refLeiterQualifikation.3' => 'Sommer',
                    'tl_user.refLeiterQualifikation.7' => 'Winter',
                ][$id] ?? $id,
            )
        ;

        $helper = $this->getHelper(null, $framework, $translator);

        $this->assertSame(
            'Sommer, Winter',
            $helper->getFormattedFieldValue('leiterQualifikation', 'tl_user', ['leiterQualifikation' => serialize([3, 7])]),
        );
    }

    public function testGetFormattedFieldValueReturnsEmptyStringForEmptyLeiterQualifikation(): void
    {
        $stringUtil = $this->mockAdapter(['deserialize']);
        $stringUtil
            ->method('deserialize')
            ->willReturn([])
        ;

        $framework = $this->mockContaoFramework([
            Controller::class => $this->mockAdapter(['loadLanguageFile']),
            StringUtil::class => $stringUtil,
        ]);

        $helper = $this->getHelper(null, $framework);

        $this->assertSame(
            '',
            $helper->getFormattedFieldValue('leiterQualifikation', 'tl_user', ['leiterQualifikation' => '']),
        );
    }

    public function testGetFormattedFieldValueResolvesSectionIdsToNames(): void
    {
        $stringUtil = $this->mockAdapter(['deserialize']);
        $stringUtil
            ->method('deserialize')
            ->willReturn([4250, 4251])
        ;

        $sectionModel = $this->mockAdapter(['findBySectionId']);
        $sectionModel
            ->method('findBySectionId')
            ->willReturnCallback(
                fn (int $sectionId) => $this->mockClassWithProperties(
                    SacSectionModel::class,
                    ['name' => 'SAC Sektion '.$sectionId],
                ),
            )
        ;

        $framework = $this->mockContaoFramework([
            StringUtil::class => $stringUtil,
            SacSectionModel::class => $sectionModel,
        ]);

        $helper = $this->getHelper(null, $framework);

        $this->assertSame(
            'SAC Sektion 4250, SAC Sektion 4251',
            $helper->getFormattedFieldValue('sectionId', 'tl_user', ['sectionId' => serialize([4250, 4251])]),
        );
    }

    public function testGetFormattedFieldValueSkipsUnknownSections(): void
    {
        $stringUtil = $this->mockAdapter(['deserialize']);
        $stringUtil
            ->method('deserialize')
            ->willReturn([4250, 9999])
        ;

        $sectionModel = $this->mockAdapter(['findBySectionId']);
        $sectionModel
            ->method('findBySectionId')
            ->willReturnCallback(
                fn (int $sectionId) => 4250 === $sectionId
                    ? $this->mockClassWithProperties(SacSectionModel::class, ['name' => 'SAC Pilatus'])
                    : null,
            )
        ;

        $framework = $this->mockContaoFramework([
            StringUtil::class => $stringUtil,
            SacSectionModel::class => $sectionModel,
        ]);

        $helper = $this->getHelper(null, $framework);

        // The unknown section (findBySectionId returns null) is filtered out.
        $this->assertSame(
            'SAC Pilatus',
            $helper->getFormattedFieldValue('sectionId', 'tl_user', ['sectionId' => serialize([4250, 9999])]),
        );
    }

    public function testGetFormattedFieldValueTranslatesRescissionCause(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with('tl_user.rescissionCauseOptions.moved', [], 'contao_default')
            ->willReturn('Wegzug')
        ;

        $helper = $this->getHelper(null, null, $translator);

        $this->assertSame(
            'Wegzug',
            $helper->getFormattedFieldValue('rescissionCause', 'tl_user', ['rescissionCause' => 'moved']),
        );
    }

    public function testGetFormattedFieldValueReturnsEmptyStringForEmptyRescissionCause(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->never())
            ->method('trans')
        ;

        $helper = $this->getHelper(null, null, $translator);

        $this->assertSame(
            '',
            $helper->getFormattedFieldValue('rescissionCause', 'tl_user', ['rescissionCause' => '']),
        );
    }

    public function testGetFormattedFieldValueCastsPlainValuesToString(): void
    {
        $helper = $this->getHelper();

        $this->assertSame('42', $helper->getFormattedFieldValue('id', 'tl_user', ['id' => 42]));
        $this->assertSame('John', $helper->getFormattedFieldValue('firstname', 'tl_user', ['firstname' => 'John']));
    }

    public function testBuildRecordsAlwaysStartsWithTheHeadline(): void
    {
        $helper = $this->getHelper(null, $this->frameworkForBuildRecords(), $this->fallbackTranslator());

        $records = $helper->buildRecords(
            [],
            'tl_user',
            ['id', 'userRole'],
            'userRole',
            [],
            UserRoleModel::class,
        );

        $this->assertSame([['ID', 'UserRole']], $records);
    }

    public function testBuildRecordsJoinsRolesInOneLine(): void
    {
        $helper = $this->getHelper(
            null,
            $this->frameworkForBuildRecords(static fn (int $id): string => 'Role'.$id),
            $this->fallbackTranslator(),
        );

        $records = $helper->buildRecords(
            [
                ['id' => 7, 'userRole' => serialize([1, 2])],
            ],
            'tl_user',
            ['id', 'userRole'],
            'userRole',
            [],
            UserRoleModel::class,
            true,
        );

        $this->assertSame(['7', 'Role1, Role2'], $this->dataRows($records)[0]);
    }

    public function testBuildRecordsWritesOneRowPerRole(): void
    {
        $helper = $this->getHelper(
            null,
            $this->frameworkForBuildRecords(static fn (int $id): string => 'Role'.$id),
            $this->fallbackTranslator(),
        );

        $records = $helper->buildRecords(
            [
                ['id' => 7, 'userRole' => serialize([1, 2])],
            ],
            'tl_user',
            ['id', 'userRole'],
            'userRole',
            [],
            UserRoleModel::class,
        );

        $this->assertSame(
            [
                ['7', 'Role1'],
                ['7', 'Role2'],
            ],
            $this->dataRows($records),
        );
    }

    public function testBuildRecordsFiltersRowsAndRolesByFilterRoles(): void
    {
        $helper = $this->getHelper(
            null,
            $this->frameworkForBuildRecords(static fn (int $id): string => 'Role'.$id),
            $this->fallbackTranslator(),
        );

        $records = $helper->buildRecords(
            [
                // Keeps only role 2, role 1 is filtered out at column level.
                ['id' => 7, 'userRole' => serialize([1, 2])],
                // Whole row is dropped: none of its roles match the filter.
                ['id' => 8, 'userRole' => serialize([3])],
            ],
            'tl_user',
            ['id', 'userRole'],
            'userRole',
            [2],
            UserRoleModel::class,
        );

        $this->assertSame(
            [
                ['7', 'Role2'],
            ],
            $this->dataRows($records),
        );
    }

    public function testBuildRecordsWritesEmptyRoleColumnWhenUserHasNoRoles(): void
    {
        $helper = $this->getHelper(
            null,
            $this->frameworkForBuildRecords(),
            $this->fallbackTranslator(),
        );

        $records = $helper->buildRecords(
            [
                ['id' => 7, 'userRole' => serialize([])],
            ],
            'tl_user',
            ['id', 'userRole'],
            'userRole',
            [],
            UserRoleModel::class,
        );

        $this->assertSame(
            [
                ['7', ''],
            ],
            $this->dataRows($records),
        );
    }

    public function testBuildRecordsFallsBackForUnknownRole(): void
    {
        // findById returns null -> getRoleName produces the fallback label.
        $helper = $this->getHelper(
            null,
            $this->frameworkForBuildRecords(static fn (int $id): UserRoleModel|null => null),
            $this->fallbackTranslator(),
        );

        $records = $helper->buildRecords(
            [
                ['id' => 7, 'userRole' => serialize([99])],
            ],
            'tl_user',
            ['id', 'userRole'],
            'userRole',
            [],
            UserRoleModel::class,
        );

        $this->assertSame(
            [
                ['7', 'Unbekannte Gruppe/Rolle mit ID: 99'],
            ],
            $this->dataRows($records),
        );
    }

    public function testBuildRecordsKeepsColumnsAfterTheFilterColumn(): void
    {
        // Filter column is NOT last: firstname and id come after it and must be preserved
        // in every emitted per-role row.
        $helper = $this->getHelper(
            null,
            $this->frameworkForBuildRecords(static fn (int $id): string => 'Role'.$id),
            $this->fallbackTranslator(),
        );

        $records = $helper->buildRecords(
            [
                ['id' => 7, 'firstname' => 'John', 'userRole' => serialize([1, 2])],
            ],
            'tl_user',
            ['userRole', 'firstname', 'id'],
            'userRole',
            [],
            UserRoleModel::class,
        );

        $this->assertSame(
            [
                ['Role1', 'John', '7'],
                ['Role2', 'John', '7'],
            ],
            $this->dataRows($records),
        );
    }

    public function testBuildRecordsKeepsTrailingColumnsWhenJoiningRolesInOneLine(): void
    {
        $helper = $this->getHelper(
            null,
            $this->frameworkForBuildRecords(static fn (int $id): string => 'Role'.$id),
            $this->fallbackTranslator(),
        );

        $records = $helper->buildRecords(
            [
                ['id' => 7, 'firstname' => 'John', 'userRole' => serialize([1, 2])],
            ],
            'tl_user',
            ['userRole', 'firstname', 'id'],
            'userRole',
            [],
            UserRoleModel::class,
            true,
        );

        $this->assertSame(
            [
                ['Role1, Role2', 'John', '7'],
            ],
            $this->dataRows($records),
        );
    }

    /**
     * @param array<int, array<int, string>> $records
     *
     * @return array<int, array<int, string>>
     */
    private function dataRows(array $records): array
    {
        // Drop the headline row.
        return array_values(\array_slice($records, 1));
    }

    private function fallbackTranslator(): TranslatorInterface
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturnArgument(0)
        ;

        return $translator;
    }

    /**
     * @param (callable(int): (string|UserRoleModel|null))|null $roleResolver
     */
    private function frameworkForBuildRecords(callable|null $roleResolver = null): ContaoFramework
    {
        $stringUtil = $this->mockAdapter(['deserialize']);
        $stringUtil
            ->method('deserialize')
            ->willReturnCallback(
                static function ($value, $force = false) {
                    if (\is_array($value)) {
                        return $value;
                    }

                    if ('' === (string) $value) {
                        return $force ? [] : null;
                    }

                    $tmp = @unserialize((string) $value, ['allowed_classes' => false]);

                    if (\is_array($tmp)) {
                        return $tmp;
                    }

                    return $force ? [$value] : $value;
                },
            )
        ;

        $filterModelAdapter = $this->mockAdapter(['findById']);
        $filterModelAdapter
            ->method('findById')
            ->willReturnCallback(
                function (int $id) use ($roleResolver) {
                    $resolved = null !== $roleResolver ? $roleResolver($id) : 'Role'.$id;

                    if (null === $resolved || $resolved instanceof UserRoleModel) {
                        return $resolved;
                    }

                    return $this->mockClassWithProperties(UserRoleModel::class, ['title' => $resolved]);
                },
            )
        ;

        return $this->mockContaoFramework([
            Controller::class => $this->mockAdapter(['loadLanguageFile']),
            StringUtil::class => $stringUtil,
            UserRoleModel::class => $filterModelAdapter,
        ]);
    }

    private function getHelper(Connection|null $connection = null, ContaoFramework|null $framework = null, TranslatorInterface|null $translator = null): UserExportHelper
    {
        return new UserExportHelper(
            $connection ?? $this->createMock(Connection::class),
            $framework ?? $this->mockContaoFramework(),
            $translator ?? $this->createMock(TranslatorInterface::class),
        );
    }
}
