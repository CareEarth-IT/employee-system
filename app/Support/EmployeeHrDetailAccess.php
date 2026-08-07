<?php

namespace App\Support;

use App\Models\User;

class EmployeeHrDetailAccess
{
    /**
     * @return array{
     *     core: array{view: bool, edit: bool},
     *     procedures: array{view: bool, edit: bool},
     *     it: array{view: bool, edit: bool, edit_self_device: bool},
     *     canSave: bool
     * }
     */
    public static function permissions(User $viewer, User $target): array
    {
        $core = [
            'view' => self::canViewCore($viewer, $target),
            'edit' => self::canEditCore($viewer, $target),
        ];
        $procedures = [
            'view' => self::canViewProcedures($viewer, $target),
            'edit' => self::canEditProcedures($viewer, $target),
        ];
        $it = [
            'view' => self::canViewIt($viewer, $target),
            'edit' => self::canEditIt($viewer, $target),
            'edit_self_device' => self::canEditItSelfDevice($viewer, $target),
        ];

        return [
            'core' => $core,
            'procedures' => $procedures,
            'it' => $it,
            'canSave' => $core['edit'] || $procedures['edit'] || $it['edit'] || $it['edit_self_device'],
        ];
    }

    public static function canViewPage(User $viewer, User $target): bool
    {
        $permissions = self::permissions($viewer, $target);

        return $permissions['core']['view']
            || $permissions['procedures']['view']
            || $permissions['it']['view'];
    }

    public static function canUpdateAny(User $viewer, User $target): bool
    {
        return self::permissions($viewer, $target)['canSave'];
    }

    /** 基本情報〜備考: 人事部・役員が編集 */
    public static function canEditCore(User $viewer, User $target): bool
    {
        return $viewer->isHrDepartment() || $viewer->isExecutive();
    }

    /** 基本情報〜備考: 情シス・人事課・人事部・役員が閲覧 */
    public static function canViewCore(User $viewer, User $target): bool
    {
        return $viewer->isExecutive()
            || $viewer->isInformationSystems()
            || $viewer->isHrSection()
            || $viewer->isHrDepartment();
    }

    /** 入社・退職手続き: 人事課・役員のみ編集 */
    public static function canEditProcedures(User $viewer, User $target): bool
    {
        return $viewer->isExecutive() || $viewer->isHrSection();
    }

    /** 入社・退職手続き: 人事課・役員と本人が閲覧 */
    public static function canViewProcedures(User $viewer, User $target): bool
    {
        return self::canEditProcedures($viewer, $target) || $viewer->id === $target->id;
    }

    /** IT・デバイス: 情シス・役員が編集 */
    public static function canEditIt(User $viewer, User $target): bool
    {
        return $viewer->isExecutive() || $viewer->isInformationSystems();
    }

    /** IT・デバイス（メーカー・型番・MAC）: 本人が編集 */
    public static function canEditItSelfDevice(User $viewer, User $target): bool
    {
        return $viewer->id === $target->id;
    }

    /** IT・デバイス: 人事部・人事課・役員・情シスと本人が閲覧 */
    public static function canViewIt(User $viewer, User $target): bool
    {
        return $viewer->isExecutive()
            || $viewer->isHrDepartment()
            || $viewer->isHrSection()
            || $viewer->isInformationSystems()
            || $viewer->id === $target->id;
    }

    /**
     * @return list<string>
     */
    public static function editableFieldNames(User $viewer, User $target): array
    {
        $fields = [];

        if (self::canEditCore($viewer, $target)) {
            $fields = array_merge($fields, EmployeeHrDetailFieldGroups::CORE);
        }

        if (self::canEditProcedures($viewer, $target)) {
            $fields = array_merge($fields, EmployeeHrDetailFieldGroups::PROCEDURES);
        }

        if (self::canEditIt($viewer, $target)) {
            $fields = array_merge($fields, EmployeeHrDetailFieldGroups::IT);
        } elseif (self::canEditItSelfDevice($viewer, $target)) {
            $fields = array_merge($fields, EmployeeHrDetailFieldGroups::IT_SELF_EDITABLE);
        }

        return array_values(array_unique($fields));
    }

    public static function canExportCsv(User $viewer): bool
    {
        return $viewer->isExecutive()
            || $viewer->isHrDepartment()
            || $viewer->isHrSection()
            || $viewer->isInformationSystems();
    }

    /** IT・デバイス一覧（Top Page「情シスデバイス用」） */
    public static function canViewItDeviceList(User $viewer): bool
    {
        return $viewer->isInformationSystems();
    }

    public static function canExportCsvForTarget(User $viewer, User $target): bool
    {
        return self::canViewPage($viewer, $target);
    }

    /**
     * @return list<string>
     */
    public static function viewableFieldNames(User $viewer, User $target): array
    {
        $fields = [];

        if (self::canViewCore($viewer, $target)) {
            $fields = array_merge($fields, EmployeeHrDetailFieldGroups::CORE);
        }

        if (self::canViewProcedures($viewer, $target)) {
            $fields = array_merge($fields, EmployeeHrDetailFieldGroups::PROCEDURES);
        }

        if (self::canViewIt($viewer, $target)) {
            $fields = array_merge($fields, EmployeeHrDetailFieldGroups::IT);
        }

        return array_values(array_unique($fields));
    }

    /**
     * @return list<string>
     */
    public static function viewableMetaColumns(User $viewer, User $target): array
    {
        $columns = [];

        if (self::canViewCore($viewer, $target)) {
            $columns = array_merge($columns, EmployeeHrDetailFieldLabels::META_CORE);
        }

        if (self::canViewProcedures($viewer, $target)) {
            $columns = array_merge($columns, EmployeeHrDetailFieldLabels::META_PROCEDURES);
        }

        return array_values(array_unique($columns));
    }

    /**
     * @param  iterable<int, User>  $targets
     * @return list<string>
     */
    public static function exportColumnNames(User $viewer, iterable $targets): array
    {
        $metaColumns = [];
        $detailFields = [];

        foreach ($targets as $target) {
            $metaColumns = array_merge($metaColumns, self::viewableMetaColumns($viewer, $target));
            $detailFields = array_merge($detailFields, self::viewableFieldNames($viewer, $target));
        }

        return self::orderExportColumns(
            array_values(array_unique($metaColumns)),
            array_values(array_unique($detailFields)),
        );
    }

    /**
     * @param  list<string>  $metaColumns
     * @param  list<string>  $detailFields
     * @return list<string>
     */
    private static function orderExportColumns(array $metaColumns, array $detailFields): array
    {
        $columns = [];

        foreach (EmployeeHrDetailFieldLabels::META as $column) {
            if (in_array($column, $metaColumns, true)) {
                $columns[] = $column;
            }
        }

        foreach (EmployeeHrDetailFieldGroups::all() as $field) {
            if (in_array($field, $detailFields, true)) {
                $columns[] = $field;
            }
        }

        return $columns;
    }
}
