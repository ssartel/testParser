SELECT
    p.date,
    SUM(p.quantity * pl.price) AS total_cost
FROM
    products p
        JOIN price_log pl ON
                p.product_id = pl.product_id AND
                pl.date = (
                    SELECT MAX(date)
                    FROM price_log
                    WHERE product_id = p.product_id AND date <= p.date
                )
WHERE
    p.date BETWEEN '2020-01-01' AND '2020-01-10'
GROUP BY
    p.date
ORDER BY
    p.date;
