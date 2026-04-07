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

    public static function activatePassport(int $iProjectId): void
    {
        if ($iProjectId <= 0) {
            return;
        }

        $iUserId = static::getCurrentUserId();
        $sPassportClass = static::getHlClass(self::PROJECT_PASSPORT_HL_CODE);
        if ($iUserId <= 0 || $sPassportClass === '') {
            return;
        }

        $iPassportId = static::getPassportIdByProjectId($iProjectId);
        if ($iPassportId <= 0) {
            static::addPassport($iProjectId);
            return;
        }

        $sPassportClass::update($iPassportId, [
            'UF_ACTIVE' => true,
            'UF_UPDATED_BY' => $iUserId,
            'UF_UPDATED_AT' => new DateTime(),
        ]);
    }

    public static function deactivatePassport(int $iProjectId): void
    {
        if ($iProjectId <= 0) {
            return;
        }

        $iPassportId = static::getPassportIdByProjectId($iProjectId);
        $iUserId = static::getCurrentUserId();
        $sPassportClass = static::getHlClass(self::PROJECT_PASSPORT_HL_CODE);
        if ($iPassportId <= 0 || $iUserId <= 0 || $sPassportClass === '') {
            return;
        }

        $sPassportClass::update($iPassportId, [
            'UF_ACTIVE' => false,
            'UF_UPDATED_BY' => $iUserId,
            'UF_UPDATED_AT' => new DateTime(),
        ]);
    }

    public static function getPassportIdByProjectId(int $iProjectId): int
    {
        if ($iProjectId <= 0) {
            return 0;
        }

        $sPassportClass = static::getHlClass(self::PROJECT_PASSPORT_HL_CODE);
        if ($sPassportClass === '') {
            return 0;
        }

        $obRequest = $sPassportClass::getList([
            'filter' => ['UF_PROJECT_ID' => $iProjectId],
        ]);

        $aRow = $obRequest->fetch();
        return (int)($aRow['ID'] ?? 0);
    }

    public static function addPassport(int $iProjectId): int
    {
        if ($iProjectId <= 0) {
            return 0;
        }

        $sPassportClass = static::getHlClass(self::PROJECT_PASSPORT_HL_CODE);
        $iUserId = static::getCurrentUserId();
        if ($sPassportClass === '' || $iUserId <= 0) {
            return 0;
        }

        $obResult = $sPassportClass::add([
            'UF_PROJECT_ID' => $iProjectId,
            'UF_ACTIVE' => true,
            'UF_CREATED_BY' => $iUserId,
            'UF_CREATED_AT' => new DateTime(),
        ]);

        return $obResult->isSuccess() ? (int)$obResult->getId() : 0;
    }

    public static function getTabs(): array
    {
        $sTabClass = static::getHlClass(self::PROJECT_PASSPORT_TAB_HL_CODE);
        if ($sTabClass === '') {
            return [];
        }

        $aResult = [];
        $obRequest = $sTabClass::getList([
            'filter' => ['!UF_VISIBLE' => false],
            'order' => ['UF_SORT' => 'ASC'],
        ]);

        while ($aRow = $obRequest->fetch()) {
            $aResult[] = [
                'id' => (int)$aRow['ID'],
                'title' => (string)$aRow['UF_TITLE'],
                'code' => (string)$aRow['UF_CODE'],
                'fields' => [],
                'tables' => [],
            ];
        }

        return $aResult;
    }

    public static function getFieldsGroupedByTab(array $aTabCodes): array
    {
        $aTabCodes = static::normalizeCodes($aTabCodes);
        if ($aTabCodes === []) {
            return [];
        }

        $sFieldClass = static::getHlClass(self::PROJECT_PASSPORT_FIELD_HL_CODE);
        if ($sFieldClass === '') {
            return [];
        }

        $aResult = [];
        $obRequest = $sFieldClass::getList([
            'filter' => [
                'UF_TAB_CODE' => $aTabCodes,
                '!UF_VISIBLE' => false,
            ],
            'order' => ['UF_SORT' => 'ASC'],
        ]);

        while ($aRow = $obRequest->fetch()) {
            $sTabCode = (string)$aRow['UF_TAB_CODE'];
            $aResult[$sTabCode][] = [
                'id' => (int)$aRow['ID'],
                'title' => (string)$aRow['UF_TITLE'],
                'code' => (string)$aRow['UF_CODE'],
                'tab' => $sTabCode,
            ];
        }

        return $aResult;
    }

    public static function getTableMetaGroupedByTab(array $aTabCodes): array
    {
        $aTabCodes = static::normalizeCodes($aTabCodes);
        if ($aTabCodes === []) {
            return [];
        }

        $sMetaClass = static::getHlClass(self::PROJECT_PASSPORT_TABLE_META_HL_CODE);
        if ($sMetaClass === '') {
            return [];
        }

        $aResult = [];
        $obRequest = $sMetaClass::getList([
            'filter' => [
                'UF_TAB_CODE' => $aTabCodes,
                '!UF_VISIBLE' => false,
            ],
            'order' => [
                'UF_TAB_CODE' => 'ASC',
                'UF_SORT' => 'ASC',
                'ID' => 'ASC',
            ],
        ]);

        while ($aRow = $obRequest->fetch()) {
            $sTabCode = (string)$aRow['UF_TAB_CODE'];
            $aResult[$sTabCode][0]['columns'][] = [
                'id' => (int)$aRow['ID'],
                'title' => (string)$aRow['UF_TITLE'],
                'code' => (string)$aRow['UF_CODE'],
                'tabCode' => $sTabCode,
            ];
        }

        return $aResult;
    }

    public static function getMetaForPassport(): array
    {
        $aTabs = static::getTabs();
        if ($aTabs === []) {
            return [];
        }

        $aTabCodes = array_column($aTabs, 'code');
        $aFieldsByTab = static::getFieldsGroupedByTab($aTabCodes);
        $aTablesByTab = static::getTableMetaGroupedByTab($aTabCodes);

        $aResult = [];
        foreach ($aTabs as $aTab) {
            $sTabCode = (string)$aTab['code'];
            $aTab['fields'] = $aFieldsByTab[$sTabCode] ?? [];
            $aTab['tables'] = $aTablesByTab[$sTabCode] ?? [];
            $aResult[] = $aTab;
        }

        return $aResult;
    }

    public static function processTabData(int $iProjectId, array $aTabData): void
    {
        if ($iProjectId <= 0 || $aTabData === []) {
            return;
        }

        $sTabCode = trim((string)($aTabData['tabCode'] ?? ''));
        if ($sTabCode === '') {
            return;
        }

        $iPassportId = static::getPassportIdByProjectId($iProjectId);
        if ($iPassportId <= 0) {
            $iPassportId = static::addPassport($iProjectId);
        }

        if ($iPassportId <= 0) {
            return;
        }

        $aFields = is_array($aTabData['fields'] ?? null) ? $aTabData['fields'] : [];
        $aTables = is_array($aTabData['tables'] ?? null) ? $aTabData['tables'] : [];

        if ($aFields !== []) {
            static::processFieldsData($iPassportId, $sTabCode, $aFields);
        }

        foreach ($aTables as $aTableData) {
            $aRows = is_array($aTableData['rows'] ?? null) ? $aTableData['rows'] : [];
            static::processTableData($iPassportId, $sTabCode, $aRows);
        }
    }

    public static function processFieldsData(int $iPassportId, string $sTabCode, array $aFields): void
    {
        if ($iPassportId <= 0 || $sTabCode === '' || $aFields === []) {
            return;
        }

        $iUserId = static::getCurrentUserId();
        $sFieldClass = static::getHlClass(self::PROJECT_PASSPORT_FIELD_HL_CODE);
        $sValueClass = static::getHlClass(self::PROJECT_PASSPORT_FIELD_VALUE_HL_CODE);
        if ($iUserId <= 0 || $sFieldClass === '' || $sValueClass === '') {
            return;
        }

        $aMetaFields = [];
        $obFieldRequest = $sFieldClass::getList([
            'filter' => [
                '=UF_TAB_CODE' => $sTabCode,
                '!UF_VISIBLE' => false,
            ],
        ]);
        while ($aField = $obFieldRequest->fetch()) {
            $aMetaFields[(string)$aField['UF_CODE']] = $aField;
        }

        if ($aMetaFields === []) {
            return;
        }

        $aCurrentValues = [];
        $obValueRequest = $sValueClass::getList([
            'filter' => [
                '=UF_PASSPORT_ID' => $iPassportId,
                'UF_FIELD_CODE' => array_keys($aMetaFields),
            ],
        ]);
        while ($aValue = $obValueRequest->fetch()) {
            $aCurrentValues[(string)$aValue['UF_FIELD_CODE']] = $aValue;
        }

        $obNow = new DateTime();
        foreach ($aFields as $sFieldCode => $mValue) {
            $sFieldCode = trim((string)$sFieldCode);
            if ($sFieldCode === '' || !isset($aMetaFields[$sFieldCode])) {
                continue;
            }

            $sNewValue = static::normalizeValue($mValue);
            if (!isset($aCurrentValues[$sFieldCode])) {
                if ($sNewValue === '') {
                    continue;
                }

                $sValueClass::add([
                    'UF_PASSPORT_ID' => $iPassportId,
                    'UF_FIELD_CODE' => $sFieldCode,
                    'UF_VALUE' => $sNewValue,
                    'UF_CREATED_BY' => $iUserId,
                    'UF_CREATED_AT' => $obNow,
                ]);
                continue;
            }

            $aCurrentValue = $aCurrentValues[$sFieldCode];
            if (static::normalizeValue($aCurrentValue['UF_VALUE'] ?? '') === $sNewValue) {
                continue;
            }

            $sValueClass::update((int)$aCurrentValue['ID'], [
                'UF_VALUE' => $sNewValue,
                'UF_UPDATED_BY' => $iUserId,
                'UF_UPDATED_AT' => $obNow,
            ]);
        }
    }

    public static function processTableData(int $iPassportId, string $sTabCode, array $aRows): void
    {
        if ($iPassportId <= 0 || $sTabCode === '') {
            return;
        }

        $iUserId = static::getCurrentUserId();
        $sStorageCode = static::getStorageHlCodeByTabCode($sTabCode);
        $sDataClass = static::getHlClass($sStorageCode);
        $sMetaClass = static::getHlClass(self::PROJECT_PASSPORT_TABLE_META_HL_CODE);
        if ($iUserId <= 0 || $sStorageCode === '' || $sDataClass === '' || $sMetaClass === '') {
            return;
        }

        $aAllowedFields = [];
        $obMetaRequest = $sMetaClass::getList([
            'filter' => [
                '=UF_TAB_CODE' => $sTabCode,
                '!UF_VISIBLE' => false,
            ],
            'order' => [
                'UF_SORT' => 'ASC',
                'ID' => 'ASC',
            ],
        ]);

        while ($aMetaRow = $obMetaRequest->fetch()) {
            $sFieldName = trim((string)$aMetaRow['UF_CODE']);
            if ($sFieldName !== '') {
                $aAllowedFields[$sFieldName] = true;
            }
        }

        if ($aAllowedFields === []) {
            return;
        }

        $aCurrentRows = [];
        $obDataRequest = $sDataClass::getList([
            'filter' => ['=UF_PASSPORT_ID' => $iPassportId],
            'order' => ['ID' => 'ASC'],
        ]);
        while ($aDataRow = $obDataRequest->fetch()) {
            $aCurrentRows[(int)$aDataRow['ID']] = $aDataRow;
        }

        $aIncomingRowIds = [];
        foreach ($aRows as $aRow) {
            if (is_array($aRow) && (int)($aRow['id'] ?? 0) > 0) {
                $aIncomingRowIds[(int)$aRow['id']] = true;
            }
        }

        foreach (array_keys($aCurrentRows) as $iCurrentRowId) {
            if (!isset($aIncomingRowIds[$iCurrentRowId])) {
                $sDataClass::delete($iCurrentRowId);
                unset($aCurrentRows[$iCurrentRowId]);
            }
        }

        $obNow = new DateTime();
        foreach ($aRows as $aRow) {
            if (!is_array($aRow)) {
                continue;
            }

            $iRowId = (int)($aRow['id'] ?? 0);
            $aPreparedFields = [];
            foreach ($aAllowedFields as $sFieldName => $bUnused) {
                if (array_key_exists($sFieldName, $aRow)) {
                    $aPreparedFields[$sFieldName] = static::normalizeValue($aRow[$sFieldName]);
                }
            }

            if ($iRowId > 0 && isset($aCurrentRows[$iRowId])) {
                $aUpdateFields = [];
                foreach ($aPreparedFields as $sFieldName => $mNewValue) {
                    $mCurrentValue = $aCurrentRows[$iRowId][$sFieldName] ?? null;
                    if (static::normalizeValue($mCurrentValue) !== static::normalizeValue($mNewValue)) {
                        $aUpdateFields[$sFieldName] = $mNewValue;
                    }
                }

                if ($aUpdateFields !== []) {
                    $aUpdateFields['UF_UPDATED_BY'] = $iUserId;
                    $aUpdateFields['UF_UPDATED_AT'] = $obNow;
                    $sDataClass::update($iRowId, $aUpdateFields);
                }

                continue;
            }

            if (static::isPreparedTableRowEmpty($aPreparedFields)) {
                continue;
            }

            $sDataClass::add([
                'UF_PASSPORT_ID' => $iPassportId,
                'UF_CREATED_BY' => $iUserId,
                'UF_CREATED_AT' => $obNow,
            ] + $aPreparedFields);
        }
    }

    public static function normalizeValue($mValue): string
    {
        if (is_array($mValue)) {
            return '';
        }

        return trim(str_replace(["\r\n", "\r"], "\n", (string)$mValue));
    }

    public static function isPreparedTableRowEmpty(array $aPreparedFields): bool
    {
        foreach ($aPreparedFields as $mValue) {
            if (static::normalizeValue($mValue) !== '') {
                return false;
            }
        }

        return true;
    }

    public static function getStorageHlCodeByTabCode(string $sTableCode): string
    {
        $aMap = [
            'members' => self::PROJECT_PASSPORT_MEMBER_HL_CODE,
            'kpi' => self::PROJECT_PASSPORT_KPI_HL_CODE,
            'budget' => self::PROJECT_PASSPORT_BUDGET_HL_CODE,
            'dependency' => self::PROJECT_PASSPORT_DEPENDENCY_HL_CODE,
            'milestone' => self::PROJECT_PASSPORT_AGES_HL_CODE,
        ];

        return $aMap[$sTableCode] ?? '';
    }

    public static function getFieldValuesByPassportId(int $iPassportId): array
    {
        if ($iPassportId <= 0) {
            return [];
        }

        $sValueClass = static::getHlClass(self::PROJECT_PASSPORT_FIELD_VALUE_HL_CODE);
        if ($sValueClass === '') {
            return [];
        }

        $aResult = [];
        $obRequest = $sValueClass::getList([
            'filter' => ['=UF_PASSPORT_ID' => $iPassportId],
        ]);
        while ($aRow = $obRequest->fetch()) {
            $aResult[(string)$aRow['UF_FIELD_CODE']] = $aRow;
        }

        return $aResult;
    }

    public static function prepareFieldsWithValues(array $aFields, array $aFieldValues): array
    {
        foreach ($aFields as &$aField) {
            $sFieldCode = (string)($aField['code'] ?? '');
            $aValueRow = $aFieldValues[$sFieldCode] ?? [];
            $aField['value'] = $aValueRow['UF_VALUE'] ?? '';
            $aField['updatedBy'] = !empty($aValueRow['UF_UPDATED_BY']) ? (int)$aValueRow['UF_UPDATED_BY'] : 0;
            $aField['updatedAt'] = $aValueRow['UF_UPDATED_AT'] ?? null;
        }
        unset($aField);

        return $aFields;
    }

    public static function getTableRows(int $iPassportId, string $sTabCode): array
    {
        if ($iPassportId <= 0 || $sTabCode === '') {
            return [];
        }

        $sStorageCode = static::getStorageHlCodeByTabCode($sTabCode);
        $sTableClass = static::getHlClass($sStorageCode);
        if ($sStorageCode === '' || $sTableClass === '') {
            return [];
        }

        $aResult = [];
        $obRequest = $sTableClass::getList([
            'filter' => ['=UF_PASSPORT_ID' => $iPassportId],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($aRow = $obRequest->fetch()) {
            $aResult[] = $aRow;
        }

        return $aResult;
    }

    public static function getPassportData(int $iProjectId): array
    {
        $aResult = [
            'projectId' => $iProjectId,
            'passportId' => 0,
            'tabs' => [],
        ];

        if ($iProjectId <= 0) {
            return $aResult;
        }

        $iPassportId = static::getPassportIdByProjectId($iProjectId);
        if ($iPassportId <= 0) {
            return $aResult;
        }

        $aResult['passportId'] = $iPassportId;
        $aTabs = static::getMetaForPassport();
        if ($aTabs === []) {
            return $aResult;
        }

        $aFieldValues = static::getFieldValuesByPassportId($iPassportId);
        foreach ($aTabs as $aTab) {
            $sTabCode = (string)$aTab['code'];

            if (!empty($aTab['fields'])) {
                $aTab['fields'] = static::prepareFieldsWithValues($aTab['fields'], $aFieldValues);
            }

            if (!empty($aTab['tables'])) {
                foreach ($aTab['tables'] as $iTableIndex => $aTable) {
                    $aTab['tables'][$iTableIndex]['rows'] = static::getTableRows($iPassportId, $sTabCode);
                }
            }

            $aResult['tabs'][] = $aTab;
        }

        return $aResult;
    }

    private static function normalizeCodes(array $aCodes): array
    {
        return array_values(array_filter(array_map(static fn ($mCode): string => trim((string)$mCode), $aCodes)));
    }

    private static function getCurrentUserId(): int
    {
        return (int)CurrentUser::get()->getId();
    }

    private static function getHlClass(string $sHlCode): string
    {
        return $sHlCode === '' ? '' : (string)CUtils::getHlBlockClassByCode($sHlCode);
    }
}
