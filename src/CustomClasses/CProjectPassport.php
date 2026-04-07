<?php

declare(strict_types=1);

namespace CustomClasses;

use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;

Loader::includeModule('highloadblock');

class CProjectPassport
{
    private const PROJECT_PASSPORT_HL_CODE = 'ProjectPassport';
    private const PROJECT_PASSPORT_TAB_HL_CODE = 'ProjectPassportTab';
    private const PROJECT_PASSPORT_FIELD_HL_CODE = 'ProjectPassportField';
    private const PROJECT_PASSPORT_FIELD_VALUE_HL_CODE = 'ProjectPassportFieldValue';
    private const PROJECT_PASSPORT_TABLE_META_HL_CODE = 'ProjectPassportTableMeta';
    private const PROJECT_PASSPORT_MEMBER_HL_CODE = 'ProjectPassportMember';
    private const PROJECT_PASSPORT_KPI_HL_CODE = 'ProjectPassportKpi';
    private const PROJECT_PASSPORT_BUDGET_HL_CODE = 'ProjectPassportBudget';
    private const PROJECT_PASSPORT_DEPENDENCY_HL_CODE = 'ProjectPassportDependency';
    private const PROJECT_PASSPORT_AGES_HL_CODE = 'ProjectPassportAges';

    public static function activatePassport(int $projectId): void
    {
        if ($projectId <= 0) {
            return;
        }

        $userId = static::getCurrentUserId();
        $passportClass = static::getHlClass(self::PROJECT_PASSPORT_HL_CODE);
        if ($userId <= 0 || $passportClass === '') {
            return;
        }

        $passportId = static::getPassportIdByProjectId($projectId);
        if ($passportId <= 0) {
            static::addPassport($projectId);
            return;
        }

        $passportClass::update($passportId, [
            'UF_ACTIVE' => true,
            'UF_UPDATED_BY' => $userId,
            'UF_UPDATED_AT' => new DateTime(),
        ]);
    }

    public static function deactivatePassport(int $projectId): void
    {
        if ($projectId <= 0) {
            return;
        }

        $passportId = static::getPassportIdByProjectId($projectId);
        $userId = static::getCurrentUserId();
        $passportClass = static::getHlClass(self::PROJECT_PASSPORT_HL_CODE);
        if ($passportId <= 0 || $userId <= 0 || $passportClass === '') {
            return;
        }

        $passportClass::update($passportId, [
            'UF_ACTIVE' => false,
            'UF_UPDATED_BY' => $userId,
            'UF_UPDATED_AT' => new DateTime(),
        ]);
    }

    public static function getPassportIdByProjectId(int $projectId): int
    {
        if ($projectId <= 0) {
            return 0;
        }

        $passportClass = static::getHlClass(self::PROJECT_PASSPORT_HL_CODE);
        if ($passportClass === '') {
            return 0;
        }

        $request = $passportClass::getList([
            'filter' => ['UF_PROJECT_ID' => $projectId],
        ]);

        $row = $request->fetch();
        return (int)($row['ID'] ?? 0);
    }

    public static function addPassport(int $projectId): int
    {
        if ($projectId <= 0) {
            return 0;
        }

        $passportClass = static::getHlClass(self::PROJECT_PASSPORT_HL_CODE);
        $userId = static::getCurrentUserId();
        if ($passportClass === '' || $userId <= 0) {
            return 0;
        }

        $result = $passportClass::add([
            'UF_PROJECT_ID' => $projectId,
            'UF_ACTIVE' => true,
            'UF_CREATED_BY' => $userId,
            'UF_CREATED_AT' => new DateTime(),
        ]);

        return $result->isSuccess() ? (int)$result->getId() : 0;
    }

    public static function getTabs(): array
    {
        $tabClass = static::getHlClass(self::PROJECT_PASSPORT_TAB_HL_CODE);
        if ($tabClass === '') {
            return [];
        }

        $result = [];
        $request = $tabClass::getList([
            'filter' => ['!UF_VISIBLE' => false],
            'order' => ['UF_SORT' => 'ASC'],
        ]);

        while ($row = $request->fetch()) {
            $result[] = [
                'id' => (int)$row['ID'],
                'title' => (string)$row['UF_TITLE'],
                'code' => (string)$row['UF_CODE'],
                'fields' => [],
                'tables' => [],
            ];
        }

        return $result;
    }

