-- Normalize GR department storage in production.
-- Target form: department = GR部（グローバル部）, jurisdiction/location = 大阪|東京|名古屋|福岡
-- Skips comma-separated multi-department values.

UPDATE employee_hr_details
SET
    department_primary = 'GR部（グローバル部）',
    jurisdiction = '大阪'
WHERE department_primary = '大阪グローバル事業部'
  AND department_primary NOT LIKE '%,%';

UPDATE employee_hr_details
SET
    department_primary = 'GR部（グローバル部）',
    jurisdiction = '東京'
WHERE department_primary = '東京グローバル事業部'
  AND department_primary NOT LIKE '%,%';

UPDATE employee_hr_details
SET
    department_primary = 'GR部（グローバル部）',
    jurisdiction = '名古屋'
WHERE department_primary = '名古屋グローバル事業部'
  AND department_primary NOT LIKE '%,%';

UPDATE employee_hr_details
SET
    department_primary = 'GR部（グローバル部）',
    jurisdiction = '福岡'
WHERE department_primary = '福岡グローバル事業部'
  AND department_primary NOT LIKE '%,%';

UPDATE employee_hr_details
SET
    department_primary = 'GR部（グローバル部）',
    jurisdiction = '大阪'
WHERE department_primary IN ('大阪-GR部', '大阪‐GR部');

UPDATE employee_hr_details
SET
    department_primary = 'GR部（グローバル部）',
    jurisdiction = '東京'
WHERE department_primary IN ('東京-GR部', '東京‐GR部');

UPDATE employee_hr_details ehd
INNER JOIN users u ON u.id = ehd.user_id
INNER JOIN affiliation_histories ah ON ah.user_id = u.id
    AND ah.enrollment_status = '在籍中'
    AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
SET ehd.jurisdiction = ah.location
WHERE ehd.department_primary = 'GR部（グローバル部）'
  AND (ehd.jurisdiction IS NULL OR ehd.jurisdiction = '')
  AND ah.location IN ('大阪', '東京', '名古屋', '福岡');

UPDATE employee_hr_details ehd
SET ehd.jurisdiction = '名古屋'
WHERE ehd.department_primary = 'GR部（グローバル部）'
  AND (ehd.jurisdiction IS NULL OR ehd.jurisdiction = '')
  AND ehd.section_primary LIKE '%_名古屋%';

UPDATE employee_hr_details ehd
SET ehd.jurisdiction = '大阪'
WHERE ehd.department_primary = 'GR部（グローバル部）'
  AND (ehd.jurisdiction IS NULL OR ehd.jurisdiction = '')
  AND ehd.section_primary LIKE '%_大阪%';

UPDATE employee_hr_details ehd
SET ehd.jurisdiction = '東京'
WHERE ehd.department_primary = 'GR部（グローバル部）'
  AND (ehd.jurisdiction IS NULL OR ehd.jurisdiction = '')
  AND ehd.section_primary LIKE '%_東京%';

UPDATE employee_hr_details ehd
SET ehd.jurisdiction = '福岡'
WHERE ehd.department_primary = 'GR部（グローバル部）'
  AND (ehd.jurisdiction IS NULL OR ehd.jurisdiction = '')
  AND ehd.section_primary LIKE '%_福岡%';

UPDATE affiliation_histories ah
INNER JOIN users u ON u.id = ah.user_id
SET
    ah.department = 'GR部（グローバル部）',
    ah.location = '大阪'
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department = '大阪グローバル事業部'
  AND ah.department NOT LIKE '%,%';

UPDATE affiliation_histories ah
INNER JOIN users u ON u.id = ah.user_id
SET
    ah.department = 'GR部（グローバル部）',
    ah.location = '東京'
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department = '東京グローバル事業部'
  AND ah.department NOT LIKE '%,%';

UPDATE affiliation_histories ah
INNER JOIN users u ON u.id = ah.user_id
SET
    ah.department = 'GR部（グローバル部）',
    ah.location = '名古屋'
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department = '名古屋グローバル事業部'
  AND ah.department NOT LIKE '%,%';

UPDATE affiliation_histories ah
INNER JOIN users u ON u.id = ah.user_id
SET
    ah.department = 'GR部（グローバル部）',
    ah.location = '福岡'
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department = '福岡グローバル事業部'
  AND ah.department NOT LIKE '%,%';

