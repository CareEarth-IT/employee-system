-- Normalize legacy sales departments to 人材派遣事業部.
-- Targets: 大阪営業部 / 東京営業部 / 名古屋営業部 / 福岡営業部 / 営業部 / 派遣事業部
-- Also rewrites those tokens inside comma-separated multi-department values.
-- Location / jurisdiction is filled from the legacy prefix when empty.

-- affiliation_histories: single-value departments
UPDATE affiliation_histories
SET
    location = CASE
        WHEN (location IS NULL OR location = '') AND department = '大阪営業部' THEN '大阪'
        WHEN (location IS NULL OR location = '') AND department = '東京営業部' THEN '東京'
        WHEN (location IS NULL OR location = '') AND department = '名古屋営業部' THEN '名古屋'
        WHEN (location IS NULL OR location = '') AND department = '福岡営業部' THEN '福岡'
        ELSE location
    END,
    department = '人材派遣事業部'
WHERE department IN ('大阪営業部', '東京営業部', '名古屋営業部', '福岡営業部', '営業部', '派遣事業部')
  AND department NOT LIKE '%,%';

-- affiliation_histories: fill location from legacy token before rewrite (multi)
UPDATE affiliation_histories
SET location = '大阪'
WHERE (location IS NULL OR location = '')
  AND department LIKE '%大阪営業部%';

UPDATE affiliation_histories
SET location = '東京'
WHERE (location IS NULL OR location = '')
  AND department LIKE '%東京営業部%';

UPDATE affiliation_histories
SET location = '名古屋'
WHERE (location IS NULL OR location = '')
  AND department LIKE '%名古屋営業部%';

UPDATE affiliation_histories
SET location = '福岡'
WHERE (location IS NULL OR location = '')
  AND department LIKE '%福岡営業部%';

-- affiliation_histories: multi-value token rewrite (longer names first)
UPDATE affiliation_histories
SET department = REPLACE(department, '名古屋営業部', '人材派遣事業部')
WHERE department LIKE '%名古屋営業部%';

UPDATE affiliation_histories
SET department = REPLACE(department, '大阪営業部', '人材派遣事業部')
WHERE department LIKE '%大阪営業部%';

UPDATE affiliation_histories
SET department = REPLACE(department, '東京営業部', '人材派遣事業部')
WHERE department LIKE '%東京営業部%';

UPDATE affiliation_histories
SET department = REPLACE(department, '福岡営業部', '人材派遣事業部')
WHERE department LIKE '%福岡営業部%';

-- 派遣事業部 / 営業部 (comma-token replace so 人材派遣事業部 is not partially matched)
UPDATE affiliation_histories
SET department = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', department, ','), ',派遣事業部,', ',人材派遣事業部,'))
WHERE CONCAT(',', department, ',') LIKE '%,派遣事業部,%';

UPDATE affiliation_histories
SET department = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', department, ','), ',営業部,', ',人材派遣事業部,'))
WHERE CONCAT(',', department, ',') LIKE '%,営業部,%';

UPDATE affiliation_histories
SET department = REPLACE(department, '人材派遣事業部,人材派遣事業部', '人材派遣事業部')
WHERE department LIKE '%人材派遣事業部,人材派遣事業部%';

-- employee_hr_details: single-value
UPDATE employee_hr_details
SET
    jurisdiction = CASE
        WHEN (jurisdiction IS NULL OR jurisdiction = '') AND department_primary = '大阪営業部' THEN '大阪'
        WHEN (jurisdiction IS NULL OR jurisdiction = '') AND department_primary = '東京営業部' THEN '東京'
        WHEN (jurisdiction IS NULL OR jurisdiction = '') AND department_primary = '名古屋営業部' THEN '名古屋'
        WHEN (jurisdiction IS NULL OR jurisdiction = '') AND department_primary = '福岡営業部' THEN '福岡'
        ELSE jurisdiction
    END,
    department_primary = '人材派遣事業部'
WHERE department_primary IN ('大阪営業部', '東京営業部', '名古屋営業部', '福岡営業部', '営業部', '派遣事業部')
  AND department_primary NOT LIKE '%,%';

UPDATE employee_hr_details
SET jurisdiction = '大阪'
WHERE (jurisdiction IS NULL OR jurisdiction = '')
  AND department_primary LIKE '%大阪営業部%';

UPDATE employee_hr_details
SET jurisdiction = '東京'
WHERE (jurisdiction IS NULL OR jurisdiction = '')
  AND department_primary LIKE '%東京営業部%';

UPDATE employee_hr_details
SET jurisdiction = '名古屋'
WHERE (jurisdiction IS NULL OR jurisdiction = '')
  AND department_primary LIKE '%名古屋営業部%';

UPDATE employee_hr_details
SET jurisdiction = '福岡'
WHERE (jurisdiction IS NULL OR jurisdiction = '')
  AND department_primary LIKE '%福岡営業部%';

UPDATE employee_hr_details
SET department_primary = REPLACE(department_primary, '名古屋営業部', '人材派遣事業部')
WHERE department_primary LIKE '%名古屋営業部%';

UPDATE employee_hr_details
SET department_primary = REPLACE(department_primary, '大阪営業部', '人材派遣事業部')
WHERE department_primary LIKE '%大阪営業部%';

UPDATE employee_hr_details
SET department_primary = REPLACE(department_primary, '東京営業部', '人材派遣事業部')
WHERE department_primary LIKE '%東京営業部%';

UPDATE employee_hr_details
SET department_primary = REPLACE(department_primary, '福岡営業部', '人材派遣事業部')
WHERE department_primary LIKE '%福岡営業部%';

UPDATE employee_hr_details
SET department_primary = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', department_primary, ','), ',派遣事業部,', ',人材派遣事業部,'))
WHERE CONCAT(',', department_primary, ',') LIKE '%,派遣事業部,%';

UPDATE employee_hr_details
SET department_primary = TRIM(BOTH ',' FROM REPLACE(CONCAT(',', department_primary, ','), ',営業部,', ',人材派遣事業部,'))
WHERE CONCAT(',', department_primary, ',') LIKE '%,営業部,%';

UPDATE employee_hr_details
SET department_primary = REPLACE(department_primary, '人材派遣事業部,人材派遣事業部', '人材派遣事業部')
WHERE department_primary LIKE '%人材派遣事業部,人材派遣事業部%';