    public static function getFieldsGroupedByTab(array $tabCodes): array
    {
        $tabCodes = static::normalizeCodes($tabCodes);
        if ($tabCodes === []) {
            return [];
        }

        $fieldClass = static::getHlClass(self::PROJECT_PASSPORT_FIELD_HL_CODE);
        if ($fieldClass === '') {
            return [];
        }

        $result = [];
        $request = $fieldClass::getList([
            'filter' => [
                'UF_TAB_CODE' => $tabCodes,
                '!UF_VISIBLE' => false,
            ],
            'order' => ['UF_SORT' => 'ASC'],
        ]);

        while ($row = $request->fetch()) {
            $tabCode = (string)$row['UF_TAB_CODE'];
            $result[$tabCode][] = [
                'id' => (int)$row['ID'],
                'title' => (string)$row['UF_TITLE'],
                'code' => (string)$row['UF_CODE'],
                'tab' => $tabCode,
            ];
        }

        return $result;
    }

    public static function getTableMetaGroupedByTab(array $tabCodes): array
    {
        $tabCodes = static::normalizeCodes($tabCodes);
        if ($tabCodes === []) {
            return [];
        }

        $metaClass = static::getHlClass(self::PROJECT_PASSPORT_TABLE_META_HL_CODE);
        if ($metaClass === '') {
            return [];
        }

        $result = [];
        $request = $metaClass::getList([
            'filter' => [
                'UF_TAB_CODE' => $tabCodes,
                '!UF_VISIBLE' => false,
            ],
            'order' => [
                'UF_TAB_CODE' => 'ASC',
                'UF_SORT' => 'ASC',
                'ID' => 'ASC',
            ],
        ]);

        while ($row = $request->fetch()) {
            $tabCode = (string)$row['UF_TAB_CODE'];
            $result[$tabCode][0]['columns'][] = [
                'id' => (int)$row['ID'],
                'title' => (string)$row['UF_TITLE'],
                'code' => (string)$row['UF_CODE'],
                'tabCode' => $tabCode,
            ];
        }

        return $result;
    }

    public static function getMetaForPassport(): array
    {
        $tabs = static::getTabs();
        if ($tabs === []) {
            return [];
        }

        $tabCodes = array_column($tabs, 'code');
        $fieldsByTab = static::getFieldsGroupedByTab($tabCodes);
        $tablesByTab = static::getTableMetaGroupedByTab($tabCodes);

        $result = [];
        foreach ($tabs as $tab) {
            $tabCode = (string)$tab['code'];
            $tab['fields'] = $fieldsByTab[$tabCode] ?? [];
            $tab['tables'] = $tablesByTab[$tabCode] ?? [];
            $result[] = $tab;
        }

        return $result;
    }

    public static function processTabData(int $projectId, array $tabData): void
    {
        if ($projectId <= 0 || $tabData === []) {
            return;
        }

        $tabCode = trim((string)($tabData['tabCode'] ?? ''));
        if ($tabCode === '') {
            return;
        }

        $passportId = static::getPassportIdByProjectId($projectId);
        if ($passportId <= 0) {
            $passportId = static::addPassport($projectId);
        }

        if ($passportId <= 0) {
            return;
        }

        $fields = is_array($tabData['fields'] ?? null) ? $tabData['fields'] : [];
        $tables = is_array($tabData['tables'] ?? null) ? $tabData['tables'] : [];

        if ($fields !== []) {
            static::processFieldsData($passportId, $tabCode, $fields);
        }

        foreach ($tables as $tableData) {
            $rows = is_array($tableData['rows'] ?? null) ? $tableData['rows'] : [];
            static::processTableData($passportId, $tabCode, $rows);
        }
    }