UPDATE affiliation_histories ah
INNER JOIN users u ON u.id = ah.user_id
SET
    ah.department = 'GR部（グローバル部）',
    ah.location = '大阪'
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department IN ('大阪-GR部', '大阪‐GR部');

UPDATE affiliation_histories ah
INNER JOIN users u ON u.id = ah.user_id
SET
    ah.department = 'GR部（グローバル部）',
    ah.location = '東京'
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department IN ('東京-GR部', '東京‐GR部');

UPDATE affiliation_histories ah
SET ah.location = '東京'
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department = 'GR部（グローバル部）'
  AND (ah.location IS NULL OR ah.location = '');

UPDATE affiliation_histories ah
INNER JOIN employee_hr_details ehd ON ehd.user_id = ah.user_id
SET ah.location = ehd.jurisdiction
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department = 'GR部（グローバル部）'
  AND (ah.location IS NULL OR ah.location = '')
  AND ehd.jurisdiction IN ('大阪', '東京', '名古屋', '福岡');

UPDATE employee_hr_details
SET department_primary = REPLACE(department_primary, '大阪グローバル事業部', 'GR部（グローバル部）')
WHERE department_primary LIKE '%,%' AND department_primary LIKE '%大阪グローバル事業部%';

UPDATE employee_hr_details
SET department_primary = REPLACE(department_primary, '東京グローバル事業部', 'GR部（グローバル部）')
WHERE department_primary LIKE '%,%' AND department_primary LIKE '%東京グローバル事業部%';

UPDATE employee_hr_details
SET department_primary = REPLACE(department_primary, '名古屋グローバル事業部', 'GR部（グローバル部）')
WHERE department_primary LIKE '%,%' AND department_primary LIKE '%名古屋グローバル事業部%';

UPDATE employee_hr_details
SET department_primary = REPLACE(department_primary, '福岡グローバル事業部', 'GR部（グローバル部）')
WHERE department_primary LIKE '%,%' AND department_primary LIKE '%福岡グローバル事業部%';

UPDATE employee_hr_details
SET jurisdiction = '大阪'
WHERE department_primary LIKE '%GR部（グローバル部）%'
  AND (jurisdiction IS NULL OR jurisdiction = '')
  AND (department_primary LIKE '%大阪%' OR section_primary LIKE '%_大阪%');

UPDATE employee_hr_details
SET jurisdiction = '東京'
WHERE department_primary LIKE '%GR部（グローバル部）%'
  AND (jurisdiction IS NULL OR jurisdiction = '')
  AND (department_primary LIKE '%東京%' OR section_primary LIKE '%_東京%');

UPDATE employee_hr_details
SET jurisdiction = '名古屋'
WHERE department_primary LIKE '%GR部（グローバル部）%'
  AND (jurisdiction IS NULL OR jurisdiction = '')
  AND (department_primary LIKE '%名古屋%' OR section_primary LIKE '%_名古屋%');

UPDATE employee_hr_details
SET jurisdiction = '福岡'
WHERE department_primary LIKE '%GR部（グローバル部）%'
  AND (jurisdiction IS NULL OR jurisdiction = '')
  AND (department_primary LIKE '%福岡%' OR section_primary LIKE '%_福岡%');

UPDATE affiliation_histories ah
SET ah.department = REPLACE(ah.department, '大阪グローバル事業部', 'GR部（グローバル部）')
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department LIKE '%,%'
  AND ah.department LIKE '%大阪グローバル事業部%';

UPDATE affiliation_histories ah
SET ah.department = REPLACE(ah.department, '東京グローバル事業部', 'GR部（グローバル部）')
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department LIKE '%,%'
  AND ah.department LIKE '%東京グローバル事業部%';

UPDATE affiliation_histories ah
SET ah.department = REPLACE(ah.department, '名古屋グローバル事業部', 'GR部（グローバル部）')
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department LIKE '%,%'
  AND ah.department LIKE '%名古屋グローバル事業部%';

UPDATE affiliation_histories ah
SET ah.department = REPLACE(ah.department, '福岡グローバル事業部', 'GR部（グローバル部）')
WHERE ah.enrollment_status = '在籍中'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
  AND ah.department LIKE '%,%'
  AND ah.department LIKE '%福岡グローバル事業部%';
