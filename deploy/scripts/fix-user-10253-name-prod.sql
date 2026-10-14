UPDATE users
SET
  first_name = 'フィ フン',
  name = 'グエン フィ フン',
  updated_at = UTC_TIMESTAMP()
WHERE employee_id = '10253'
  AND id = 208;

SELECT employee_id, last_name, first_name, name
FROM users
WHERE employee_id = '10253';