    public static function processFieldsData(int $passportId, string $tabCode, array $fields): void
    {
        if ($passportId <= 0 || $tabCode === '' || $fields === []) {
            return;
        }

        $userId = static::getCurrentUserId();
        $fieldClass = static::getHlClass(self::PROJECT_PASSPORT_FIELD_HL_CODE);
        $valueClass = static::getHlClass(self::PROJECT_PASSPORT_FIELD_VALUE_HL_CODE);
        if ($userId <= 0 || $fieldClass === '' || $valueClass === '') {
            return;
        }

        $metaFields = [];
        $fieldRequest = $fieldClass::getList([
            'filter' => [
                '=UF_TAB_CODE' => $tabCode,
                '!UF_VISIBLE' => false,
            ],
        ]);
        while ($field = $fieldRequest->fetch()) {
            $metaFields[(string)$field['UF_CODE']] = $field;
        }

        if ($metaFields === []) {
            return;
        }

        $currentValues = [];
        $valueRequest = $valueClass::getList([
            'filter' => [
                '=UF_PASSPORT_ID' => $passportId,
                'UF_FIELD_CODE' => array_keys($metaFields),
            ],
        ]);
        while ($value = $valueRequest->fetch()) {
            $currentValues[(string)$value['UF_FIELD_CODE']] = $value;
        }

        $now = new DateTime();
        foreach ($fields as $fieldCode => $value) {
            $fieldCode = trim((string)$fieldCode);
            if ($fieldCode === '' || !isset($metaFields[$fieldCode])) {
                continue;
            }

            $newValue = static::normalizeValue($value);
            if (!isset($currentValues[$fieldCode])) {
                if ($newValue === '') {
                    continue;
                }

                $valueClass::add([
                    'UF_PASSPORT_ID' => $passportId,
                    'UF_FIELD_CODE' => $fieldCode,
                    'UF_VALUE' => $newValue,
                    'UF_CREATED_BY' => $userId,
                    'UF_CREATED_AT' => $now,
                ]);
                continue;
            }

            $currentValue = $currentValues[$fieldCode];
            if (static::normalizeValue($currentValue['UF_VALUE'] ?? '') === $newValue) {
                continue;
            }

            $valueClass::update((int)$currentValue['ID'], [
                'UF_VALUE' => $newValue,
                'UF_UPDATED_BY' => $userId,
                'UF_UPDATED_AT' => $now,
            ]);
        }
    }

