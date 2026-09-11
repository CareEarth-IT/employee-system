SELECT
    u.employee_id,
    HEX(u.name) AS name_hex,
    HEX(COALESCE(ehd.department_primary, '')) AS dept_hex,
    HEX(COALESCE(ehd.jurisdiction, '')) AS jur_hex,
    HEX(COALESCE(ehd.section_primary, '')) AS section_hex,
    HEX(COALESCE(ah.section, '')) AS aff_section_hex,
    HEX(COALESCE(ah.department, '')) AS aff_dept_hex,
    HEX(COALESCE(ah.location, '')) AS aff_loc_hex,
    CASE WHEN ah.id IS NULL THEN '' ELSE '1' END AS has_active_aff
FROM users u
LEFT JOIN employee_hr_details ehd ON ehd.user_id = u.id
LEFT JOIN affiliation_histories ah ON ah.user_id = u.id
    AND ah.enrollment_status = '在籍中'
    AND (ah.end_date IS NULL OR ah.end_date >= CURDATE())
WHERE COALESCE(ehd.department_primary, '') LIKE '%GR%'
   OR COALESCE(ehd.department_primary, '') LIKE '%グローバル%'
   OR COALESCE(ah.department, '') LIKE '%GR%'
   OR COALESCE(ah.department, '') LIKE '%グローバル%'
ORDER BY u.employee_id
