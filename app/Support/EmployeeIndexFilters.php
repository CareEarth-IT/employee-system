<?php

namespace App\Support;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EmployeeIndexFilters
{
    public const MAX_CONDITIONS = 10;

    private const TEXT_OPERATORS = ['contains', 'not_contains', 'eq', 'not_eq', 'empty', 'not_empty'];

    private const SELECT_OPERATORS = ['eq', 'not_eq', 'empty', 'not_empty'];

    private const DATE_OPERATORS = ['eq', 'not_eq', 'empty', 'not_empty'];

    private const EMPLOYEE_ID_OPERATORS = ['contains', 'not_contains', 'eq', 'not_eq', 'empty', 'not_empty'];

    /** @var array<string, array{label: string, group: string, type: string, operators: list<string>, input?: string}> */
    private const FIELD_DEFS = [
        'company' => [
            'label' => '所属会社',
            'group' => '社員一覧',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'employee_id' => [
            'label' => '社員ID',
            'group' => '社員一覧',
            'type' => 'text',
            'input' => 'employee_id',
            'operators' => self::EMPLOYEE_ID_OPERATORS,
        ],
        'name' => [
            'label' => '名前',
            'group' => '基本情報',
            'type' => 'text',
            'operators' => self::TEXT_OPERATORS,
        ],
        'english_name' => [
            'label' => 'Name',
            'group' => '基本情報',
            'type' => 'text',
            'operators' => self::TEXT_OPERATORS,
        ],
        'email' => [
            'label' => '社用メール',
            'group' => '基本情報',
            'type' => 'text',
            'input' => 'ascii',
            'operators' => self::TEXT_OPERATORS,
        ],
        'gmail_address' => [
            'label' => 'Gmailアドレス',
            'group' => '基本情報',
            'type' => 'text',
            'input' => 'ascii',
            'operators' => self::TEXT_OPERATORS,
        ],
        'affiliation_code' => [
            'label' => '所属',
            'group' => '基本情報',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'employment_type' => [
            'label' => '雇用形態',
            'group' => '基本情報',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'nationality' => [
            'label' => '国籍',
            'group' => '基本情報',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'employment_status' => [
            'label' => '状況',
            'group' => '基本情報',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'joined_at' => [
            'label' => '入社日',
            'group' => '基本情報',
            'type' => 'date',
            'operators' => self::DATE_OPERATORS,
        ],
        'resigned_at' => [
            'label' => '退職日',
            'group' => '基本情報',
            'type' => 'date',
            'operators' => self::DATE_OPERATORS,
        ],
        'last_working_day' => [
            'label' => '最終出勤日',
            'group' => '基本情報',
            'type' => 'date',
            'operators' => self::DATE_OPERATORS,
        ],
        'department_primary' => [
            'label' => '部署①',
            'group' => '部署・役職',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'section_primary' => [
            'label' => '課①',
            'group' => '部署・役職',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'position_primary' => [
            'label' => '役職①',
            'group' => '部署・役職',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'department_secondary' => [
            'label' => '部署②',
            'group' => '部署・役職',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'section_secondary' => [
            'label' => '課②',
            'group' => '部署・役職',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'position_secondary' => [
            'label' => '役職②',
            'group' => '部署・役職',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'jurisdiction' => [
            'label' => '管轄',
            'group' => '部署・役職',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'gender' => [
            'label' => '性別',
            'group' => '個人情報',
            'type' => 'select',
            'operators' => self::SELECT_OPERATORS,
        ],
        'birth_date' => [
            'label' => '生年月日',
            'group' => '個人情報',
            'type' => 'date',
            'operators' => self::DATE_OPERATORS,
        ],
        'company_phone' => [
            'label' => '社用電話番号',
            'group' => '個人情報',
            'type' => 'text',
            'operators' => self::TEXT_OPERATORS,
        ],
    ];

    /** @var array<string, string> */
    private const OPERATOR_LABELS = [
        'contains' => '次を含む',
        'not_contains' => '次を含まない',
        'eq' => '次と一致',
        'not_eq' => '次と一致しない',
        'empty' => '空',
        'not_empty' => '空でない',
        'starts_with' => '次で始まる',
    ];

    /**
     * @return array{groups: list<array{label: string, fields: list<string>}>, fields: array<string, array{label: string, group: string, type: string, operators: list<string>, options?: list<string>}>}
     */
    public static function fieldConfig(): array
    {
        $fields = [];

        foreach (self::FIELD_DEFS as $field => $definition) {
            $fields[$field] = [
                ...$definition,
                'options' => self::optionsForField($field),
            ];
        }

        $groups = [];
        $groupOrder = ['社員一覧', '基本情報', '部署・役職', '個人情報'];

        foreach ($groupOrder as $groupLabel) {
            $groupFields = [];

            foreach (self::FIELD_DEFS as $field => $definition) {
                if ($definition['group'] === $groupLabel) {
                    $groupFields[] = $field;
                }
            }

            if ($groupFields !== []) {
                $groups[] = [
                    'label' => $groupLabel,
                    'fields' => $groupFields,
                ];
            }
        }

        return [
            'groups' => $groups,
            'fieldOrder' => self::fieldOrder(),
            'fields' => $fields,
            'operatorLabels' => self::OPERATOR_LABELS,
        ];
    }

    /**
     * @return list<string>
     */
    public static function fieldOrder(): array
    {
        return array_keys(self::FIELD_DEFS);
    }

    /**
     * @return list<string>
     */
    public static function fieldKeysAfter(string $anchorField): array
    {
        $keys = self::fieldOrder();
        $anchorIndex = array_search($anchorField, $keys, true);

        if ($anchorIndex === false) {
            return $keys;
        }

        return array_values(array_slice($keys, $anchorIndex + 1));
    }

    /**
     * @return list<array{label: string, fields: list<string>}>
     */
    public static function groupsAfterField(string $anchorField): array
    {
        $allowedFields = self::fieldKeysAfter($anchorField);
        $groups = [];
        $groupOrder = ['社員一覧', '基本情報', '部署・役職', '個人情報'];

        foreach ($groupOrder as $groupLabel) {
            $groupFields = [];

            foreach ($allowedFields as $field) {
                if ((self::FIELD_DEFS[$field]['group'] ?? '') === $groupLabel) {
                    $groupFields[] = $field;
                }
            }

            if ($groupFields !== []) {
                $groups[] = [
                    'label' => $groupLabel,
                    'fields' => $groupFields,
                ];
            }
        }

        return $groups;
    }

    /**
     * @return list<array{field: string, op: string, value: string}>
     */
    public static function parseFromRequest(Request $request): array
    {
        $filters = self::parseFilterArray($request->input('filters'));

        if ($filters !== []) {
            return $filters;
        }

        return self::legacyFiltersFromRequest($request);
    }

    /**
     * @param  Builder<User>  $query
     * @param  list<array{field: string, op: string, value: string}>  $filters
     */
    public static function apply(Builder $query, array $filters): void
    {
        foreach ($filters as $filter) {
            self::applyFilter($query, $filter['field'], $filter['op'], $filter['value']);
        }
    }

    /**
     * @param  list<array{field: string, op: string, value: string}>  $filters
     * @return array<string, mixed>
     */
    public static function toQueryParams(array $filters): array
    {
        if ($filters === []) {
            return [];
        }

        $params = [];

        foreach ($filters as $index => $filter) {
            $params['filters'][$index] = $filter;
        }

        return $params;
    }

    public static function fieldLabel(string $field): string
    {
        return self::FIELD_DEFS[$field]['label']
            ?? EmployeeHrDetailFieldLabels::label($field);
    }

    public static function operatorLabel(string $operator): string
    {
        return self::OPERATOR_LABELS[$operator] ?? $operator;
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyFilter(Builder $query, string $field, string $operator, string $value): void
    {
        match ($field) {
            'company' => self::applyCompanyFilter($query, $operator, $value),
            'employee_id' => self::applyEmployeeIdFilter($query, $operator, $value),
            'name' => self::applyUserTextFilter($query, ['last_name', 'first_name', 'name'], $operator, $value),
            'english_name' => self::applyProfileTextFilter($query, 'english_name', $operator, $value),
            'email' => self::applyUserTextFilter($query, 'email', $operator, $value),
            'joined_at' => self::applyProfileDateFilter($query, 'joined_at', $operator, $value),
            'employment_type' => self::applyEmploymentTypeFilter($query, $operator, $value),
            'affiliation_code' => self::applyAffiliationCodeFilter($query, $operator, $value),
            'employment_status' => self::applyEmploymentStatusFilter($query, $operator, $value),
            'nationality' => self::applyProfileTextFilter($query, 'nationality', $operator, $value),
            'resigned_at', 'last_working_day', 'birth_date' => self::applyHrDetailDateFilter($query, $field, $operator, $value),
            'gender', 'company_phone', 'gmail_address',
            'department_primary', 'section_primary', 'position_primary',
            'department_secondary', 'section_secondary', 'position_secondary',
            'jurisdiction' => self::applyHrDetailTextFilter($query, $field, $operator, $value),
            default => null,
        };
    }

    private static function isPresenceOperator(string $operator): bool
    {
        return in_array($operator, ['empty', 'not_empty'], true);
    }

    /**
     * @return list<string>
     */
    private static function optionsForField(string $field): array
    {
        return match ($field) {
            'company' => User::COMPANY_NAMES,
            'affiliation_code' => array_values(User::companyAffiliationSelectOptions()),
            'employment_type' => User::EMPLOYMENT_TYPE_OPTIONS,
            'employment_status' => User::EMPLOYMENT_STATUS_OPTIONS,
            'department_primary', 'department_secondary' => RegistryDepartmentOptions::options(),
            'section_primary', 'section_secondary' => RegistrySectionOptions::options(),
            'position_primary', 'position_secondary' => RegistryPositionOptions::OPTIONS,
            'jurisdiction' => User::OFFICE_LOCATIONS,
            'gender' => EmployeeHrDetail::GENDERS,
            'nationality' => NationalityOptions::names(),
            default => [],
        };
    }

    /**
     * @param  mixed  $rawFilters
     * @return list<array{field: string, op: string, value: string}>
     */
    private static function parseFilterArray(mixed $rawFilters): array
    {
        if (! is_array($rawFilters)) {
            return [];
        }

        $filters = [];

        foreach ($rawFilters as $rawFilter) {
            if (! is_array($rawFilter)) {
                continue;
            }

            $filter = self::normalizeFilter($rawFilter);

            if ($filter !== null) {
                $filters[] = $filter;
            }

            if (count($filters) >= self::MAX_CONDITIONS) {
                break;
            }
        }

        return $filters;
    }

    /**
     * @return list<array{field: string, op: string, value: string}>
     */
    private static function legacyFiltersFromRequest(Request $request): array
    {
        $filters = [];

        $company = trim((string) $request->query('company', ''));
        if ($company !== '' && in_array($company, User::COMPANY_NAMES, true)) {
            $filters[] = ['field' => 'company', 'op' => 'eq', 'value' => $company];
        }

        $employeeId = trim((string) $request->query('employee_id', ''));
        if (preg_match('/^\d{5}$/', $employeeId) === 1) {
            $filters[] = ['field' => 'employee_id', 'op' => 'eq', 'value' => $employeeId];
        }

        $employmentType = trim((string) $request->query('employment_type', ''));
        if ($employmentType !== '' && in_array($employmentType, User::EMPLOYMENT_TYPE_OPTIONS, true)) {
            $filters[] = ['field' => 'employment_type', 'op' => 'eq', 'value' => $employmentType];
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $rawFilter
     * @return array{field: string, op: string, value: string}|null
     */
    private static function normalizeFilter(array $rawFilter): ?array
    {
        $field = trim((string) ($rawFilter['field'] ?? ''));
        $operator = trim((string) ($rawFilter['op'] ?? ''));
        $value = trim((string) ($rawFilter['value'] ?? ''));

        if (! isset(self::FIELD_DEFS[$field])) {
            return null;
        }

        if (! in_array($operator, self::FIELD_DEFS[$field]['operators'], true)) {
            $operator = self::FIELD_DEFS[$field]['operators'][0];
        }

        if (self::isPresenceOperator($operator)) {
            return [
                'field' => $field,
                'op' => $operator,
                'value' => '',
            ];
        }

        if ($value === '') {
            return null;
        }

        $value = self::normalizeFilterValue($field, $operator, $value);

        if ($value === null) {
            return null;
        }

        if (self::FIELD_DEFS[$field]['type'] === 'select'
            && ! in_array($value, self::optionsForField($field), true)) {
            return null;
        }

        return [
            'field' => $field,
            'op' => $operator,
            'value' => $value,
        ];
    }

    private static function normalizeFilterValue(string $field, string $operator, string $value): ?string
    {
        return match ($field) {
            'employee_id' => self::normalizeEmployeeIdValue($operator, $value),
            'email', 'gmail_address' => preg_match('/^[A-Za-z0-9@._+\-]+$/', $value) === 1 ? $value : null,
            'joined_at', 'resigned_at', 'last_working_day', 'birth_date' => self::normalizeDateValue($value),
            default => $value,
        };
    }

    private static function normalizeEmployeeIdValue(string $operator, string $value): ?string
    {
        if ($operator === 'eq' || $operator === 'not_eq') {
            return preg_match('/^\d{5}$/', $value) === 1 ? $value : null;
        }

        return preg_match('/^\d{1,5}$/', $value) === 1 ? $value : null;
    }

    private static function normalizeDateValue(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $value;
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyProfileDateFilter(Builder $query, string $column, string $operator, string $value): void
    {
        $query->whereHas('profile', function (Builder $profileQuery) use ($column, $operator, $value) {
            self::applyDateColumnOperator($profileQuery, $column, $operator, $value);
        });
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyHrDetailDateFilter(Builder $query, string $column, string $operator, string $value): void
    {
        $query->whereHas('hrDetail', function (Builder $hrDetailQuery) use ($column, $operator, $value) {
            self::applyDateColumnOperator($hrDetailQuery, $column, $operator, $value);
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function applyDateColumnOperator(
        Builder $query,
        string $column,
        string $operator,
        string $value,
        string $method = 'where',
    ): void {
        match ($operator) {
            'eq' => $query->{$method . 'Date'}($column, $value),
            'not_eq' => $query->{$method . 'Date'}($column, '!=', $value),
            'empty' => self::applyEmptyColumn($query, $column, $method),
            'not_empty' => self::applyNotEmptyColumn($query, $column, $method),
            default => null,
        };
    }

    /**
     * @param  Builder<User>  $query
     * @param  string|list<string>  $columns
     */
    private static function applyUserTextFilter(Builder $query, string|array $columns, string $operator, string $value): void
    {
        $columns = (array) $columns;

        if (self::isPresenceOperator($operator)) {
            $query->where(function (Builder $userQuery) use ($columns, $operator) {
                if ($operator === 'empty') {
                    foreach ($columns as $column) {
                        self::applyEmptyColumn($userQuery, $column);
                    }

                    return;
                }

                $userQuery->where(function (Builder $subQuery) use ($columns) {
                    foreach ($columns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        self::applyNotEmptyColumn($subQuery, $column, $method);
                    }
                });
            });

            return;
        }

        if (in_array($operator, ['not_contains', 'not_eq'], true) && count($columns) > 1) {
            $query->where(function (Builder $userQuery) use ($columns, $operator, $value) {
                foreach ($columns as $column) {
                    self::applyColumnOperator($userQuery, $column, $operator, $value);
                }
            });

            return;
        }

        $query->where(function (Builder $userQuery) use ($columns, $operator, $value) {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                self::applyColumnOperator($userQuery, $column, $operator, $value, $method);
            }
        });
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyProfileTextFilter(Builder $query, string $column, string $operator, string $value): void
    {
        $query->whereHas('profile', function (Builder $profileQuery) use ($column, $operator, $value) {
            self::applyColumnOperator($profileQuery, $column, $operator, $value);
        });
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyHrDetailTextFilter(Builder $query, string $column, string $operator, string $value): void
    {
        $query->whereHas('hrDetail', function (Builder $hrDetailQuery) use ($column, $operator, $value) {
            self::applyColumnOperator($hrDetailQuery, $column, $operator, $value);
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function applyColumnOperator(
        Builder $query,
        string $column,
        string $operator,
        string $value,
        string $method = 'where',
    ): void {
        match ($operator) {
            'eq' => $query->{$method}($column, $value),
            'not_eq' => $query->{$method}($column, '!=', $value),
            'starts_with' => $query->{$method}($column, 'like', $value.'%'),
            'contains' => $query->{$method}($column, 'like', '%'.$value.'%'),
            'not_contains' => $query->{$method}($column, 'not like', '%'.$value.'%'),
            'empty' => self::applyEmptyColumn($query, $column, $method),
            'not_empty' => self::applyNotEmptyColumn($query, $column, $method),
            default => $query->{$method}($column, 'like', '%'.$value.'%'),
        };
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function applyEmptyColumn(Builder $query, string $column, string $method = 'where'): void
    {
        $query->{$method}(function (Builder $subQuery) use ($column) {
            $subQuery->whereNull($column)->orWhere($column, '');
        });
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function applyNotEmptyColumn(Builder $query, string $column, string $method = 'where'): void
    {
        $query->{$method}(function (Builder $subQuery) use ($column) {
            $subQuery->whereNotNull($column)->where($column, '!=', '');
        });
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyEmployeeIdFilter(Builder $query, string $operator, string $value): void
    {
        if (self::isPresenceOperator($operator)) {
            self::applyColumnOperator($query, 'employee_id', $operator, $value);

            return;
        }

        match ($operator) {
            'eq' => $query->where('employee_id', $value),
            'not_eq' => $query->where('employee_id', '!=', $value),
            'contains' => $query->where('employee_id', 'like', '%'.$value.'%'),
            'not_contains' => $query->where('employee_id', 'not like', '%'.$value.'%'),
            default => $query->where('employee_id', $value),
        };
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyCompanyFilter(Builder $query, string $operator, string $value): void
    {
        match ($operator) {
            'eq' => $query->whereDisplayCompany($value),
            'not_eq' => $query->where(function (Builder $companyQuery) use ($value) {
                $companyQuery->whereNot(function (Builder $subQuery) use ($value) {
                    $subQuery->whereDisplayCompany($value);
                });
            }),
            'empty' => $query->where(function (Builder $companyQuery) {
                $companyQuery->whereNot(function (Builder $subQuery) {
                    $subQuery->whereHas('affiliationHistories', function (Builder $affiliationQuery) {
                        $affiliationQuery
                            ->whereNotNull('company')
                            ->where('company', '!=', '');
                    });
                });
            }),
            'not_empty' => $query->whereHas('affiliationHistories', function (Builder $affiliationQuery) {
                $affiliationQuery
                    ->whereNotNull('company')
                    ->where('company', '!=', '');
            }),
            default => null,
        };
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyEmploymentTypeFilter(Builder $query, string $operator, string $value): void
    {
        $matchesEmploymentType = function (Builder $employmentQuery) use ($value) {
            $employmentQuery
                ->whereHas('hrDetail', fn (Builder $hrDetailQuery) => $hrDetailQuery->where('employment_type', $value))
                ->orWhereHas('affiliationHistories', function (Builder $affiliationQuery) use ($value) {
                    $affiliationQuery
                        ->currentlyActive()
                        ->where('position', $value);
                });
        };

        match ($operator) {
            'eq' => $query->where($matchesEmploymentType),
            'not_eq' => $query->whereNot($matchesEmploymentType),
            'empty' => $query->where(function (Builder $employmentQuery) {
                $employmentQuery
                    ->where(function (Builder $subQuery) {
                        $subQuery
                            ->whereDoesntHave('hrDetail')
                            ->orWhereHas('hrDetail', function (Builder $detailQuery) {
                                self::applyEmptyColumn($detailQuery, 'employment_type');
                            });
                    })
                    ->whereDoesntHave('affiliationHistories', function (Builder $affiliationQuery) {
                        $affiliationQuery
                            ->currentlyActive()
                            ->whereNotNull('position')
                            ->where('position', '!=', '');
                    });
            }),
            'not_empty' => $query->where(function (Builder $employmentQuery) {
                $employmentQuery
                    ->whereHas('hrDetail', function (Builder $detailQuery) {
                        self::applyNotEmptyColumn($detailQuery, 'employment_type');
                    })
                    ->orWhereHas('affiliationHistories', function (Builder $affiliationQuery) {
                        $affiliationQuery
                            ->currentlyActive()
                            ->whereNotNull('position')
                            ->where('position', '!=', '');
                    });
            }),
            default => null,
        };
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyAffiliationCodeFilter(Builder $query, string $operator, string $value): void
    {
        $matchesAffiliationCode = function (Builder $userQuery) use ($value) {
            $userQuery->whereHas('hrDetail', function (Builder $hrDetailQuery) use ($value) {
                $code = User::resolveAffiliationCodeForStorage($value);

                if ($code !== null) {
                    $hrDetailQuery->where('affiliation_code', $code);

                    return;
                }

                $hrDetailQuery->where('affiliation_code', $value);
            });
        };

        match ($operator) {
            'eq' => $query->where($matchesAffiliationCode),
            'not_eq' => $query->whereNot($matchesAffiliationCode),
            'empty' => $query->whereHas('hrDetail', function (Builder $hrDetailQuery) {
                self::applyEmptyColumn($hrDetailQuery, 'affiliation_code');
            }),
            'not_empty' => $query->whereHas('hrDetail', function (Builder $hrDetailQuery) {
                self::applyNotEmptyColumn($hrDetailQuery, 'affiliation_code');
            }),
            default => null,
        };
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyEmploymentStatusFilter(Builder $query, string $operator, string $value): void
    {
        $matchesEmploymentStatus = function (Builder $statusQuery) use ($value) {
            $statusQuery->whereHas('hrDetail', function (Builder $hrDetailQuery) use ($value) {
                if ($value === '在籍') {
                    $hrDetailQuery->whereIn('employment_status', EmploymentStatus::ACTIVE_STORED_VALUES);

                    return;
                }

                if ($value === '退職') {
                    $hrDetailQuery->whereIn('employment_status', ['退職', '辞退']);

                    return;
                }

                $hrDetailQuery->where('employment_status', $value);
            });
        };

        match ($operator) {
            'eq' => $query->where($matchesEmploymentStatus),
            'not_eq' => $query->whereNot($matchesEmploymentStatus),
            'empty' => $query->where(function (Builder $statusQuery) {
                $statusQuery
                    ->whereDoesntHave('hrDetail')
                    ->orWhereHas('hrDetail', function (Builder $hrDetailQuery) {
                        self::applyEmptyColumn($hrDetailQuery, 'employment_status');
                    });
            }),
            'not_empty' => $query->whereHas('hrDetail', function (Builder $hrDetailQuery) {
                self::applyNotEmptyColumn($hrDetailQuery, 'employment_status');
            }),
            default => null,
        };
    }
}
