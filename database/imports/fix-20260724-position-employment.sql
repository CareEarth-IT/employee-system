-- Fix imported employees: position 正社員 -> employment_type, department -> department_primary
UPDATE affiliation_histories ah
INNER JOIN users u ON u.id = ah.user_id
SET ah.position = NULL
WHERE u.employee_id IN ('10431','10433','10459','10460','10461','10463','10464','10465','10466','10467')
  AND ah.position = '正社員'
  AND (ah.end_date IS NULL OR ah.end_date >= CURDATE());

UPDATE employee_hr_details ehd
INNER JOIN users u ON u.id = ehd.user_id
INNER JOIN affiliation_histories ah
  ON ah.user_id = u.id
 AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
SET
  ehd.employment_type = COALESCE(NULLIF(ehd.employment_type, ''), '正社員'),
  ehd.department_primary = CASE
    WHEN ah.department IS NOT NULL
     AND ah.department <> ''
     AND ah.department <> '未設定'
    THEN ah.department
    ELSE ehd.department_primary
  END
WHERE u.employee_id IN ('10431','10433','10459','10460','10461','10463','10464','10465','10466','10467');

SELECT u.employee_id, u.email, ah.department, ah.position, ehd.employment_type, ehd.department_primary
FROM users u
LEFT JOIN affiliation_histories ah
  ON ah.user_id = u.id
 AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
LEFT JOIN employee_hr_details ehd ON ehd.user_id = u.id
WHERE u.employee_id IN ('10431','10433','10459','10460','10461','10463','10464','10465','10466','10467')
ORDER BY u.employee_id;
