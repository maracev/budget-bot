SELECT
    *
FROM transactions t
WHERE 
    t.type = 'outgo'
    AND t.category = 'mascotas'
    AND YEAR(t.created_at) = YEAR(CURDATE())
    AND MONTH(t.created_at) = MONTH(CURDATE());
