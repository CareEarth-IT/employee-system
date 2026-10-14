SELECT HEX(department) AS hex_value, department, COUNT(*) AS cnt
FROM affiliation_histories
WHERE (department LIKE '%営業部%' OR department LIKE '%派遣事業部%')
  AND department <> '人材派遣事業部'
GROUP BY department;

SELECT HEX(department_primary) AS hex_value, department_primary, COUNT(*) AS cnt
FROM employee_hr_details
WHERE (department_primary LIKE '%営業部%' OR department_primary LIKE '%派遣事業部%')
  AND department_primary <> '人材派遣事業部'
GROUP BY department_primary;
