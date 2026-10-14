SELECT
  CASE department
    WHEN '大阪営業部' THEN 'osaka'
    WHEN '東京営業部' THEN 'tokyo'
    WHEN '名古屋営業部' THEN 'nagoya'
    WHEN '福岡営業部' THEN 'fukuoka'
    WHEN '営業部' THEN 'eigyo'
    WHEN '派遣事業部' THEN 'haken'
    WHEN '人材派遣事業部' THEN 'staffing'
    ELSE CONCAT('other:', CHAR_LENGTH(department))
  END AS label,
  COUNT(*) AS cnt
FROM affiliation_histories
WHERE department LIKE '%営業部%' OR department LIKE '%派遣事業部%'
GROUP BY department
ORDER BY cnt DESC;

SELECT
  CASE department_primary
    WHEN '大阪営業部' THEN 'osaka'
    WHEN '東京営業部' THEN 'tokyo'
    WHEN '名古屋営業部' THEN 'nagoya'
    WHEN '福岡営業部' THEN 'fukuoka'
    WHEN '営業部' THEN 'eigyo'
    WHEN '派遣事業部' THEN 'haken'
    WHEN '人材派遣事業部' THEN 'staffing'
    ELSE CONCAT('other:', CHAR_LENGTH(department_primary))
  END AS label,
  COUNT(*) AS cnt
FROM employee_hr_details
WHERE department_primary LIKE '%営業部%' OR department_primary LIKE '%派遣事業部%'
GROUP BY department_primary
ORDER BY cnt DESC;
