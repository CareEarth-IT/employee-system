SELECT
  CASE department
    WHEN '大阪営業部' THEN 'osaka'
    WHEN '東京営業部' THEN 'tokyo'
    WHEN '名古屋営業部' THEN 'nagoya'
    WHEN '福岡営業部' THEN 'fukuoka'
    WHEN '営業部' THEN 'eigyo'
    ELSE CONCAT('other:', HEX(department), ':', CHAR_LENGTH(department))
  END AS label,
  department,
  COUNT(*) AS cnt
FROM affiliation_histories
WHERE department LIKE '%営業部%'
GROUP BY department
ORDER BY cnt DESC;

SELECT
  CASE department_primary
    WHEN '大阪営業部' THEN 'osaka'
    WHEN '東京営業部' THEN 'tokyo'
    WHEN '名古屋営業部' THEN 'nagoya'
    WHEN '福岡営業部' THEN 'fukuoka'
    WHEN '営業部' THEN 'eigyo'
    ELSE CONCAT('other:', HEX(department_primary), ':', CHAR_LENGTH(department_primary))
  END AS label,
  department_primary,
  COUNT(*) AS cnt
FROM employee_hr_details
WHERE department_primary LIKE '%営業部%'
GROUP BY department_primary
ORDER BY cnt DESC;