    public static function processTableData(int $passportId, string $tabCode, array $rows): void
    {
        if ($passportId <= 0 || $tabCode === '') {
            return;
        }

        $userId = static::getCurrentUserId();
        $storageCode = static::getStorageHlCodeByTabCode($tabCode);
        $dataClass = static::getHlClass($storageCode);
        $metaClass = static::getHlClass(self::PROJECT_PASSPORT_TABLE_META_HL_CODE);
        if ($userId <= 0 || $storageCode === '' || $dataClass === '' || $metaClass === '') {
            return;
        }

        $allowedFields = [];
        $metaRequest = $metaClass::getList([
            'filter' => [
                '=UF_TAB_CODE' => $tabCode,
                '!UF_VISIBLE' => false,
            ],
            'order' => [
                'UF_SORT' => 'ASC',
                'ID' => 'ASC',
            ],
        ]);

        while ($metaRow = $metaRequest->fetch()) {
            $fieldName = trim((string)$metaRow['UF_CODE']);
            if ($fieldName !== '') {
                $allowedFields[$fieldName] = true;
            }
        }

        if ($allowedFields === []) {
            return;
        }

        $currentRows = [];
        $dataRequest = $dataClass::getList([
            'filter' => ['=UF_PASSPORT_ID' => $passportId],
            'order' => ['ID' => 'ASC'],
        ]);
        while ($dataRow = $dataRequest->fetch()) {
            $currentRows[(int)$dataRow['ID']] = $dataRow;
        }

        $incomingRowIds = [];
        foreach ($rows as $row) {
            if (is_array($row) && (int)($row['id'] ?? 0) > 0) {
                $incomingRowIds[(int)$row['id']] = true;
            }
        }

        foreach (array_keys($currentRows) as $currentRowId) {
            if (!isset($incomingRowIds[$currentRowId])) {
                $dataClass::delete($currentRowId);
                unset($currentRows[$currentRowId]);
            }
        }

        $now = new DateTime();
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rowId = (int)($row['id'] ?? 0);
            $preparedFields = [];
            foreach ($allowedFields as $fieldName => $unused) {
                if (array_key_exists($fieldName, $row)) {
                    $preparedFields[$fieldName] = static::normalizeValue($row[$fieldName]);
                }
            }

            if ($rowId > 0 && isset($currentRows[$rowId])) {
                $updateFields = [];
                foreach ($preparedFields as $fieldName => $newValue) {
                    $currentValue = $currentRows[$rowId][$fieldName] ?? null;
                    if (static::normalizeValue($currentValue) !== static::normalizeValue($newValue)) {
                        $updateFields[$fieldName] = $newValue;
                    }
                }

                if ($updateFields !== []) {
                    $updateFields['UF_UPDATED_BY'] = $userId;
                    $updateFields['UF_UPDATED_AT'] = $now;
                    $dataClass::update($rowId, $updateFields);
                }

                continue;
            }

            if (static::isPreparedTableRowEmpty($preparedFields)) {
                continue;
            }

            $dataClass::add([
                'UF_PASSPORT_ID' => $passportId,
                'UF_CREATED_BY' => $userId,
                'UF_CREATED_AT' => $now,
            ] + $preparedFields);
        }
    }

    public static function normalizeValue($value): string
    {
        if (is_array($value)) {
            return '';
        }

        return trim(str_replace(["\r\n", "\r"], "\n", (string)$value));
    }

    public static function isPreparedTableRowEmpty(array $preparedFields): bool
    {
        foreach ($preparedFields as $value) {
            if (static::normalizeValue($value) !== '') {
                return false;
            }
        }

        return true;
    }

    public static function getStorageHlCodeByTabCode(string $tableCode): string
    {
        $map = [
            'members' => self::PROJECT_PASSPORT_MEMBER_HL_CODE,
            'kpi' => self::PROJECT_PASSPORT_KPI_HL_CODE,
            'budget' => self::PROJECT_PASSPORT_BUDGET_HL_CODE,
            'dependency' => self::PROJECT_PASSPORT_DEPENDENCY_HL_CODE,
            'milestone' => self::PROJECT_PASSPORT_AGES_HL_CODE,
        ];

        return $map[$tableCode] ?? '';
    }

    public static function getFieldValuesByPassportId(int $passportId): array
    {
        if ($passportId <= 0) {
            return [];
        }

        $valueClass = static::getHlClass(self::PROJECT_PASSPORT_FIELD_VALUE_HL_CODE);
        if ($valueClass === '') {
            return [];
        }

        $result = [];
        $request = $valueClass::getList([
            'filter' => ['=UF_PASSPORT_ID' => $passportId],
        ]);
        while ($row = $request->fetch()) {
            $result[(string)$row['UF_FIELD_CODE']] = $row;
        }

        return $result;
    }

    public static function prepareFieldsWithValues(array $fields, array $fieldValues): array
    {
        foreach ($fields as &$field) {
            $fieldCode = (string)($field['code'] ?? '');
            $valueRow = $fieldValues[$fieldCode] ?? [];
            $field['value'] = $valueRow['UF_VALUE'] ?? '';
            $field['updatedBy'] = !empty($valueRow['UF_UPDATED_BY']) ? (int)$valueRow['UF_UPDATED_BY'] : 0;
            $field['updatedAt'] = $valueRow['UF_UPDATED_AT'] ?? null;
        }
        unset($field);

        return $fields;
    }

    public static function getTableRows(int $passportId, string $tabCode): array
    {
        if ($passportId <= 0 || $tabCode === '') {
            return [];
        }

        $storageCode = static::getStorageHlCodeByTabCode($tabCode);
        $tableClass = static::getHlClass($storageCode);
        if ($storageCode === '' || $tableClass === '') {
            return [];
        }

        $result = [];
        $request = $tableClass::getList([
            'filter' => ['=UF_PASSPORT_ID' => $passportId],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($row = $request->fetch()) {
            $result[] = $row;
        }

        return $result;
    }

    public static function getPassportData(int $projectId): array
    {
        $result = [
            'projectId' => $projectId,
            'passportId' => 0,
            'tabs' => [],
        ];

        if ($projectId <= 0) {
            return $result;
        }

        $passportId = static::getPassportIdByProjectId($projectId);
        if ($passportId <= 0) {
            return $result;
        }

        $result['passportId'] = $passportId;
        $tabs = static::getMetaForPassport();
        if ($tabs === []) {
            return $result;
        }

        $fieldValues = static::getFieldValuesByPassportId($passportId);
        foreach ($tabs as $tab) {
            $tabCode = (string)$tab['code'];

            if (!empty($tab['fields'])) {
                $tab['fields'] = static::prepareFieldsWithValues($tab['fields'], $fieldValues);
            }

            if (!empty($tab['tables'])) {
                foreach ($tab['tables'] as $tableIndex => $table) {
                    $tab['tables'][$tableIndex]['rows'] = static::getTableRows($passportId, $tabCode);
                }
            }

            $result['tabs'][] = $tab;
        }

        return $result;
    }

    private static function normalizeCodes(array $codes): array
    {
        return array_values(array_filter(array_map(static fn ($code): string => trim((string)$code), $codes)));
    }

    private static function getCurrentUserId(): int
    {
        return (int)CurrentUser::get()->getId();
    }

    private static function getHlClass(string $hlCode): string
    {
        return $hlCode === '' ? '' : (string)CUtils::getHlBlockClassByCode($hlCode);
    }
}
