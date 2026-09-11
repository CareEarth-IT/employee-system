SELECT
    u.employee_id,
    HEX(COALESCE(ehd.section_primary, '')) AS hr_section_hex,
    HEX(COALESCE(ah.section, '')) AS aff_section_hex
FROM users u
INNER JOIN employee_hr_details ehd ON ehd.user_id = u.id
INNER JOIN affiliation_histories ah ON ah.user_id = u.id
    AND ah.enrollment_status = '在籍中'
    AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
WHERE COALESCE(ehd.department_primary, '') LIKE '%GR%'
   OR COALESCE(ehd.department_primary, '') LIKE '%グローバル%'
ORDER BY u.employee_id
