-- RESUMEN POR CATEGORÍA, MES, RUBRO Y SUBRUBRO

SELECT
    SUM(amount) AS total
FROM transactions
WHERE created_at >= STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', 
    CASE LOWER(:mes)
        WHEN 'enero' THEN '01'
        WHEN 'febrero' THEN '02'
        WHEN 'marzo' THEN '03'
        WHEN 'abril' THEN '04'
        WHEN 'mayo' THEN '05'
        WHEN 'junio' THEN '06'
        WHEN 'julio' THEN '07'
        WHEN 'agosto' THEN '08'
        WHEN 'septiembre' THEN '09'
        WHEN 'octubre' THEN '10'
        WHEN 'noviembre' THEN '11'
        WHEN 'diciembre' THEN '12'
    END,
'-01'), '%Y-%m-%d')

AND created_at < DATE_ADD(
    STR_TO_DATE(CONCAT(YEAR(CURDATE()), '-', 
    CASE LOWER(:mes)
        WHEN 'enero' THEN '01'
        WHEN 'febrero' THEN '02'
        WHEN 'marzo' THEN '03'
        WHEN 'abril' THEN '04'
        WHEN 'mayo' THEN '05'
        WHEN 'junio' THEN '06'
        WHEN 'julio' THEN '07'
        WHEN 'agosto' THEN '08'
        WHEN 'septiembre' THEN '09'
        WHEN 'octubre' THEN '10'
        WHEN 'noviembre' THEN '11'
        WHEN 'diciembre' THEN '12'
    END,
'-01'), '%Y-%m-%d'),
INTERVAL 1 MONTH
)

AND type = :type
AND category = :category
AND subcategory = :subcategory;