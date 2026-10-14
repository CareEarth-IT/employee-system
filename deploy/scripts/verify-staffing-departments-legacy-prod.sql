SELECT COUNT(*) AS legacy_affiliation_cnt
FROM affiliation_histories
WHERE department IN ('大阪営業部', '東京営業部', '名古屋営業部', '福岡営業部', '営業部', '派遣事業部')
   OR CONCAT(',', department, ',') LIKE '%,大阪営業部,%'
   OR CONCAT(',', department, ',') LIKE '%,東京営業部,%'
   OR CONCAT(',', department, ',') LIKE '%,名古屋営業部,%'
   OR CONCAT(',', department, ',') LIKE '%,福岡営業部,%'
   OR CONCAT(',', department, ',') LIKE '%,営業部,%'
   OR CONCAT(',', department, ',') LIKE '%,派遣事業部,%';

SELECT COUNT(*) AS legacy_hr_cnt
FROM employee_hr_details
WHERE department_primary IN ('大阪営業部', '東京営業部', '名古屋営業部', '福岡営業部', '営業部', '派遣事業部')
   OR CONCAT(',', department_primary, ',') LIKE '%,大阪営業部,%'
   OR CONCAT(',', department_primary, ',') LIKE '%,東京営業部,%'
   OR CONCAT(',', department_primary, ',') LIKE '%,名古屋営業部,%'
   OR CONCAT(',', department_primary, ',') LIKE '%,福岡営業部,%'
   OR CONCAT(',', department_primary, ',') LIKE '%,営業部,%'
   OR CONCAT(',', department_primary, ',') LIKE '%,派遣事業部,%';

SELECT COUNT(*) AS staffing_affiliation_cnt
FROM affiliation_histories
WHERE department = '人材派遣事業部'
   OR CONCAT(',', department, ',') LIKE '%,人材派遣事業部,%';
